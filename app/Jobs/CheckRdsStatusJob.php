<?php

namespace App\Jobs;

use App\Models\Database;
use App\Services\RdsDatabaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckRdsStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * The database model instance.
     *
     * @var \App\Models\Database
     */
    protected $database;

    /**
     * Create a new job instance.
     *
     * @param Database $database
     * @return void
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Execute the job.
     *
     * @param RdsDatabaseService $rdsDatabaseService
     * @return void
     */
    public function handle(RdsDatabaseService $rdsDatabaseService)
    {
        Log::info('Checking RDS instance status', [
            'database_id' => $this->database->id,
            'instance_identifier' => $this->database->instance_identifier,
        ]);

        try {
            $status = $rdsDatabaseService->checkInstanceStatus($this->database);

            Log::info('RDS instance status checked', [
                'database_id' => $this->database->id,
                'instance_identifier' => $this->database->instance_identifier,
                'status' => $status,
            ]);

            // If the instance is still creating, schedule another check after backoff
            if ($status === 'creating') {
                self::dispatch($this->database)
                    ->delay(now()->addSeconds($this->backoff));

                Log::info('Scheduled another status check', [
                    'database_id' => $this->database->id,
                    'delay' => $this->backoff,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to check RDS instance status', [
                'database_id' => $this->database->id,
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
        Log::error('RDS status check job failed', [
            'database_id' => $this->database->id,
            'error' => $exception->getMessage(),
        ]);

        // Update the database status to indicate error
        $this->database->update([
            'status' => 'error',
            'provisioning_status' => 'monitoring_failed',
            'error_message' => 'Status check failed: ' . $exception->getMessage(),
        ]);
    }
}
