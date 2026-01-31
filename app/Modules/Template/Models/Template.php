<?php

namespace App\Modules\Template\Models;

use App\Modules\Notification\Models\Notification;
use App\Shared\Enums\Channel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Template extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'channel',
        'content',
        'subject',
        'variables',
        'description',
        'is_active',
    ];

    protected $casts = [
        'channel' => Channel::class,
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate slug from name
        static::creating(function ($template) {
            if (!$template->slug) {
                $template->slug = Str::slug($template->name);
            }
        });

        // Update slug if name changed
        static::updating(function ($template) {
            if ($template->isDirty('name') && !$template->isDirty('slug')) {
                $template->slug = Str::slug($template->name);
            }
        });
    }

    /**
     * Get notifications using this template
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Render template with variables
     */
    public function render(array $data = []): array
    {
        $content = $this->content;
        $subject = $this->subject;

        // Replace variables in content
        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $content = str_replace($placeholder, $value, $content);
            
            if ($subject) {
                $subject = str_replace($placeholder, $value, $subject);
            }
        }

        return [
            'content' => $content,
            'subject' => $subject,
        ];
    }

    /**
     * Extract variables from template content
     */
    public function extractVariables(): array
    {
        $pattern = '/\{\{([^}]+)\}\}/';
        $matches = [];
        
        preg_match_all($pattern, $this->content, $contentMatches);
        $matches = array_merge($matches, $contentMatches[1] ?? []);
        
        if ($this->subject) {
            preg_match_all($pattern, $this->subject, $subjectMatches);
            $matches = array_merge($matches, $subjectMatches[1] ?? []);
        }

        return array_unique(array_map('trim', $matches));
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific channel
     */
    public function scopeForChannel($query, Channel $channel)
    {
        return $query->where('channel', $channel);
    }
}
