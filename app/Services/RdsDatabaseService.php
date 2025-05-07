<?php

namespace App\Services;

use Aws\Rds\RdsClient;
use Aws\Exception\AwsException;
use App\Exceptions\RdsProvisioningException;
use App\Models\Client;
use App\Models\Database;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RdsDatabaseService
{
    protected $rdsClient;
    protected $awsConfig;

    /**
     * Create a new RDS Database Service instance.
     *
     * @param RdsClient|null $rdsClient
     */
    public function __construct(RdsClient $rdsClient = null)
    {
        if ($rdsClient) {
            $this->rdsClient = $rdsClient;
        } else {
            $this->awsConfig = config('aws');
            $this->rdsClient = new RdsClient([
                'version' => $this->awsConfig['version'],
                'region' => $this->awsConfig['region'],
                'credentials' => [
                    'key' => $this->awsConfig['credentials']['key'],
                    'secret' => $this->awsConfig['credentials']['secret'],
                ],
            ]);
        }
    }

    /**
     * Provision database instances for a client based on subscribed services.
     *
     * @param Client $client
     * @return array
     */
    public function provisionClientDatabases(Client $client): array
    {
        Log::info("Starting database provisioning for client: {$client->name}", [
            'client_id' => $client->id,
        ]);

        $results = [];
        $services = $client->services()->where('is_active', true)->get();

        if ($services->isEmpty()) {
            Log::warning("Client has no active services to provision databases for", [
                'client_id' => $client->id,
            ]);
            return $results;
        }

        foreach ($services as $service) {
            try {
                $database = $this->provisionServiceDatabase($client, $service);
                $results[$service->slug] = [
                    'success' => true,
                    'database_id' => $database->id,
                    'message' => "Database for {$service->name} provisioning initiated",
                ];
            } catch (\Exception $e) {
                Log::error("Failed to provision database for service", [
                    'client_id' => $client->id,
                    'service_id' => $service->id,
                    'error' => $e->getMessage(),
                ]);

                $results[$service->slug] = [
                    'success' => false,
                    'message' => "Failed to provision database: {$e->getMessage()}",
                ];
            }
        }

        return $results;
    }

    /**
     * Provision a database for a specific client and service.
     *
     * @param Client $client
     * @param Service $service
     * @return Database
     * @throws RdsProvisioningException
     */
    public function provisionServiceDatabase(Client $client, Service $service): Database
    {
        // Generate database information
        $dbName = $this->generateDatabaseName($client, $service);
        $instanceIdentifier = $this->generateInstanceIdentifier($client, $service);
        $username = $this->generateUsername($client, $service);
        $password = Str::random(32);

        // Create the database record in our admin database
        $database = new Database([
            'client_id' => $client->id,
            'service_id' => $service->id,
            'name' => $dbName,
            'instance_identifier' => $instanceIdentifier,
            'database_name' => $dbName,
            'username' => $username,
            'password' => $password,
            'engine' => $this->awsConfig['rds']['engine'],
            'engine_version' => $this->awsConfig['rds']['engine_version'],
            'instance_class' => $this->awsConfig['rds']['instance_class'],
            'storage_type' => $this->awsConfig['rds']['storage_type'],
            'allocated_storage' => $this->awsConfig['rds']['allocated_storage'],
            'encrypted' => $this->awsConfig['rds']['encrypted'],
            'provisioning_status' => 'queued',
        ]);

        $database->save();

        // Initiate the RDS instance creation process
        try {
            $this->createRdsInstance($database);
            return $database;
        } catch (\Exception $e) {
            $database->update([
                'provisioning_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw new RdsProvisioningException(
                "Failed to create RDS instance: {$e->getMessage()}",
                0,
                $e,
                $client->id,
                $database->id,
                $service->id
            );
        }
    }

    /**
     * Create an RDS database instance.
     *
     * @param Database $database
     * @return bool
     * @throws \Exception
     */
    public function createRdsInstance(Database $database): bool
    {
        $database->update(['provisioning_status' => 'creating_instance']);

        try {
            $params = [
                'DBInstanceIdentifier' => $database->instance_identifier,
                'DBName' => $database->database_name,
                'AllocatedStorage' => $database->allocated_storage,
                'DBInstanceClass' => $database->instance_class,
                'Engine' => $database->engine,
                'EngineVersion' => $database->engine_version,
                'MasterUsername' => $database->username,
                'MasterUserPassword' => $database->password,
                'StorageType' => $database->storage_type,
                'StorageEncrypted' => $database->encrypted,
                'BackupRetentionPeriod' => $this->awsConfig['rds']['backup_retention_period'],
                'PubliclyAccessible' => $this->awsConfig['rds']['publicly_accessible'],
                'MultiAZ' => $this->awsConfig['rds']['multi_az'],
                'DBSubnetGroupName' => $this->awsConfig['rds']['subnet_group_name'],
                'VpcSecurityGroupIds' => $this->awsConfig['rds']['security_group_ids'],
                'DeletionProtection' => $this->awsConfig['rds']['deletion_protection'],
                'MaxAllocatedStorage' => $this->awsConfig['rds']['max_allocated_storage'],
                'Tags' => [
                    [
                        'Key' => 'Client',
                        'Value' => $database->client->name,
                    ],
                    [
                        'Key' => 'Service',
                        'Value' => $database->service ? $database->service->name : 'General',
                    ],
                    [
                        'Key' => 'Environment',
                        'Value' => app()->environment(),
                    ],
                    [
                        'Key' => 'ManagedBy',
                        'Value' => 'RdsProvisioningService',
                    ],
                ],
            ];

            if ($this->awsConfig['rds']['monitoring_interval'] > 0) {
                $params['MonitoringInterval'] = $this->awsConfig['rds']['monitoring_interval'];
                $params['MonitoringRoleArn'] = env('AWS_MONITORING_ROLE_ARN');
            }

            $result = $this->rdsClient->createDBInstance($params);

            $dbInstance = $result->get('DBInstance');
            $database->update([
                'rds_instance_id' => $dbInstance['DBInstanceIdentifier'],
                'provisioning_status' => 'creating',
                'status' => 'creating',
            ]);

            // Store the connection details in AWS Parameter Store or Secrets Manager if enabled
            if ($this->awsConfig['parameter_store']['enabled']) {
                $this->storeInParameterStore($database);
            }

            if ($this->awsConfig['secrets_manager']['enabled']) {
                $this->storeInSecretsManager($database);
            }

            Log::info("RDS instance creation initiated", [
                'client_id' => $database->client_id,
                'database_id' => $database->id,
                'instance_identifier' => $database->instance_identifier,
            ]);

            return true;
        } catch (AwsException $e) {
            Log::error("AWS RDS creation error", [
                'client_id' => $database->client_id,
                'database_id' => $database->id,
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
                'aws_error_message' => $e->getAwsErrorMessage(),
            ]);

            $database->update([
                'provisioning_status' => 'failed',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw new RdsProvisioningException(
                "AWS RDS Error: {$e->getAwsErrorMessage()}",
                0,
                $e,
                $database->client_id,
                $database->id,
                $database->service_id,
                ['aws_error_code' => $e->getAwsErrorCode()]
            );
        } catch (\Exception $e) {
            Log::error("Generic RDS creation error", [
                'client_id' => $database->client_id,
                'database_id' => $database->id,
                'error' => $e->getMessage(),
            ]);

            $database->update([
                'provisioning_status' => 'failed',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check and update the status of an RDS instance.
     *
     * @param Database $database
     * @return string The current status
     */
    public function checkInstanceStatus(Database $database): string
    {
        try {
            $result = $this->rdsClient->describeDBInstances([
                'DBInstanceIdentifier' => $database->instance_identifier,
            ]);

            $dbInstance = $result->get('DBInstances')[0];
            $status = $dbInstance['DBInstanceStatus'];

            $database->update([
                'status' => $status,
            ]);

            // If the instance is available, update the endpoint information
            if ($status === 'available' && $database->host === null) {
                $endpoint = $dbInstance['Endpoint'];
                $database->update([
                    'host' => $endpoint['Address'],
                    'port' => $endpoint['Port'],
                    'provisioning_status' => 'completed',
                ]);

                // Initialize the database schema if needed
                if ($database->service && $database->service->schema_template) {
                    $this->initializeSchema($database);
                }
            }

            return $status;
        } catch (\Exception $e) {
            Log::error("Failed to check RDS instance status", [
                'database_id' => $database->id,
                'instance_identifier' => $database->instance_identifier,
                'error' => $e->getMessage(),
            ]);

            return 'error';
        }
    }

    /**
     * Delete an RDS instance.
     *
     * @param Database $database
     * @param bool $skipFinalSnapshot Whether to skip the final snapshot
     * @param string|null $finalSnapshotIdentifier
     * @return bool
     */
    public function deleteRdsInstance(
        Database $database,
        bool     $skipFinalSnapshot = false,
        ?string  $finalSnapshotIdentifier = null
    ): bool
    {
        if (!$skipFinalSnapshot && !$finalSnapshotIdentifier) {
            $finalSnapshotIdentifier = "{$database->instance_identifier}-final-" . date('YmdHis');
        }

        try {
            $params = [
                'DBInstanceIdentifier' => $database->instance_identifier,
                'SkipFinalSnapshot' => $skipFinalSnapshot,
            ];

            if (!$skipFinalSnapshot) {
                $params['FinalDBSnapshotIdentifier'] = $finalSnapshotIdentifier;
            }

            $this->rdsClient->deleteDBInstance($params);

            $database->update([
                'status' => 'deleting',
                'provisioning_status' => 'deleting',
            ]);

            Log::info("RDS instance deletion initiated", [
                'database_id' => $database->id,
                'instance_identifier' => $database->instance_identifier,
                'skip_final_snapshot' => $skipFinalSnapshot,
                'final_snapshot_identifier' => $finalSnapshotIdentifier,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete RDS instance", [
                'database_id' => $database->id,
                'instance_identifier' => $database->instance_identifier,
                'error' => $e->getMessage(),
            ]);

            $database->update([
                'provisioning_status' => 'delete_failed',
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Initialize database schema based on service template.
     *
     * @param Database $database
     * @return bool
     */
    protected function initializeSchema(Database $database): bool
    {
        try {
            $database->update(['provisioning_status' => 'initializing_schema']);

            // Get the schema template path
            $schemaTemplate = $database->service->schema_template;
            $schemaPath = database_path("schema_templates/{$schemaTemplate}.sql");

            if (!file_exists($schemaPath)) {
                Log::warning("Schema template not found", [
                    'database_id' => $database->id,
                    'schema_template' => $schemaTemplate,
                    'path' => $schemaPath,
                ]);

                $database->update([
                    'provisioning_status' => 'schema_not_found',
                ]);

                return false;
            }

            // Read and execute the schema SQL
            $schemaSql = file_get_contents($schemaPath);

            // Connect to the database and execute the schema
            $connection = $this->createTemporaryConnection($database);
            $connection->unprepared($schemaSql);

            $database->update([
                'provisioning_status' => 'schema_initialized',
            ]);

            Log::info("Schema initialized successfully", [
                'database_id' => $database->id,
                'schema_template' => $schemaTemplate,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to initialize schema", [
                'database_id' => $database->id,
                'error' => $e->getMessage(),
            ]);

            $database->update([
                'provisioning_status' => 'schema_failed',
                'error_message' => "Schema initialization failed: {$e->getMessage()}",
            ]);

            return false;
        }
    }

    /**
     * Create a temporary database connection for schema initialization.
     *
     * @param Database $database
     * @return \Illuminate\Database\Connection
     */
    protected function createTemporaryConnection(Database $database)
    {
        $config = [
            'driver' => $database->engine === 'postgres' ? 'pgsql' : 'mysql',
            'host' => $database->host,
            'port' => $database->port,
            'database' => $database->database_name,
            'username' => $database->username,
            'password' => $database->password,
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => 'prefer',
        ];

        return \Illuminate\Support\Facades\DB::connection()->setDatabaseName($database->database_name)
            ->setPdo(new \PDO(
                "{$config['driver']}:host={$config['host']};port={$config['port']};dbname={$config['database']}",
                $config['username'],
                $config['password']
            ));
    }

    /**
     * Store database credentials in AWS Parameter Store.
     *
     * @param Database $database
     * @return bool
     */
    protected function storeInParameterStore(Database $database): bool
    {
        try {
            $ssmClient = new \Aws\Ssm\SsmClient([
                'version' => $this->awsConfig['version'],
                'region' => $this->awsConfig['region'],
                'credentials' => [
                    'key' => $this->awsConfig['credentials']['key'],
                    'secret' => $this->awsConfig['credentials']['secret'],
                ],
            ]);

            $prefix = $this->awsConfig['parameter_store']['prefix'];
            $parameterBase = "{$prefix}{$database->client->slug}/{$database->name}";

            // Store each connection parameter
            $parameters = [
                'host' => $database->host ?: $database->instance_identifier . '.rds.amazonaws.com',
                'port' => $database->port,
                'database' => $database->database_name,
                'username' => $database->username,
                'password' => $database->password,
            ];

            foreach ($parameters as $key => $value) {
                $ssmClient->putParameter([
                    'Name' => "{$parameterBase}/{$key}",
                    'Value' => (string)$value,
                    'Type' => $key === 'password' ? 'SecureString' : 'String',
                    'Overwrite' => true,
                ]);
            }

            Log::info("Database credentials stored in Parameter Store", [
                'database_id' => $database->id,
                'parameter_base' => $parameterBase,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to store credentials in Parameter Store", [
                'database_id' => $database->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Store database credentials in AWS Secrets Manager.
     *
     * @param Database $database
     * @return bool
     */
    protected function storeInSecretsManager(Database $database): bool
    {
        try {
            $secretsClient = new \Aws\SecretsManager\SecretsManagerClient([
                'version' => $this->awsConfig['version'],
                'region' => $this->awsConfig['region'],
                'credentials' => [
                    'key' => $this->awsConfig['credentials']['key'],
                    'secret' => $this->awsConfig['credentials']['secret'],
                ],
            ]);

            $prefix = $this->awsConfig['secrets_manager']['prefix'];
            $secretId = "{$prefix}{$database->client->slug}/{$database->name}";

            // Create the secret with all connection details
            $secretValue = json_encode([
                'host' => $database->host ?: $database->instance_identifier . '.rds.amazonaws.com',
                'port' => $database->port,
                'dbname' => $database->database_name,
                'username' => $database->username,
                'password' => $database->password,
                'engine' => $database->engine,
            ]);

            $secretsClient->createSecret([
                'Name' => $secretId,
                'Description' => "Database connection details for {$database->client->name} - {$database->name}",
                'SecretString' => $secretValue,
                'Tags' => [
                    [
                        'Key' => 'Client',
                        'Value' => $database->client->name,
                    ],
                    [
                        'Key' => 'Service',
                        'Value' => $database->service ? $database->service->name : 'General',
                    ],
                    [
                        'Key' => 'Environment',
                        'Value' => app()->environment(),
                    ],
                    [
                        'Key' => 'ManagedBy',
                        'Value' => 'RdsProvisioningService',
                    ],
                ],
            ]);

            Log::info("Database credentials stored in Secrets Manager", [
                'database_id' => $database->id,
                'secret_id' => $secretId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to store credentials in Secrets Manager", [
                'database_id' => $database->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate a database name based on client and service.
     *
     * @param Client $client
     * @param Service|null $service
     * @return string
     */
    protected function generateDatabaseName(Client $client, ?Service $service = null): string
    {
        $clientSlug = Str::slug($client->slug, '_');

        if ($service) {
            $serviceSlug = Str::slug($service->slug, '_');
            return "client_{$clientSlug}_{$serviceSlug}_db";
        }

        return "client_{$clientSlug}_db";
    }

    /**
     * Generate an instance identifier based on client and service.
     *
     * @param Client $client
     * @param Service|null $service
     * @return string
     */
    protected function generateInstanceIdentifier(Client $client, ?Service $service = null): string
    {
        $env = Str::slug(app()->environment(), '-');
        $clientSlug = Str::slug($client->slug, '-');

        if ($service) {
            $serviceSlug = Str::slug($service->slug, '-');
            $identifier = "{$env}-{$clientSlug}-{$serviceSlug}";
        } else {
            $identifier = "{$env}-{$clientSlug}";
        }

        // AWS RDS identifier constraints
        $identifier = strtolower($identifier);
        $identifier = substr($identifier, 0, 63);

        return $identifier;
    }

    /**
     * Generate a username based on client and service.
     *
     * @param Client $client
     * @param Service|null $service
     * @return string
     */
    protected function generateUsername(Client $client, ?Service $service = null): string
    {
        $clientSlug = Str::slug($client->slug, '_');

        if ($service) {
            $serviceSlug = Str::slug($service->slug, '_');
            $username = "{$clientSlug}_{$serviceSlug}_user";
        } else {
            $username = "{$clientSlug}_user";
        }

        // AWS RDS username constraints - username must start with a letter
        $username = strtolower($username);
        $username = substr($username, 0, 16); // RDS username length limit

        return $username;
    }
}
