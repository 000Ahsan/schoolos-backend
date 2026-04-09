<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FeeInvoice extends Model {
    protected $guarded = [];
    protected $casts = [
        'discount_breakdown' => 'array',
        'due_date' => 'date',
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }
    
    public function academicYear() {
        return $this->belongsTo(AcademicYear::class);
    }
    
    public function payments() {
        return $this->hasMany(FeePayment::class, 'invoice_id');
    }
}
