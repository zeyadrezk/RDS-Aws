<?php

use App\Http\Controllers\ClientDatabaseController;
use App\Http\Controllers\RdsProvisioningController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});



Route::get('/databases', [ClientDatabaseController::class, 'index']);
Route::post('/databases', [ClientDatabaseController::class, 'store']);

Route::post('/rds-instances', [RdsProvisioningController::class, 'store']);
Route::get('/rds-instances/{id}', [RdsProvisioningController::class, 'show']);
