<?php

use Illuminate\Support\Facades\Route;
// use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Http\Controllers\{
    AuthController,
    EmployeeController,
    DepartmentController,
    LeaveConfigurationController,
    LeaveCreditController,
    LeaveRecordController,
    LeaveApplicationController,
    EmploymentHistoryController,
    AttendanceController,
    UserController,
    ActivityLogController
};

Route::middleware('throttle:5,1')->post('/login', [AuthController::class, 'login']);
// Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
//     $request->fulfill();
//     return response()->json(['message' => 'Email verified successfully.']);
// })->middleware(['auth:sanctum', 'signed'])->name('verification.verify');
// Remove 'auth:sanctum' from this route
// Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
//     $request->fulfill();
//     return response()->json(['message' => 'Email verified successfully.']);
// })->middleware(['signed'])->name('verification.verify'); // <-- Removed 'auth:sanctum'

// 2. AUTHENTICATED ROUTES
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // --- Employee & Department Management ---
    Route::get('employees/stats', [EmployeeController::class, 'stats']);
    Route::get('employees/step-increment-forecast', [EmployeeController::class, 'stepIncrementForecast']);
    Route::get('employees/{id}/leave-card', [EmployeeController::class, 'leaveCard']);
    Route::apiResource('employees', EmployeeController::class);


    // Departments (READ) - open to everyone authenticated
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::get('departments/{id}', [DepartmentController::class, 'show']);

    //mobile
    Route::get('/dashboard/balances', [LeaveCreditController::class, 'getLeaveCreditBalances']);

    // --- Leave Applications (General) ---
    Route::prefix('leave-applications')->group(function () {
        Route::get('/', [LeaveApplicationController::class, 'index']);
        Route::post('/', [LeaveApplicationController::class, 'store']);
        Route::get('/{id}', [LeaveApplicationController::class, 'show']);
        Route::get('/{id}/pdf', [LeaveApplicationController::class, 'generatePdf']);
    });

    // --- Attendance ---
    Route::get('attendance', [AttendanceController::class, 'index']);

    Route::get('leave-configurations', [LeaveConfigurationController::class, 'index']);
    Route::get('leave-configurations/{id}', [LeaveConfigurationController::class, 'show']);

    // 3. HR ADMIN ONLY ROUTES (Using your new Middleware)
    Route::middleware('role:super_admin,hr_admin')->group(function () {
        // Leave Application Admin Actions
        Route::post('leave-applications/{id}/approve', [LeaveApplicationController::class, 'approve']);
        Route::post('leave-applications/{id}/reject', [LeaveApplicationController::class, 'reject']);
        Route::post('leave-applications/{id}/cancel', [LeaveApplicationController::class, 'cancel']);

        // Leave Records (HR Management)
        Route::get('leave-records/summary', [LeaveRecordController::class, 'summary']);
        Route::apiResource('leave-records', LeaveRecordController::class);

        // Credit Admin
        Route::post('employees/leave-credits/initialize-all', [LeaveCreditController::class, 'initializeAllCredits']);

        // Attendance Admin
        Route::post('attendance/upload', [AttendanceController::class, 'upload']);
        // --- Promotion History ---
        Route::prefix('employees/{employeeId}/promotions')->group(function () {
            Route::get('/', [EmploymentHistoryController::class, 'index']);
            Route::post('/', [EmploymentHistoryController::class, 'store']);
            Route::delete('/{promotionId}', [EmploymentHistoryController::class, 'destroy']);
        });

        Route::prefix('employees/{employeeId}/leave-credits')->group(function () {
            Route::get('/', [LeaveCreditController::class, 'index']);
            // Route::post('/initialize', [LeaveCreditController::class, 'initializeCredits']);
            Route::put('/{creditId}', [LeaveCreditController::class, 'update']);
        });
    });

    // 4. SUPER ADMIN ONLY ROUTES
    Route::middleware('role:super_admin')->group(function () {
        // Account Management
        Route::apiResource('users', UserController::class)->only(['index', 'store', 'destroy']);

        Route::post('leave-configurations', [LeaveConfigurationController::class, 'store']);
        Route::put('leave-configurations/{id}', [LeaveConfigurationController::class, 'update']);
        Route::delete('leave-configurations/{id}', [LeaveConfigurationController::class, 'destroy']);
        Route::post('leave-configurations/{id}/reactivate', [LeaveConfigurationController::class, 'reactivate']);

        // NEW — Departments (WRITE)
        Route::post('departments', [DepartmentController::class, 'store']);
        Route::put('departments/{id}', [DepartmentController::class, 'update']);
        Route::delete('departments/{id}', [DepartmentController::class, 'destroy']);

        // NEW — Activity Logs
        Route::get('activity-logs', [ActivityLogController::class, 'index']);
    });
});
