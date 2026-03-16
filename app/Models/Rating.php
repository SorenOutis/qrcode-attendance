<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'score',
        'message',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'score' => 'integer',
    ];
}
