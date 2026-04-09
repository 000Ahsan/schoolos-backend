<?php
namespace App\Observers;

use App\Models\FeePayment;
use App\Services\WhatsAppService;

class FeePaymentObserver {
    public function created(FeePayment $payment) {
        $invoice = $payment->invoice;
        $student = $invoice->student;
        $class = $student->class;
        $school = \App\Models\SchoolSetting::first();
        
        $balance = $invoice->balance; // It's updated in FeeService before observer if we keep logic there, OR we move logic here. The prompt said "FeePaymentObserver.php (updates invoice balance and status after payment)". But FeeService also says "Update fee_invoices: amount_paid += payment_amount..."
        // Since my FeeService already updates it in the same transaction, I will just dispatch WhatsApp from here.
        
        $message = "Dear {$student->parent_name},\n\nPayment received for {$student->name} ({$class->name}).\n\nAmount Paid : PKR {$payment->amount_paid}\nReceipt No  : {$payment->receipt_no}\nDate        : {$payment->payment_date}\nRemaining   : PKR {$invoice->balance}\n\nThank you.\n-- {$school->school_name}";
        
        app(WhatsAppService::class)->sendReceipt($student, $message);
    }
}
