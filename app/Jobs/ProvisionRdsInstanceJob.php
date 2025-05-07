<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Service;
use App\Services\RdsDatabaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProvisionRdsInstanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600;

    /**
     * The client model instance.
     *
     * @var \App\Models\Client
     */
    protected $client;

    /**
     * The service model instance or null if not service-specific.
     *
     * @var \App\Models\Service|null
     */
    protected $service;

    /**
     * Create a new job instance.
     *
     * @param Client $client
     * @param Service|null $service
     * @return void
     */
    public function __construct(Client $client, ?Service $service = null)
    {
        $this->client = $client;
        $this->service = $service;
    }

    /**
     * Execute the job.
     *
     * @param RdsDatabaseService $rdsDatabaseService
     * @return void
     */
    public function handle(RdsDatabaseService $rdsDatabaseService)
    {
        Log::info('Starting RDS instance provisioning job', [
            'client_id' => $this->client->id,
            'service_id' => $this->service ? $this->service->id : null,
        ]);

        try {
            if ($this->service) {
                // Provision for a specific service
                $database = $rdsDatabaseService->provisionServiceDatabase($this->client, $this->service);

                Log::info('RDS instance provisioning initiated for service', [
                    'client_id' => $this->client->id,
                    'service_id' => $this->service->id,
                    'database_id' => $database->id,
                ]);
            } else {
                // Provision for all client's active services
                $results = $rdsDatabaseService->provisionClientDatabases($this->client);

                Log::info('RDS instances provisioning initiated for all services', [
                    'client_id' => $this->client->id,
                    'results' => $results,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to provision RDS instance', [
                'client_id' => $this->client->id,
                'service_id' => $this->service ? $this->service->id : null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('RDS instance provisioning job failed', [
            'client_id' => $this->client->id,
            'service_id' => $this->service ? $this->service->id : null,
            'error' => $exception->getMessage(),
        ]);
    }
}
