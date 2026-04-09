<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FeePayment extends Model {
    protected $guarded = [];
    protected $casts = [
        'payment_date' => 'date',
    ];

    public function invoice() {
        return $this->belongsTo(FeeInvoice::class, 'invoice_id');
    }

    public function receiver() {
        return $this->belongsTo(User::class, 'received_by');
    }
}
