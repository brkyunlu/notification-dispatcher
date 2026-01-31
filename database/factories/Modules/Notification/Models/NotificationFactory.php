<?php

namespace Database\Factories\Modules\Notification\Models;

use App\Modules\Notification\Models\Notification;
use App\Shared\Enums\Channel;
use App\Shared\Enums\Priority;
use App\Shared\Enums\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Notification factory for testing
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'recipient' => fake()->email(),
            'channel' => fake()->randomElement(Channel::cases()),
            'content' => fake()->sentence(),
            'subject' => fake()->words(3, true),
            'priority' => fake()->randomElement(Priority::cases()),
            'status' => Status::PENDING,
            'metadata' => [],
        ];
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => Channel::EMAIL,
            'recipient' => fake()->email(),
        ]);
    }

    public function sms(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => Channel::SMS,
            'recipient' => fake()->phoneNumber(),
        ]);
    }

    public function push(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => Channel::PUSH,
            'recipient' => 'device-token-' . fake()->uuid(),
        ]);
    }

    public function high(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Priority::HIGH,
        ]);
    }

    public function normal(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Priority::NORMAL,
        ]);
    }

    public function low(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Priority::LOW,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::PENDING,
        ]);
    }

    public function queued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::QUEUED,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::SENT,
            'sent_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::FAILED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Status::CANCELLED,
        ]);
    }

    public function scheduled(?string $time = null): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_at' => $time ? now()->parse($time) : now()->addHour(),
            'status' => Status::PENDING,
        ]);
    }

    public function withBatch(string $batchId): static
    {
        return $this->state(fn (array $attributes) => [
            'batch_id' => $batchId,
        ]);
    }
}
