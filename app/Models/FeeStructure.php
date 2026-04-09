<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model {
    protected $guarded = [];

    public function class() {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function academicYear() {
        return $this->belongsTo(AcademicYear::class);
    }
}
