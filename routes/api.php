<?php

use App\Http\Controllers\RdsProvisioningController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/rds-instances', [RdsProvisioningController::class, 'store']);
Route::get('/rds-instances/{id}', [RdsProvisioningController::class, 'show']);
Route::get('/rds-instances', [RdsProvisioningController::class, 'index']);
