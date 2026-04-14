<?php
namespace App\Services;

use App\Jobs\SendWhatsAppJob;
use App\Models\Student;
use App\Models\WhatsAppLog;

class WhatsAppService {
    public function sendReminder(Student $student, string $messageBody) {
        $phone = $student->guardian_phone;
        if (!$phone) throw new \Exception("Guardian phone number is missing for student: {$student->name}");
        
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
        $phone = $student->guardian_phone;
        if (!$phone) throw new \Exception("Guardian phone number is missing for student: {$student->name}");
        
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

    public function sendVoucherDetails(Student $student, \App\Models\FeeInvoice $invoice) {
        $phone = $student->guardian_phone;
        if (!$phone) throw new \Exception("Guardian phone number is missing for student: {$student->name}");

        $schoolName = \App\Models\SchoolSetting::first()?->school_name ?? 'SchoolOS';

        $messageBody = "*{$schoolName}*\n\n" .
                       "*Fee Voucher Reminder*\n\n" .
                       "Dear Parent,\n" .
                       "Fee voucher for *{$student->name}* for the month of *{$invoice->month}/{$invoice->year}* has been issued.\n\n" .
                       "*Voucher No:* {$invoice->invoice_no}\n" .
                       "*Net Amount:* PKR " . number_format($invoice->net_amount) . "\n" .
                       "*Due Date:* " . \Carbon\Carbon::parse($invoice->due_date)->format('d M Y') . "\n\n" .
                       "Please ensure timely payment to avoid late fees. Thank you.";

        $log = WhatsAppLog::create([
            'recipient_phone' => $phone,
            'student_id' => $student->id,
            'message_type' => 'fee_voucher',
            'message_body' => $messageBody,
            'status' => 'queued',
        ]);
        
        SendWhatsAppJob::dispatch($log->id, $phone, $messageBody);
        return $log;
    }
}
