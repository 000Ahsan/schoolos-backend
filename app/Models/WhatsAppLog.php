<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class WhatsAppLog extends Model {
    protected $guarded = [];
    const UPDATED_AT = null;
    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function student() {
        return $this->belongsTo(Student::class);
    }
}
