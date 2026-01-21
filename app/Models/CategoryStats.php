<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryStats extends Model
{
    protected $fillable = [
        'category',
        'total_implemented',
        'total_successful',
        'success_rate',
    ];

    protected $casts = [
        'total_implemented' => 'integer',
        'total_successful' => 'integer',
        'success_rate' => 'decimal:2',
    ];
}
