<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeePaymentAllocation extends Model
{
    protected $guarded = [];

    public function payment()
    {
        return $this->belongsTo(FeePayment::class, 'payment_id');
    }

    public function invoice()
    {
        return $this->belongsTo(FeeInvoice::class, 'invoice_id');
    }
}
