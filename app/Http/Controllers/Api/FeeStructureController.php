<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FeeStructure;

class FeeStructureController extends Controller {
    public function index(Request $request) {
        $query = FeeStructure::query();
        if ($request->has('class_id')) $query->where('class_id', $request->class_id);
        if ($request->has('academic_year_id')) $query->where('academic_year_id', $request->academic_year_id);
        return response()->json($query->get());
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'fee_head' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'frequency' => 'nullable|in:monthly,quarterly,annually,one_time',
            'is_active' => 'nullable|boolean'
        ]);

        $feeStructure = FeeStructure::create($validated);
        return response()->json($feeStructure, 201);
    }

    public function update(Request $request, $id) {
        $feeStructure = FeeStructure::findOrFail($id);
        
        $validated = $request->validate([
            'fee_head' => 'nullable|string|max:100',
            'amount' => 'nullable|numeric|min:0',
            'frequency' => 'nullable|in:monthly,quarterly,annually,one_time',
            'is_active' => 'nullable|boolean'
        ]);

        $feeStructure->update($validated);
        return response()->json($feeStructure);
    }
}
