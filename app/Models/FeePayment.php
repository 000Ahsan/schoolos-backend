<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FeePayment extends Model {
    protected $guarded = [];
    protected $casts = [
        'payment_date' => 'date',
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function allocations() {
        return $this->hasMany(FeePaymentAllocation::class, 'payment_id');
    }

    public function receiver() {
        return $this->belongsTo(User::class, 'received_by');
    }
}
