<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FeeStructure;

class FeeStructureController extends Controller {
    public function index(Request $request) {
        $query = FeeStructure::with(['class', 'academicYear']);
        
        if ($request->has('class_id')) $query->where('class_id', $request->class_id);
        if ($request->has('academic_year_id')) $query->where('academic_year_id', $request->academic_year_id);
        
        $structures = $query->get();

        // Group by fee_head, amount, academic_year_id, and frequency to match the frontend expectations of "many classes"
        $grouped = $structures->groupBy(function($item) {
            return $item->fee_head . $item->amount . $item->academic_year_id . $item->frequency;
        })->map(function($group) {
            $first = $group->first();
            return [
                'id' => $first->id,
                'fee_head_name' => $first->fee_head,
                'amount' => $first->amount,
                'period' => $first->frequency,
                'academic_year' => $first->academicYear,
                'academic_year_id' => $first->academic_year_id,
                'classes' => $group->map(function($item) {
                    return $item->class;
                })->filter()
            ];
        })->values();

        return response()->json($grouped);
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'classes' => 'required|array',
            'classes.*' => 'exists:classes,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'fee_head_name' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'period' => 'required|in:monthly,yearly,one_time',
            'is_active' => 'nullable|boolean'
        ]);

        $created = [];
        foreach ($validated['classes'] as $classId) {
            $created[] = FeeStructure::create([
                'class_id' => $classId,
                'academic_year_id' => $validated['academic_year_id'],
                'fee_head' => $validated['fee_head_name'],
                'amount' => $validated['amount'],
                'frequency' => $validated['period'],
                'is_active' => $validated['is_active'] ?? 1
            ]);
        }

        return response()->json($created[0], 201);
    }

    public function update(Request $request, $id) {
        $structure = FeeStructure::findOrFail($id);
        
        $validated = $request->validate([
            'classes' => 'required|array',
            'classes.*' => 'exists:classes,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'fee_head_name' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'period' => 'required|in:monthly,yearly,one_time',
            'is_active' => 'nullable|boolean'
        ]);

        // Find all records that belong to the SAME GROUP as the one being edited
        // We find by old values
        $currentGroup = FeeStructure::where([
            'academic_year_id' => $structure->academic_year_id,
            'fee_head' => $structure->fee_head,
            'amount' => $structure->amount,
            'frequency' => $structure->frequency
        ])->get();

        $existingClassIds = $currentGroup->pluck('class_id')->toArray();
        $newClassIds = $validated['classes'];

        // 1. Delete records for classes that are no longer selected
        $toDeleteIds = array_diff($existingClassIds, $newClassIds);
        if (!empty($toDeleteIds)) {
            FeeStructure::whereIn('class_id', $toDeleteIds)
                ->where([
                    'academic_year_id' => $structure->academic_year_id,
                    'fee_head' => $structure->fee_head,
                    'amount' => $structure->amount,
                    'frequency' => $structure->frequency
                ])->delete();
        }

        // 2. Update common fields for classes that are PRESERVED
        $toUpdateIds = array_intersect($existingClassIds, $newClassIds);
        if (!empty($toUpdateIds)) {
             FeeStructure::whereIn('class_id', $toUpdateIds)
                ->where([
                    'academic_year_id' => $structure->academic_year_id,
                    'fee_head' => $structure->fee_head,
                    'amount' => $structure->amount,
                    'frequency' => $structure->frequency
                ])->update([
                    'academic_year_id' => $validated['academic_year_id'],
                    'fee_head' => $validated['fee_head_name'],
                    'amount' => $validated['amount'],
                    'frequency' => $validated['period'],
                    'is_active' => $validated['is_active'] ?? 1
                ]);
        }

        // 3. Create records for classes that are NEWLY selected
        $toCreateIds = array_diff($newClassIds, $existingClassIds);
        foreach ($toCreateIds as $classId) {
            FeeStructure::create([
                'class_id' => $classId,
                'academic_year_id' => $validated['academic_year_id'],
                'fee_head' => $validated['fee_head_name'],
                'amount' => $validated['amount'],
                'frequency' => $validated['period'],
                'is_active' => $validated['is_active'] ?? 1
            ]);
        }

        return response()->json(['message' => 'Updated successfully']);
    }

    public function destroy($id) {
        $structure = FeeStructure::findOrFail($id);
        FeeStructure::where([
            'academic_year_id' => $structure->academic_year_id,
            'fee_head' => $structure->fee_head,
            'amount' => $structure->amount,
            'frequency' => $structure->frequency
        ])->delete();

        return response()->json(null, 204);
    }
}
