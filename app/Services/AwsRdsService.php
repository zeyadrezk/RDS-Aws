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
        return $this->rdsClient->createDBInstance([
            'DBInstanceIdentifier' => $instanceIdentifier,
            'AllocatedStorage' => 20,
            'DBInstanceClass' => 'db.t3.micro',
            'Engine' => 'postgres', //mysql
            'MasterUsername' => $username,
            'MasterUserPassword' => $password,
            'DBName' => $dbName,
            'BackupRetentionPeriod' => 7,
            'PubliclyAccessible' => false,
            'DBSubnetGroupName' => 'saas-admin-db-subnet-group',
            'VpcSecurityGroupIds' => ['sg-09d9a96f0b49fdb8f'],
            'StorageEncrypted' => true,
            'MultiAZ' => false
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
}
