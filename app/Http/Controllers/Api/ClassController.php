<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Classes;

class ClassController extends Controller
{
    public function index() {
        return response()->json(Classes::all());
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'section' => 'nullable|string|max:10',
            'numeric_order' => 'required|integer',
            'capacity' => 'nullable|integer',
            'is_active' => 'nullable|boolean'
        ]);

        $class = Classes::create($validated);
        return response()->json($class, 201);
    }
    
    public function show($id) {
        return response()->json(Classes::findOrFail($id));
    }

    public function update(Request $request, $id) {
        $validated = $request->validate([
            'name' => 'nullable|string|max:50',
            'section' => 'nullable|string|max:10',
            'numeric_order' => 'nullable|integer',
            'capacity' => 'nullable|integer',
            'is_active' => 'nullable|boolean'
        ]);

        $class = Classes::findOrFail($id);
        $class->update($validated);
        return response()->json($class);
    }

    public function destroy($id) {
        $class = Classes::findOrFail($id);
        $class->delete();
        return response()->json(null, 204);
    }
}
