<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::select(['id', 'user_id', 'action', 'description', 'subject_type', 'subject_id', 'created_at'])
            ->with('user:id,username,role')
            ->when($request->filled('action'), function ($query) use ($request) {
                $query->where('action', $request->action);
            })
            ->when($request->filled('role'), function ($query) use ($request) {
                $query->whereHas('user', fn($q) => $q->where('role', $request->role));
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($logs);
    }
    public function actions()
    {
        $actions = ActivityLog::select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return response()->json($actions);
    }
}
