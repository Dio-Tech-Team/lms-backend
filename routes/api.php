<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmploymentHistoryController;



Route::post('/login', [AuthController::class, 'login']);
// ->middleware('throttle:login'); // Limit to 5 attempts per minute

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    //Employee Routes
    Route::apiResource('employees', EmployeeController::class);

    //Department Routes
    Route::apiResource('departments', DepartmentController::class);

    // Promotion History Routes
    Route::get('employees/{employeeId}/promotions', [EmploymentHistoryController::class, 'index']);
    Route::post('employees/{employeeId}/promotions', [EmploymentHistoryController::class, 'store']);
    Route::delete('employees/{employeeId}/promotions/{promotionId}', [EmploymentHistoryController::class, 'destroy']);
});
