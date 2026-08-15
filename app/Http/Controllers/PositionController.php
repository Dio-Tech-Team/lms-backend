<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Position;

class PositionController extends Controller
{
    public function index()
    {
        return response()->json(Position::orderBy('title')->get());
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'title' => 'required|string|unique:positions,title',
        ]);

        $position = Position::create(['title' => $request->title]);

        return response()->json($position, 201);
    }

    public function update(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $position = Position::findOrFail($id);

        $request->validate([
            'title' => 'required|string|unique:positions,title,' . $position->id,
        ]);

        $position->update(['title' => $request->title]);

        return response()->json($position);
    }
}
