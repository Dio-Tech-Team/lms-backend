<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveApplication;
use App\Models\LeaveCredit;

class DashboardController extends Controller
{
    private function isAdmin($user): bool
    {
        return $user && in_array($user->role, ['hr_admin', 'super_admin'], true);
    }

    public function leaveStats(Request $request)
    {
        $user = $request->user();
        if (!$this->isAdmin($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $year = $request->year ?? now()->year;

        $statusCounts = LeaveApplication::selectRaw('status, COUNT(*) as count')
            ->whereYear('applied_at', $year)
            ->groupBy('status')
            ->pluck('count', 'status');

        // $monthlyTrend = LeaveApplication::selectRaw("DATE_FORMAT(applied_at, '%Y-%m') as month, COUNT(*) as count")
        //     ->where('applied_at', '>=', now()->subMonths(5)->startOfMonth())
        //     ->groupBy('month')
        //     ->orderBy('month')
        //     ->get();
        $monthlyTrend = LeaveApplication::selectRaw("DATE_FORMAT(applied_at, '%Y-%m') as month, COUNT(*) as count")
            ->whereYear('applied_at', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $leaveTypeBreakdown = LeaveApplication::join('leave_configurations', 'leave_applications.leave_configuration_id', '=', 'leave_configurations.id')
            ->selectRaw('leave_configurations.code, leave_configurations.name, COUNT(*) as count')
            ->whereYear('leave_applications.applied_at', $year)
            ->groupBy('leave_configurations.code', 'leave_configurations.name')
            ->get();

        $lowCreditAlerts = LeaveCredit::with([
            'employee:id,first_name,surname,department_id',
            'employee.department:id,name',
            'leaveConfiguration:id,code,name',
        ])
            ->whereHas('leaveConfiguration', fn($q) => $q->whereIn('code', ['VL', 'SL']))
            ->where('year', $year)
            ->where('remaining_balance', '<', 3)
            ->orderBy('remaining_balance')
            ->limit(10)
            ->get();

        return response()->json([
            'pending_count'        => $statusCounts['pending'] ?? 0,
            'approved_count'       => $statusCounts['approved'] ?? 0,
            'cancelled_count'      => $statusCounts['cancelled'] ?? 0,
            'rejected_count'       => $statusCounts['rejected'] ?? 0,
            'monthly_trend'        => $monthlyTrend,
            'leave_type_breakdown' => $leaveTypeBreakdown,
            'low_credit_alerts'    => $lowCreditAlerts,
        ]);
    }
}
