<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class StudentDiscount extends Model {
    protected $guarded = [];
    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function approver() {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
