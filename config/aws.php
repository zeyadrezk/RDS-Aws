<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AWS Credentials
    |--------------------------------------------------------------------------
    |
    | These configuration values are used when connecting to AWS services.
    | Update these with your actual AWS credentials or use environment variables.
    |
    */
    'credentials' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],

    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'version' => 'latest',

    /*
    |--------------------------------------------------------------------------
    | RDS Settings
    |--------------------------------------------------------------------------
    |
    | Default configuration for RDS instances created by the provisioning service.
    |
    */
    'rds' => [
        'default_vpc_id' => env('AWS_VPC_ID'),
        'subnet_group_name' => env('AWS_DB_SUBNET_GROUP', 'default'),
        'security_group_ids' => explode(',', env('AWS_SECURITY_GROUP_IDS', '')),
        'backup_retention_period' => env('AWS_BACKUP_RETENTION_PERIOD', 7),
        'publicly_accessible' => env('AWS_RDS_PUBLICLY_ACCESSIBLE', false),
        'multi_az' => env('AWS_RDS_MULTI_AZ', false),
        'monitoring_interval' => env('AWS_RDS_MONITORING_INTERVAL', 0),
        'deletion_protection' => env('AWS_RDS_DELETION_PROTECTION', true),
        'engine' => env('AWS_RDS_ENGINE', 'postgres'),
        'engine_version' => env('AWS_RDS_ENGINE_VERSION', '14.6'),
        'instance_class' => env('AWS_RDS_INSTANCE_CLASS', 'db.t3.micro'),
        'storage_type' => env('AWS_RDS_STORAGE_TYPE', 'gp2'),
        'allocated_storage' => env('AWS_RDS_ALLOCATED_STORAGE', 20),
        'max_allocated_storage' => env('AWS_RDS_MAX_ALLOCATED_STORAGE', 100),
        'encrypted' => env('AWS_RDS_ENCRYPTED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parameter Store / Secrets Manager Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for AWS Parameter Store or Secrets Manager
    |
    */
    'parameter_store' => [
        'enabled' => env('AWS_PARAMETER_STORE_ENABLED', false),
        'prefix' => env('AWS_PARAMETER_STORE_PREFIX', '/production/database/'),
    ],

    'secrets_manager' => [
        'enabled' => env('AWS_SECRETS_MANAGER_ENABLED', false),
        'prefix' => env('AWS_SECRETS_MANAGER_PREFIX', 'database/'),
    ],
];
