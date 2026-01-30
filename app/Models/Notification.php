<?php

namespace App\Models;

use App\Enums\Channel;
use App\Enums\Priority;
use App\Enums\Status;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'batch_id',
        'recipient',
        'channel',
        'content',
        'subject',
        'priority',
        'status',
        'template_id',
        'scheduled_at',
        'sent_at',
        'attempts',
        'last_error',
        'idempotency_key',
        'external_message_id',
        'metadata',
    ];

    protected $casts = [
        'channel' => Channel::class,
        'priority' => Priority::class,
        'status' => Status::class,
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the template used for this notification
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Get failed notification records
     */
    public function failures(): HasMany
    {
        return $this->hasMany(FailedNotification::class);
    }

    /**
     * Scope for pending notifications
     */
    public function scopePending($query)
    {
        return $query->where('status', Status::PENDING);
    }

    /**
     * Scope for scheduled notifications ready to send
     */
    public function scopeScheduledReady($query)
    {
        return $query->where('status', Status::PENDING)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    /**
     * Scope for specific channel
     */
    public function scopeByChannel($query, Channel $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope for specific batch
     */
    public function scopeByBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    /**
     * Mark notification as queued
     */
    public function markAsQueued(): void
    {
        $this->update(['status' => Status::QUEUED]);
    }

    /**
     * Mark notification as processing
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => Status::PROCESSING]);
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent(string $externalMessageId = null): void
    {
        $this->update([
            'status' => Status::SENT,
            'sent_at' => now(),
            'external_message_id' => $externalMessageId,
        ]);
    }

    /**
     * Mark notification as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update(['status' => Status::DELIVERED]);
    }

    /**
     * Mark notification as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => Status::FAILED,
            'last_error' => $error,
        ]);
    }

    /**
     * Mark notification as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update(['status' => Status::CANCELLED]);
    }

    /**
     * Increment attempt counter
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Check if notification can be retried
     */
    public function canRetry(): bool
    {
        return $this->attempts < 5 && !$this->status->isFinal();
    }

    /**
     * Get masked recipient for logging
     */
    public function getMaskedRecipientAttribute(): string
    {
        $recipient = $this->recipient;
        
        return match ($this->channel) {
            Channel::EMAIL => preg_replace('/(?<=.).(?=.*@)/u', '*', $recipient),
            Channel::SMS => substr($recipient, 0, 3) . str_repeat('*', strlen($recipient) - 6) . substr($recipient, -3),
            Channel::PUSH => substr($recipient, 0, 8) . '...' . substr($recipient, -8),
        };
    }
}
