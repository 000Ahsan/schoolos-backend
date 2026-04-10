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

        $today = now()->toDateString();
        $is_today_in_range = ($today >= $validated['start_date'] && $today <= $validated['end_date']);

        if ($is_today_in_range) {
            AcademicYear::where('is_current', 1)->update(['is_current' => 0]);
            $validated['is_current'] = 1;
        }

        $year = AcademicYear::create($validated);
        return response()->json($year, 201);
    }

    public function update(Request $request, $id) {
        $year = AcademicYear::findOrFail($id);
        $validated = $request->validate([
            'label' => 'nullable|string|unique:academic_years,label,' . $id,
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $startDate = $validated['start_date'] ?? $year->start_date;
        $endDate = $validated['end_date'] ?? $year->end_date;
        $today = now()->toDateString();

        if ($today >= $startDate && $today <= $endDate) {
            AcademicYear::where('is_current', 1)->update(['is_current' => 0]);
            $validated['is_current'] = 1;
        }

        $year->update($validated);
        return response()->json($year);
    }

    public function destroy($id) {
        $year = AcademicYear::findOrFail($id);
        // Maybe check if it's referenced in fee invoices? But usually schools delete demo data.
        $year->delete();
        return response()->json(null, 204);
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
