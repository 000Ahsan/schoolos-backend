<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;

class StudentController extends Controller
{
    public function index(Request $request) {
        $query = Student::with('class');
        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('roll_no', 'like', '%' . $request->search . '%');
            });
        }

        // Add balance sum: Total Charged - Total Paid
        $query->withSum(['invoices as total_charged' => function($q) {
            // Include all invoices except waived to get a true lifetime charge
            $q->where('status', '!=', 'waived');
        }], 'net_amount');

        $query->withSum('allocations as total_paid', 'allocated_amount');

        $students = $query->get();
        $students->transform(function($student) {
            // Calculate balance dynamically
            $student->balance = (float) ($student->total_charged ?? 0) - (float) ($student->total_paid ?? 0);
            
            if ($student->photo_path) {
                $student->photo_url = asset('storage/' . $student->photo_path);
            }
            return $student;
        });
        return response()->json($students);
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'roll_no' => 'required|string|unique:students,roll_no',
            'name' => 'required|string',
            'father_name' => 'required|string',
            'class_id' => 'required|exists:classes,id',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'admission_date' => 'required|date',
            'guardian_name' => 'required|string',
            'guardian_relation' => 'required|string',
            'guardian_phone' => ['required', 'regex:/^\+92[0-9]{10}$/'],
            'guardian_cnic' => 'nullable|string',
            'b_form_no' => 'nullable|string',
            'emergency_contact' => ['nullable', 'regex:/^\+92[0-9]{10}$/'],
            'address' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'status' => 'nullable|in:active,left,graduated,suspended'
        ]);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('students', 'public');
            $validated['photo_path'] = $path;
        }
        unset($validated['photo']);

        $student = Student::create($validated);
        return response()->json($student, 201);
    }

    public function show($id) {
        $student = Student::with(['discounts' => function ($q) {
            $q->where('is_active', 1);
        }, 'invoices'])
        ->withSum(['invoices as total_charged' => function($q) {
            $q->where('status', '!=', 'waived');
        }], 'net_amount')
        ->withSum('allocations as total_paid', 'allocated_amount')
        ->findOrFail($id);
        
        $student->balance = (float)($student->total_charged ?? 0) - (float)($student->total_paid ?? 0);

        // Append full URL if photo exists
        if ($student->photo_path) {
            $student->photo_url = asset('storage/' . $student->photo_path);
        }
        
        return response()->json($student);
    }

    public function update(Request $request, $id) {
        $student = Student::findOrFail($id);
        
        $validated = $request->validate([
            'roll_no' => 'nullable|string|unique:students,roll_no,' . $id,
            'name' => 'nullable|string',
            'father_name' => 'nullable|string',
            'class_id' => 'nullable|exists:classes,id',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'admission_date' => 'nullable|date',
            'guardian_name' => 'nullable|string',
            'guardian_relation' => 'nullable|string',
            'guardian_phone' => ['nullable', 'regex:/^\+92[0-9]{10}$/'],
            'guardian_cnic' => 'nullable|string',
            'b_form_no' => 'nullable|string',
            'emergency_contact' => ['nullable', 'regex:/^\+92[0-9]{10}$/'],
            'address' => 'nullable|string',
            'photo' => 'nullable|image|max:2048',
            'status' => 'nullable|in:active,left,graduated,suspended'
        ]);

        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($student->photo_path) {
                \Storage::disk('public')->delete($student->photo_path);
            }
            $path = $request->file('photo')->store('students', 'public');
            $validated['photo_path'] = $path;
        }
        unset($validated['photo']);

        $student->update($validated);
        return response()->json($student);
    }

    public function destroy($id) {
        $student = Student::findOrFail($id);
        $student->delete();
        return response()->json(null, 204);
    }
}
