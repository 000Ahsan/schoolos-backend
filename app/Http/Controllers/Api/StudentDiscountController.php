<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudentDiscount;

class StudentDiscountController extends Controller {
    public function index($studentId) {
        $discounts = StudentDiscount::where('student_id', $studentId)->get();
        return response()->json($discounts);
    }
    public function store(Request $request, $studentId) {
        $validated = $request->validate([
            'discount_name' => 'required|string|max:100',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'applies_to' => 'nullable|in:all,tuition_only,specific_head',
            'fee_head_name' => 'nullable|string|max:100',
            'valid_from' => 'required|date',
            'valid_until' => 'nullable|date',
            'remarks' => 'nullable|string'
        ]);
        $validated['student_id'] = $studentId;
        $validated['approved_by'] = $request->user()->id;
        
        $discount = StudentDiscount::create($validated);
        return response()->json($discount, 201);
    }
    public function update(Request $request, $studentId, $discountId) {
        $discount = StudentDiscount::where('student_id', $studentId)->findOrFail($discountId);
        $discount->update($request->only('is_active'));
        return response()->json($discount);
    }
    public function destroy($studentId, $discountId) {
        $discount = StudentDiscount::where('student_id', $studentId)->findOrFail($discountId);
        $discount->update(['is_active' => 0]);
        return response()->json(null, 204);
    }
}
