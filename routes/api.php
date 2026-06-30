<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\LeaveConfigurationController;
use App\Http\Controllers\LeaveCreditController;
use App\Http\Controllers\LeaveRecordController;
use App\Http\Controllers\LeaveApplicationController;
use App\Http\Controllers\EmploymentHistoryController;
use App\Http\Controllers\AttendanceController;



Route::post('/login', [AuthController::class, 'login']);
// ->middleware('throttle:login'); // Limit to 5 attempts per minute
// Route::post('/test-upload', function () {
//     return [
//         'SERVER_CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? null,
//         'FILES_GLOBAL' => $_FILES,
//     ];
// });

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


    //Leave Configuration Routes
    // Route::get('leave-configurations', [LeaveConfigurationController::class, 'index']);
    // Route::post('leave-configurations', [LeaveConfigurationController::class, 'store']);
    // Route::get('leave-configurations/{id}', [LeaveConfigurationController::class, 'show']);
    // Route::put('leave-configurations/{id}', [LeaveConfigurationController::class, 'update']);
    // Route::delete('leave-configurations/{id}', [LeaveConfigurationController::class, 'destroy']); 
    Route::apiResource('leave-configurations', LeaveConfigurationController::class);

    // Leave Credit Routes
    Route::get('employees/{employeeId}/leave-credits', [LeaveCreditController::class, 'index']);
    Route::post('employees/{employeeId}/leave-credits/initialize', [LeaveCreditController::class, 'initializeCredits']);
    Route::put('employees/{employeeId}/leave-credits/{creditId}', [LeaveCreditController::class, 'update']);

    // Leave Record Routes
    // Leave Record Routes - HR can only view, update, delete
    Route::get('leave-records', [LeaveRecordController::class, 'index']);
    Route::get('leave-records/{id}', [LeaveRecordController::class, 'show']);
    Route::put('leave-records/{id}', [LeaveRecordController::class, 'update']);
    Route::delete('leave-records/{id}', [LeaveRecordController::class, 'destroy']);



    // Leave Application Routes
    Route::get('leave-applications', [LeaveApplicationController::class, 'index']);
    Route::post('leave-applications', [LeaveApplicationController::class, 'store']);
    Route::get('leave-applications/{id}', [LeaveApplicationController::class, 'show']);
    Route::post('leave-applications/{id}/approve', [LeaveApplicationController::class, 'approve']);
    Route::post('leave-applications/{id}/cancel', [LeaveApplicationController::class, 'cancel']);

    //Leave form
    Route::get('leave-applications/{id}/pdf', [LeaveApplicationController::class, 'generatePdf']);

    Route::get('employees/{employeeId}/promotions', [EmploymentHistoryController::class, 'index']);
    Route::post('employees/{employeeId}/promotions', [EmploymentHistoryController::class, 'store']);
    Route::delete('employees/{employeeId}/promotions/{promotionId}', [EmploymentHistoryController::class, 'destroy']);

    //attendance

    Route::post('attendance/upload', [AttendanceController::class, 'upload']);
});
