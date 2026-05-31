<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\PromotionHistoryController;



Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    //Employee Routes
    Route::apiResource('employees', EmployeeController::class);

    //Department Routes
    Route::apiResource('departments', DepartmentController::class);

    // Promotion History Routes
    Route::get('employees/{employeeId}/promotions', [PromotionHistoryController::class, 'index']);
    Route::post('employees/{employeeId}/promotions', [PromotionHistoryController::class, 'store']);
    Route::delete('employees/{employeeId}/promotions/{promotionId}', [PromotionHistoryController::class, 'destroy']);
});
