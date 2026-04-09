<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class StudentPromotion extends Model {
    protected $guarded = [];
    const CREATED_AT = 'promoted_at';
    const UPDATED_AT = null;

    public function student() {
        return $this->belongsTo(Student::class);
    }

    public function academicYear() {
        return $this->belongsTo(AcademicYear::class);
    }

    public function fromClass() {
        return $this->belongsTo(Classes::class, 'from_class_id');
    }

    public function toClass() {
        return $this->belongsTo(Classes::class, 'to_class_id');
    }

    public function promoter() {
        return $this->belongsTo(User::class, 'promoted_by');
    }
}
