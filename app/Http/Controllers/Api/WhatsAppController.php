<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WhatsAppLog;
use App\Models\Student;
use App\Services\WhatsAppService;

class WhatsAppController extends Controller {
    protected $whatsappService;

    public function __construct(WhatsAppService $ws) {
        $this->whatsappService = $ws;
    }

    public function logs(Request $request) {
        $query = WhatsAppLog::query();
        if ($request->has('status')) $query->where('status', $request->status);
        if ($request->has('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->has('date_to')) $query->whereDate('created_at', '<=', $request->date_to);
        
        return response()->json($query->orderByDesc('created_at')->get());
    }

    public function reminder($studentId) {
        $student = Student::findOrFail($studentId);
        $message = "Dear {$student->parent_name}, this is a reminder regarding due fees for {$student->name}. Please clear them soon.";
        
        $log = $this->whatsappService->sendReminder($student, $message);
        return response()->json(['message' => 'Reminder queued', 'log' => $log]);
    }

    public function bulkReminder(Request $request) {
        $defaulters = Student::whereHas('invoices', function($q) {
            $q->where('balance', '>', 0);
        })->get();

        $count = 0;
        foreach ($defaulters as $student) {
            $message = "Dear {$student->parent_name}, this is an automated reminder regarding pending due fees for {$student->name}.";
            $this->whatsappService->sendReminder($student, $message);
            $count++;
        }

        return response()->json(['message' => "Bulk reminder queued for {$count} students."]);
    }
}
