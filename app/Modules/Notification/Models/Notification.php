<?php

namespace App\Modules\Notification\Models;

use App\Modules\Notification\Events\NotificationFailed;
use App\Modules\Notification\Events\NotificationQueued;
use App\Modules\Notification\Events\NotificationSent;
use App\Modules\Template\Models\Template;
use App\Shared\Enums\Channel;
use App\Shared\Enums\Priority;
use App\Shared\Enums\Status;
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
        
        // Broadcast event
        broadcast(new NotificationQueued($this))->toOthers();
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
        
        // Broadcast event
        broadcast(new NotificationSent($this))->toOthers();
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
        
        // Broadcast event
        event(new NotificationFailed($this, $error));
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
     * 
     * A notification can be retried if:
     * - Attempts are less than max attempts
     * - Status is not CANCELLED or DELIVERED (these are truly final)
     * - FAILED status is allowed to retry (temporary failure)
     */
    public function canRetry(): bool
    {
        $maxAttempts = config('notification.retry.max_attempts', 5);
        
        // Cannot retry if max attempts reached
        if ($this->attempts >= $maxAttempts) {
            return false;
        }
        
        // Cannot retry CANCELLED or DELIVERED statuses
        $nonRetryableStatuses = [Status::CANCELLED, Status::DELIVERED];
        
        return !in_array($this->status, $nonRetryableStatuses, strict: true);
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
