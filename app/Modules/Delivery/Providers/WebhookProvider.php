<?php

namespace App\Modules\Delivery\Providers;

use App\Modules\Delivery\Contracts\ProviderInterface;
use App\Modules\Notification\Models\Notification;
use App\Shared\Enums\Channel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookProvider implements ProviderInterface
{
    /**
     * Send notification via webhook
     *
     * @param Notification $notification
     * @return array
     * @throws \RuntimeException
     */
    public function send(Notification $notification): array
    {
        $webhookUrl = config('notification.webhook_url', 'https://webhook.site/unique-uuid');

        $payload = [
            'id' => $notification->id,
            'recipient' => $notification->recipient,
            'channel' => $notification->channel->value,
            'content' => $notification->content,
            'subject' => $notification->subject,
            'priority' => $notification->priority->value,
            'metadata' => $notification->metadata,
        ];

        Log::info('Sending to webhook provider', [
            'provider' => $this->getName(),
            'notification_id' => $notification->id,
            'channel' => $notification->channel->value,
            'url' => $webhookUrl,
        ]);

        // Get HTTP settings from config
        $timeout = config('notification.provider.timeout', 30);
        $retryAttempts = config('notification.provider.retry_attempts', 2);
        $retryDelay = config('notification.provider.retry_delay', 100);

        try {
            $response = Http::timeout($timeout)
                ->retry($retryAttempts, $retryDelay)
                ->post($webhookUrl, $payload);

            if (!$response->successful()) {
                throw new \RuntimeException(
                    "Webhook provider returned error: {$response->status()}",
                    $response->status()
                );
            }

            return [
                'message_id' => $notification->id,
                'status' => 'sent',
                'provider' => $this->getName(),
                'response_code' => $response->status(),
            ];

        } catch (\Exception $e) {
            Log::error('Webhook provider failed', [
                'provider' => $this->getName(),
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "Webhook provider failed: {$e->getMessage()}",
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Get provider name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'webhook';
    }

    /**
     * Check if provider supports given channel
     *
     * @param string $channel
     * @return bool
     */
    public function supportsChannel(string $channel): bool
    {
        // Webhook provider supports all channels (for testing)
        return in_array($channel, [
            Channel::SMS->value,
            Channel::EMAIL->value,
            Channel::PUSH->value,
        ]);
    }
}
