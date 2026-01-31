<?php

namespace App\Modules\Delivery\Contracts;

use App\Modules\Notification\Models\Notification;

interface ProviderInterface
{
    /**
     * Send notification to provider
     *
     * @param Notification $notification
     * @return array Response with message_id and status
     * @throws \RuntimeException When provider fails
     */
    public function send(Notification $notification): array;

    /**
     * Get provider name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if provider supports given channel
     *
     * @param string $channel
     * @return bool
     */
    public function supportsChannel(string $channel): bool;
}
