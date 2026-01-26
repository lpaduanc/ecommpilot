<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'suggestion_id',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(Suggestion::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function latestMessages(int $limit = 10): HasMany
    {
        return $this->messages()
            ->orderBy('created_at', 'desc')
            ->limit($limit);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function close(): void
    {
        $this->update(['status' => 'closed']);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
