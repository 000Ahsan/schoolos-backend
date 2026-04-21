<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FeeInvoice extends Model {
    protected $guarded = [];
    protected $casts = [
        'discount_breakdown' => 'array',
        'additional_charges_breakdown' => 'array',
        'due_date' => 'date',
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }
    
    public function academicYear() {
        return $this->belongsTo(AcademicYear::class);
    }
    
    public function allocations() {
        return $this->hasMany(FeePaymentAllocation::class, 'invoice_id');
    }

    public function payments() {
        return $this->hasManyThrough(FeePayment::class, FeePaymentAllocation::class, 'invoice_id', 'id', 'id', 'payment_id');
    }
}
