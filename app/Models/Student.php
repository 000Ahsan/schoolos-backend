<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model {
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = [
        'date_of_birth' => 'date',
        'admission_date' => 'date',
    ];
    
    public function class() {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function discounts() {
        return $this->hasMany(StudentDiscount::class);
    }

    public function invoices() {
        return $this->hasMany(FeeInvoice::class);
    }

    public function promotions() {
        return $this->hasMany(StudentPromotion::class);
    }

    public function whatsappLogs() {
        return $this->hasMany(WhatsAppLog::class);
    }

    public function allocations() {
        return $this->hasManyThrough(FeePaymentAllocation::class, FeeInvoice::class, 'student_id', 'invoice_id', 'id', 'id');
    }
}
