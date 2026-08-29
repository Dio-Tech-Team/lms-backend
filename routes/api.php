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
    ActivityLogController,
    DashboardController,
    LeaveMonetizationController,
    HolidayController,
    PositionController,
    ReportController
};

Route::middleware('throttle:5,1')->post('/login', [AuthController::class, 'login']);
Route::middleware('throttle:5,1')->post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::middleware('throttle:5,1')->post('/resend-otp', [AuthController::class, 'resendOtp']);

// 2. AUTHENTICATED ROUTES
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // --- Employee & Department Management ---
    // Open to all authenticated users — ownership/role filtering handled inside the controller
    Route::get('employees/stats', [EmployeeController::class, 'stats']);
    Route::get('employees/step-increment-forecast', [EmployeeController::class, 'stepIncrementForecast']);
    Route::get('employees/{id}/leave-card', [EmployeeController::class, 'leaveCard']);
    Route::get('/employees/{id}/leave-card/pdf', [EmployeeController::class, 'leaveCardPdf']);
    Route::get('employees', [EmployeeController::class, 'index']);
    Route::get('employees/{id}', [EmployeeController::class, 'show']);

    // Departments (READ) - open to everyone authenticated
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::get('departments/{id}', [DepartmentController::class, 'show']);


    Route::get('positions', [PositionController::class, 'index']);
    //mobile
    Route::get('/dashboard/balances', [LeaveCreditController::class, 'getLeaveCreditBalances']);

    // --- Leave Applications (General) ---
    Route::prefix('leave-applications')->group(function () {
        Route::get('/', [LeaveApplicationController::class, 'index']);
        Route::post('/', [LeaveApplicationController::class, 'store']);
        Route::get('/{id}', [LeaveApplicationController::class, 'show']);
        Route::get('/{id}/pdf', [LeaveApplicationController::class, 'generatePdf']);
        Route::post('/{id}/cancel', [LeaveApplicationController::class, 'cancel']);
    });


    Route::get('leave-configurations', [LeaveConfigurationController::class, 'index']);
    Route::get('leave-configurations/{id}', [LeaveConfigurationController::class, 'show']);

    Route::prefix('leave-monetizations')->group(function () {
        Route::get('/', [LeaveMonetizationController::class, 'index']);
        Route::post('/', [LeaveMonetizationController::class, 'store']);
        Route::post('/{id}/cancel', [LeaveMonetizationController::class, 'cancel']);
    });

    // 3. HR ADMIN ONLY ROUTES (Using your new Middleware)
    Route::middleware('role:super_admin,hr_admin')->group(function () {

        Route::put('employees/{id}', [EmployeeController::class, 'update']);
        Route::patch('employees/{id}', [EmployeeController::class, 'update']);
        Route::delete('employees/{id}', [EmployeeController::class, 'destroy']);

        Route::post('employees/{id}/resign', [EmployeeController::class, 'resign']);
        Route::post('employees/{id}/rehire', [EmployeeController::class, 'rehire']);
        Route::post('/employees/{id}/retire', [EmployeeController::class, 'retire']);
        // Leave Application Admin Actions
        Route::post('leave-applications/{id}/approve', [LeaveApplicationController::class, 'approve']);
        Route::post('leave-applications/{id}/reject', [LeaveApplicationController::class, 'reject']);

        // Leave Records (HR Management)
        Route::get('leave-records/summary', [LeaveRecordController::class, 'summary']);
        Route::apiResource('leave-records', LeaveRecordController::class)->except(['store']);
        // Route::apiResource('leave-records', LeaveRecordController::class);

        // Credit Admin
        Route::post('employees/leave-credits/initialize-all', [LeaveCreditController::class, 'initializeAllCredits']);
        Route::post('/leave-credits/grant', [LeaveCreditController::class, 'grantLeave']);

        // Attendance Admin
        Route::post('attendance/upload', [AttendanceController::class, 'upload']);
        Route::get('attendance/missing-check', [AttendanceController::class, 'checkMissingAttendance']);

        // --- Promotion History ---
        Route::prefix('employees/{employeeId}/promotions')->group(function () {
            Route::get('/', [EmploymentHistoryController::class, 'index']);
            Route::post('/', [EmploymentHistoryController::class, 'store']);
            Route::put('/{promotionId}', [EmploymentHistoryController::class, 'update']);
            Route::delete('/{promotionId}', [EmploymentHistoryController::class, 'destroy']);
        });

        Route::prefix('employees/{employeeId}/leave-credits')->group(function () {
            Route::get('/', [LeaveCreditController::class, 'index']);
            Route::post('/initialize', [LeaveCreditController::class, 'initializeCredits']);
            Route::put('/{creditId}', [LeaveCreditController::class, 'update']);
        });

        Route::get('/dashboard/leave-stats', [DashboardController::class, 'leaveStats']);
        // --- Attendance ---
        Route::get('attendance', [AttendanceController::class, 'index']);


        Route::post('leave-monetizations/{id}/approve', [LeaveMonetizationController::class, 'approve']);
        Route::post('leave-monetizations/{id}/reject', [LeaveMonetizationController::class, 'reject']);

        Route::prefix('reports')->group(function () {
            Route::get('/employee-masterlist', [ReportController::class, 'employeeMasterlist']);
            Route::get('/leave-balances', [ReportController::class, 'leaveBalances']);
            Route::get('/leave-utilization', [ReportController::class, 'leaveUtilization']);
        });
    });

    // 4. SUPER ADMIN ONLY ROUTES
    Route::middleware('role:super_admin')->group(function () {
        // Account Management
        Route::apiResource('users', UserController::class)->only(['index', 'store', 'destroy']);
        Route::post('employees', [EmployeeController::class, 'store']);

        Route::post('leave-configurations', [LeaveConfigurationController::class, 'store']);
        Route::put('leave-configurations/{id}', [LeaveConfigurationController::class, 'update']);
        Route::delete('leave-configurations/{id}', [LeaveConfigurationController::class, 'destroy']);
        Route::post('leave-configurations/{id}/reactivate', [LeaveConfigurationController::class, 'reactivate']);

        // NEW — Departments (WRITE)
        Route::post('departments', [DepartmentController::class, 'store']);
        Route::put('departments/{id}', [DepartmentController::class, 'update']);
        Route::delete('departments/{id}', [DepartmentController::class, 'destroy']);

        Route::get('/holidays', [HolidayController::class, 'index']);
        Route::post('/holidays', [HolidayController::class, 'store']);
        Route::put('/holidays/{id}', [HolidayController::class, 'update']);
        Route::delete('/holidays/{id}', [HolidayController::class, 'destroy']);

        Route::post('/positions', [PositionController::class, 'store']);
        Route::put('/positions/{id}', [PositionController::class, 'update']);

        // NEW — Activity Logs
        Route::get('/activity-logs/actions', [ActivityLogController::class, 'actions']);
        Route::get('activity-logs', [ActivityLogController::class, 'index']);
    });
});
