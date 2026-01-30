<?php

namespace App\Models;

use App\Enums\Channel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedNotification extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'notification_id',
        'channel',
        'error_message',
        'http_status_code',
        'error_context',
        'failed_at',
    ];

    protected $casts = [
        'channel' => Channel::class,
        'error_context' => 'array',
        'failed_at' => 'datetime',
    ];

    /**
     * Get the notification that failed
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    /**
     * Scope for specific channel
     */
    public function scopeByChannel($query, Channel $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope for recent failures
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('failed_at', '>=', now()->subHours($hours));
    }
}
