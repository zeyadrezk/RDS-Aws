<?php

namespace App\Services;

use Aws\Rds\RdsClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class AwsRdsService
{
    protected $rdsClient;

    public function __construct()
    {
        // Basic configuration with credentials from environment variables
        $config = [
            'version' => 'latest',
            'region'  => Config::get('services.aws.region', 'us-east-1'),
        ];

        // Add credentials if they exist in config
        if (Config::has('services.aws.key') && Config::has('services.aws.secret')) {
            $config['credentials'] = [
                'key'    => Config::get('services.aws.key'),
                'secret' => Config::get('services.aws.secret'),
            ];
        }

        $this->rdsClient = new RdsClient($config);
    }

    /**
     * Create a DB subnet group.
     *
     * @param string $groupName
     * @param string $description
     * @return \Aws\Result
     */
    public function createDBSubnetGroup($groupName, $description)
    {
        try {
            return $this->rdsClient->createDBSubnetGroup([
                'DBSubnetGroupName' => $groupName,
                'DBSubnetGroupDescription' => $description,
                'SubnetIds' => [
                    'subnet-0ed82b596c74499e5', // Private subnet in us-east-1a
                    'subnet-0c3de980fb35e5b83'  // Private subnet in us-east-1b
                ]
            ]);
        } catch (AwsException $e) {
            // If subnet group already exists, we can ignore this error
            if (strpos($e->getMessage(), 'DBSubnetGroupAlreadyExists') !== false) {
                Log::info("Subnet group {$groupName} already exists");
                return true;
            }

            // Otherwise, rethrow the exception
            throw $e;
        }
    }

    /**
     * Create an RDS instance.
     *
     * @param string $instanceIdentifier
     * @param string $dbName
     * @param string $username
     * @param string $password
     * @return \Aws\Result
     */
    public function createRdsInstance($instanceIdentifier, $dbName, $username, $password)
    {
        //for mysql
//        return $this->rdsClient->createDBInstance([
//            'DBInstanceIdentifier' => $instanceIdentifier,
//            'AllocatedStorage' => 20,
//            'DBInstanceClass' => 'db.t3.micro',
//            'Engine' => 'mysql',
//            'MasterUsername' => $username,
//            'MasterUserPassword' => $password,
//            'DBName' => $dbName,
//            'BackupRetentionPeriod' => 7,
//            'PubliclyAccessible' => false,
//            'DBSubnetGroupName' => 'saas-admin-db-subnet-group',
//            'VpcSecurityGroupIds' => ['sg-09d9a96f0b49fdb8f'],
//            'StorageEncrypted' => true,
//            'MultiAZ' => false
//        ]);

        try {
            $engineVersions = $this->rdsClient->describeDBEngineVersions([
                'Engine' => 'postgres',
            ]);

            // Get the latest available PostgreSQL version
            $latestVersion = null;
            foreach ($engineVersions['DBEngineVersions'] as $version) {
                $latestVersion = $version['EngineVersion'];
                // We could implement logic here to find the latest 15.x version
                // For now, we'll just use the last one returned
            }

            Log::info("Using PostgreSQL version: {$latestVersion}");

            // If we couldn't find a version, default to a known supported version
            if (!$latestVersion) {
                $latestVersion = '15.3'; // Fallback to a commonly supported version
            }
        } catch (AwsException $e) {
            Log::warning("Failed to get PostgreSQL versions: " . $e->getMessage());
            $latestVersion = '15.3'; // Fallback to a commonly supported version
        }

        return $this->rdsClient->createDBInstance([
            'DBInstanceIdentifier' => $instanceIdentifier,
            'AllocatedStorage' => 20,
            'DBInstanceClass' => 'db.t3.micro',
            'Engine' => 'postgres',
            'EngineVersion' => $latestVersion, // Use the detected version
            'MasterUsername' => $username,
            'MasterUserPassword' => $password,
            'DBName' => $dbName,
            'BackupRetentionPeriod' => 7,
            'PubliclyAccessible' => false,
            'DBSubnetGroupName' => 'saas-admin-db-subnet-group',
            'VpcSecurityGroupIds' => ['sg-09d9a96f0b49fdb8f'],
            'StorageEncrypted' => true,
            'MultiAZ' => false,
            'LicenseModel' => 'postgresql-license',
            'Port' => 5432, // Default PostgreSQL port
            'StorageType' => 'gp2', // General Purpose SSD
            'EnablePerformanceInsights' => true, // Enable Performance Insights for monitoring
            'PerformanceInsightsRetentionPeriod' => 7 // Retention period in days
        ]);

    }

    /**
     * Describe an RDS instance.
     *
     * @param string $instanceIdentifier
     * @return \Aws\Result
     */
    public function describeRdsInstance($instanceIdentifier)
    {
        return $this->rdsClient->describeDBInstances([
            'DBInstanceIdentifier' => $instanceIdentifier
        ]);
    }


    /**
     * List available PostgreSQL versions supported by AWS RDS.
     *
     * @return array
     */
    public function listPostgresVersions()
    {
        try {
            $result = $this->rdsClient->describeDBEngineVersions([
                'Engine' => 'postgres',
            ]);

            $versions = [];
            foreach ($result['DBEngineVersions'] as $version) {
                $versions[] = $version['EngineVersion'];
            }

            return $versions;
        } catch (AwsException $e) {
            Log::error("Failed to list PostgreSQL versions: " . $e->getMessage());
            throw $e;
        }
    }

}
