<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuccessCase extends Model
{
    protected $fillable = [
        'store_id',
        'niche',
        'subcategory',
        'category',
        'title',
        'description',
        'implementation_details',
        'metrics_impact',
    ];

    protected $casts = [
        'metrics_impact' => 'array',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
