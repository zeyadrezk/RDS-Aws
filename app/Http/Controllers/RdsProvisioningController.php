<?php

namespace App\Http\Controllers;

use App\Models\RdsInstance;
use App\Services\AwsRdsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RdsProvisioningController extends Controller
{
    protected $awsRdsService;

    public function __construct(AwsRdsService $awsRdsService)
    {
        $this->awsRdsService = $awsRdsService;
    }

    /**
     * Display a listing of all RDS instances.
     */
    public function index()
    {
        $rdsInstances = RdsInstance::all();

        // Update status for all instances
        foreach ($rdsInstances as $instance) {
            try {
                $this->updateInstanceStatus($instance);
            } catch (\Exception $e) {
                // Log error but continue with other instances
                \Log::error("Failed to update instance {$instance->instance_identifier}: {$e->getMessage()}");
            }
        }

        return response()->json($rdsInstances);
    }

    /**
     * Store a newly created RDS instance.
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|string',
            'db_name' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string|min:8',
            'subnet_group_name' => 'nullable|string',
        ]);

        $instanceIdentifier = 'rds-' . strtolower(Str::random(8));
        $subnetGroupName = $request->subnet_group_name ?? 'saas-admin-db-subnet-group';

        try {
            // Create subnet group if it doesn't exist
            try {
                $this->awsRdsService->describeDBSubnetGroup($subnetGroupName);
            } catch (\Exception $e) {
                // Subnet group doesn't exist, create it
                $this->awsRdsService->createDBSubnetGroup(
                    $subnetGroupName,
                    'Subnet group for SAAS Admin RDS instances'
                );
            }

            // Create the RDS instance
            $this->awsRdsService->createRdsInstance(
                $instanceIdentifier,
                $request->db_name,
                $request->username,
                $request->password,
                $subnetGroupName
            );

            // Store record in database
            $rdsInstance = RdsInstance::create([
                'client_id' => $request->client_id,
                'instance_identifier' => $instanceIdentifier,
                'status' => 'creating',
            ]);

            return response()->json($rdsInstance, 201);
        } catch (\Exception $e) {
            \Log::error('Failed to create RDS instance: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create RDS instance: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified RDS instance.
     */
    public function show($id)
    {
        $rdsInstance = RdsInstance::findOrFail($id);

        try {
            $this->updateInstanceStatus($rdsInstance);
            return response()->json($rdsInstance);
        } catch (\Exception $e) {
            \Log::error('Failed to retrieve RDS instance details: ' . $e->getMessage());
            return response()->json([
                'instance' => $rdsInstance,
                'error' => 'Failed to retrieve latest RDS instance details: ' . $e->getMessage()
            ], 200);
        }
    }

    /**
     * Update instance status and endpoint information.
     */
    private function updateInstanceStatus(RdsInstance $rdsInstance)
    {
        $result = $this->awsRdsService->describeRdsInstance($rdsInstance->instance_identifier);

        if (!empty($result['DBInstances'])) {
            $dbInstance = $result['DBInstances'][0];
            $rdsInstance->status = $dbInstance['DBInstanceStatus'];

            if ($dbInstance['DBInstanceStatus'] == 'available' && isset($dbInstance['Endpoint']['Address'])) {
                $rdsInstance->endpoint = $dbInstance['Endpoint']['Address'];
                $rdsInstance->port = $dbInstance['Endpoint']['Port'] ?? 3306;
            }

            $rdsInstance->save();
        }

        return $rdsInstance;
    }

    /**
     * Delete an RDS instance.
     */
    public function destroy($id)
    {
        $rdsInstance = RdsInstance::findOrFail($id);

        try {
            $this->awsRdsService->deleteRdsInstance($rdsInstance->instance_identifier);
            $rdsInstance->status = 'deleting';
            $rdsInstance->save();

            return response()->json(['message' => 'RDS instance deletion initiated']);
        } catch (\Exception $e) {
            \Log::error('Failed to delete RDS instance: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete RDS instance: ' . $e->getMessage()], 500);
        }
    }
}
