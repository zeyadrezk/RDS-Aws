<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionRdsInstanceJob;
use App\Jobs\CheckRdsStatusJob;
use App\Models\Client;
use App\Models\Database;
use App\Models\Service;
use App\Services\RdsDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientDatabaseController extends Controller
{
    /**
     * Display a listing of the client's databases.
     *
     * @param Client $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Client $client)
    {
        $databases = $client->databases()->with('service')->get();

        return response()->json([
            'success' => true,
            'data' => $databases,
        ]);
    }

    /**
     * Display the specified database.
     *
     * @param Client $client
     * @param Database $database
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Client $client, Database $database)
    {
        if ($database->client_id !== $client->id) {
            return response()->json([
                'success' => false,
                'message' => 'Database does not belong to this client',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $database->load('service'),
        ]);
    }

    /**
     * Provision databases for all client services.
     *
     * @param Request $request
     * @param Client $client
     * @return \Illuminate\Http\JsonResponse
     */
    public function provisionAllDatabases(Request $request, Client $client)
    {
        try {
            // Dispatch job to provision databases for all services
            ProvisionRdsInstanceJob::dispatch($client);

            return response()->json([
                'success' => true,
                'message' => 'Database provisioning initiated for all services',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to initiate database provisioning', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate database provisioning: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Provision a database for a specific client service.
     *
     * @param Request $request
     * @param Client $client
     * @param Service $service
     * @return \Illuminate\Http\JsonResponse
     */
    public function provisionServiceDatabase(Request $request, Client $client, Service $service)
    {
        try {
            // Check if client is subscribed to this service
            $isSubscribed = $client->services()->where('services.id', $service->id)->exists();

            if (!$isSubscribed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client is not subscribed to this service',
                ], 400);
            }

            // Dispatch job to provision database for the specific service
            ProvisionRdsInstanceJob::dispatch($client, $service);

            return response()->json([
                'success' => true,
                'message' => "Database provisioning initiated for {$service->name} service",
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to initiate service database provisioning', [
                'client_id' => $client->id,
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate database provisioning: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check the status of a database.
     *
     * @param Request $request
     * @param Client $client
     * @param Database $database
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkStatus(Request $request, Client $client, Database $database)
    {
        if ($database->client_id !== $client->id) {
            return response()->json([
                'success' => false,
                'message' => 'Database does not belong to this client',
            ], 403);
        }

        try {
            $rdsDatabaseService = app(RdsDatabaseService::class);
            $status = $rdsDatabaseService->checkInstanceStatus($database);

            return response()->json([
                'success' => true,
                'data' => [
                    'database_id' => $database->id,
                    'instance_identifier' => $database->instance_identifier,
                    'status' => $status,
                    'provisioning_status' => $database->provisioning_status,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check database status', [
                'client_id' => $client->id,
                'database_id' => $database->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a database and its RDS instance.
     *
     * @param Request $request
     * @param Client $client
     * @param Database $database
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Client $client, Database $database)
    {
        if ($database->client_id !== $client->id) {
            return response()->json([
                'success' => false,
                'message' => 'Database does not belong to this client',
            ], 403);
        }

        try {
            $rdsDatabaseService = app(RdsDatabaseService::class);

            // Validate if we should skip final snapshot
            $skipFinalSnapshot = $request->input('skip_final_snapshot', false);
            $finalSnapshotIdentifier = $request->input('final_snapshot_identifier');

            // Delete RDS instance
            $result = $rdsDatabaseService->deleteRdsInstance(
                $database,
                $skipFinalSnapshot,
                $finalSnapshotIdentifier
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Database deletion initiated',
                    'data' => [
                        'database_id' => $database->id,
                        'status' => $database->status,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete database',
                    'data' => [
                        'database_id' => $database->id,
                        'error_message' => $database->error_message,
                    ],
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete database', [
                'client_id' => $client->id,
                'database_id' => $database->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete database: ' . $e->getMessage(),
            ], 500);
        }
    }
}
