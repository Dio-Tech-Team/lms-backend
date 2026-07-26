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
            ->when($request->filled('user_id'), function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($logs);
    }
}
