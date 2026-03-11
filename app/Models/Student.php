<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'student_number',
        'email',
        'section',
        'qr_token',
        'schedule',
    ];

    protected $casts = [
        'schedule' => 'array',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }
}

