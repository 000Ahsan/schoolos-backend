<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Classes extends Model {
    protected $table = 'classes';
    protected $guarded = [];

    public function students() {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function feeStructures() {
        return $this->hasMany(FeeStructure::class, 'class_id');
    }
}
