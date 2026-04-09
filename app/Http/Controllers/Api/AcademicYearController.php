<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AcademicYear;
use App\Services\PromotionService;

class AcademicYearController extends Controller {
    public function index() {
        return response()->json(AcademicYear::orderByDesc('start_date')->get());
    }
    public function store(Request $request) {
        $validated = $request->validate([
            'label' => 'required|string|unique:academic_years,label',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);
        return response()->json(AcademicYear::create($validated), 201);
    }
    public function setCurrent($id) {
        AcademicYear::where('is_current', 1)->update(['is_current' => 0]);
        $year = AcademicYear::findOrFail($id);
        $year->update(['is_current' => 1]);
        return response()->json($year);
    }
    public function promoteStudents(Request $request, $id, PromotionService $ps) {
        try {
            $result = $ps->promoteAll($request->user()->id, $id);
            return response()->json(['message' => "Promoted {$result['count']} students successfully."]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
