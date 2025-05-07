<?php
namespace App\Services;

use Aws\Rds\RdsClient;

class AwsRdsService
{
    protected $rdsClient;

    public function __construct()
    {
        $this->rdsClient = new RdsClient([
            'version' => 'latest',
            'region' => config('aws.region'),
            'credentials' => [
                'key' => config('aws.access_key_id'),
                'secret' => config('aws.secret_access_key'),
            ],
        ]);
    }

    public function createRdsInstance($instanceIdentifier, $dbName, $username, $password)
    {
        return $this->rdsClient->createDBInstance([
            'DBInstanceIdentifier' => $instanceIdentifier,
            'AllocatedStorage' => 20,
            'DBInstanceClass' => 'db.t2.micro',
            'Engine' => 'mysql',
            'MasterUsername' => $username,
            'MasterUserPassword' => $password,
            'DBName' => $dbName,
            'BackupRetentionPeriod' => 0,
            'PubliclyAccessible' => true,
        ]);
    }

    public function describeRdsInstance($instanceIdentifier)
    {
        return $this->rdsClient->describeDBInstances([
            'DBInstanceIdentifier' => $instanceIdentifier,
        ]);
    }
}

