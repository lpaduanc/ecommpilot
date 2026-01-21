<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailureCase extends Model
{
    protected $fillable = [
        'store_id',
        'category',
        'title',
        'failure_reason',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
