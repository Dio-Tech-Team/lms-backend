<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Holiday;

class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $holidays = Holiday::orderBy('date')->get();
        return response()->json($holidays);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'         => 'required|date|unique:holidays,date',
            'name'         => 'required|string|max:255',
            'is_recurring' => 'nullable|boolean',
        ]);

        $holiday = Holiday::create($validated);

        return response()->json($holiday, 201);
    }
    public function update(Request $request, $id)
    {
        $holiday = Holiday::findOrFail($id);

        $validated = $request->validate([
            'date'         => 'required|date|unique:holidays,date,' . $holiday->id,
            'name'         => 'required|string|max:255',
            'is_recurring' => 'nullable|boolean',
        ]);

        $holiday->update($validated);

        return response()->json($holiday);
    }

    public function destroy($id)
    {
        $holiday = Holiday::findOrFail($id);
        $holiday->delete();

        return response()->json(['message' => 'Holiday deleted successfully']);
    }
}
