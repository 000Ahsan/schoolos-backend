<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model {
    protected $guarded = [];
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function feeStructures() {
        return $this->hasMany(FeeStructure::class);
    }

    public function invoices() {
        return $this->hasMany(FeeInvoice::class);
    }
}
