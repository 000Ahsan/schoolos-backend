<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;

class StudentController extends Controller
{
    public function index(Request $request) {
        $query = Student::query();
        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('roll_no', 'like', '%' . $request->search . '%');
        }
        return response()->json($query->get());
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'roll_no' => 'required|string|unique:students,roll_no',
            'name' => 'required|string',
            'father_name' => 'required|string',
            'class_id' => 'required|exists:classes,id',
            'admission_date' => 'required|date',
            'parent_phone' => ['required', 'regex:/^\+92[0-9]{10}$/'],
            'parent_whatsapp' => ['nullable', 'regex:/^\+92[0-9]{10}$/'],
            'status' => 'nullable|in:active,left,graduated,suspended'
        ]);

        $student = Student::create($validated);
        return response()->json($student, 201);
    }

    public function show($id) {
        $student = Student::with(['discounts' => function ($q) {
            $q->where('is_active', 1);
        }, 'invoices'])->findOrFail($id);
        return response()->json($student);
    }

    public function update(Request $request, $id) {
        $student = Student::findOrFail($id);
        
        $validated = $request->validate([
            'roll_no' => 'nullable|string|unique:students,roll_no,' . $id,
            'name' => 'nullable|string',
            'father_name' => 'nullable|string',
            'class_id' => 'nullable|exists:classes,id',
            'parent_phone' => ['nullable', 'regex:/^\+92[0-9]{10}$/'],
            'parent_whatsapp' => ['nullable', 'regex:/^\+92[0-9]{10}$/'],
            'status' => 'nullable|in:active,left,graduated,suspended'
        ]);

        $student->update($validated);
        return response()->json($student);
    }

    public function destroy($id) {
        $student = Student::findOrFail($id);
        $student->delete();
        return response()->json(null, 204);
    }
}
