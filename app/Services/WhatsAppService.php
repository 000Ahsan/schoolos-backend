<?php
namespace App\Services;

use App\Jobs\SendWhatsAppJob;
use App\Models\Student;
use App\Models\WhatsAppLog;

class WhatsAppService {
    public function sendReminder(Student $student, string $messageBody) {
        $phone = $student->parent_whatsapp ?? $student->parent_phone;
        
        $log = WhatsAppLog::create([
            'recipient_phone' => $phone,
            'student_id' => $student->id,
            'message_type' => 'fee_reminder',
            'message_body' => $messageBody,
            'status' => 'queued',
        ]);
        
        SendWhatsAppJob::dispatch($log->id, $phone, $messageBody);
        return $log;
    }
    
    public function sendReceipt(Student $student, string $messageBody) {
        $phone = $student->parent_whatsapp ?? $student->parent_phone;
        
        $log = WhatsAppLog::create([
            'recipient_phone' => $phone,
            'student_id' => $student->id,
            'message_type' => 'fee_receipt',
            'message_body' => $messageBody,
            'status' => 'queued',
        ]);
        
        SendWhatsAppJob::dispatch($log->id, $phone, $messageBody);
        return $log;
    }
}
