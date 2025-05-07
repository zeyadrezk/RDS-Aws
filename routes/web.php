<?php

use App\Http\Controllers\ClientDatabaseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::prefix('clients/{client}')->group(function () {
    // Get all databases for a client
    Route::get('databases', [ClientDatabaseController::class, 'index']);

    // Get specific database details
    Route::get('databases/{database}', [ClientDatabaseController::class, 'show']);

    // Provision databases for all client services
    Route::post('databases/provision-all', [ClientDatabaseController::class, 'provisionAllDatabases']);

    // Provision database for a specific service
    Route::post('services/{service}/provision-database', [ClientDatabaseController::class, 'provisionServiceDatabase']);

    // Check database status
    Route::get('databases/{database}/status', [ClientDatabaseController::class, 'checkStatus']);

    // Delete a database
    Route::delete('databases/{database}', [ClientDatabaseController::class, 'destroy']);
});
