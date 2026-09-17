<?php

namespace App\Http\Controllers;

use App\Models\Signatory;
use Illuminate\Http\Request;

class SignatoryController extends Controller
{
    public function index()
    {
        return response()->json(Signatory::orderBy('id')->get());
    }

    public function update(Request $request, $id)
    {
        $signatory = Signatory::findOrFail($id);

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'position' => 'required|string|max:255',
        ]);

        $signatory->update($validated);

        return response()->json([
            'message'   => 'Signatory updated successfully.',
            'signatory' => $signatory,
        ]);
    }
}
