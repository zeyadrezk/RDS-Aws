<?php

namespace App\Services;

use Aws\Rds\RdsClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;

class AwsRdsService
{
    protected $rdsClient;

    public function __construct()
    {
        $this->rdsClient = new RdsClient([
            'version' => 'latest',
            'region'  => config('aws.region', 'us-east-1'),
            'credentials' => [
                'key'    => config('aws.key'),
                'secret' => config('aws.secret'),
            ],
        ]);
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
            'Engine' => 'mysql',
            'MasterUsername' => $username,
            'MasterUserPassword' => $password,
            'DBName' => $dbName,
            'BackupRetentionPeriod' => 7,
            'PubliclyAccessible' => false,
            'DBSubnetGroupName' => 'saas-admin-db-subnet-group', // Use the created subnet group
            'VpcSecurityGroupIds' => ['sg-09d9a96f0b49fdb8f'], // Default VPC security group
            'StorageEncrypted' => true,
            'MultiAZ' => false // Set to true for production
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
