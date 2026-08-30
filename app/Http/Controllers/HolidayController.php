<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Holiday;

class HolidayController extends Controller
{
    // public function index(Request $request)
    // {
    //     $holidays = Holiday::orderBy('date')->get();
    //     return response()->json($holidays);
    // }
    public function index(Request $request)
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2020|max:2099',
        ]);

        $holidays = Holiday::query()
            ->when($request->filled('year'), function ($q) use ($request) {
                // A recurring holiday applies to every year — the year in its
                // stored date only records when HR first entered it, so it
                // must not be filtered out by that date.
                $q->where(function ($sub) use ($request) {
                    $sub->where('is_recurring', true)
                        ->orWhereYear('date', $request->year);
                });
            })
            ->orderByRaw('DATE_FORMAT(date, "%m-%d")')
            ->get();

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
