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

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|string',
            'db_name' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string|min:8',
        ]);

        $instanceIdentifier = 'rds-' . Str::random(8);

        try {
            $this->awsRdsService->createRdsInstance(
                $instanceIdentifier,
                $request->db_name,
                $request->username,
                $request->password
            );

            $rdsInstance = RdsInstance::create([
                'client_id' => $request->client_id,
                'instance_identifier' => $instanceIdentifier,
                'status' => 'creating',
            ]);

            return response()->json($rdsInstance, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create RDS instance: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $rdsInstance = RdsInstance::findOrFail($id);

        try {
            $result = $this->awsRdsService->describeRdsInstance($rdsInstance->instance_identifier);
            $dbInstance = $result['DBInstances'][0];

            $rdsInstance->status = $dbInstance['DBInstanceStatus'];
            if ($dbInstance['DBInstanceStatus'] == 'available') {
                $rdsInstance->endpoint = $dbInstance['Endpoint']['Address'];
            }
            $rdsInstance->save();

            return response()->json($rdsInstance);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve RDS instance details: ' . $e->getMessage()], 500);
        }
    }
    public function index()
    {
        $rdsInstance = RdsInstance::get();

        try {
            $result = $this->awsRdsService->describeRdsInstance($rdsInstance->instance_identifier);
            $dbInstance = $result['DBInstances'][0];

            $rdsInstance->status = $dbInstance['DBInstanceStatus'];
            if ($dbInstance['DBInstanceStatus'] == 'available') {
                $rdsInstance->endpoint = $dbInstance['Endpoint']['Address'];
            }
            $rdsInstance->save();

            return response()->json($rdsInstance);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to retrieve RDS instance details: ' . $e->getMessage()], 500);
        }
    }
}
