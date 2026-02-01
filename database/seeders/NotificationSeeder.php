<?php

namespace Database\Seeders;

use App\Modules\Notification\Models\Notification;
use App\Modules\Template\Models\Template;
use App\Shared\Enums\Channel;
use App\Shared\Enums\Priority;
use App\Shared\Enums\Status;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds demo notifications for Postman collection (List, Get by ID, Stats, Batch Stats).
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $welcomeTemplate = Template::where('name', 'Welcome Email')->first();
        $templateId = $welcomeTemplate?->id;

        $batchId = (string) Str::uuid();

        // Sent (3–4): mix of email/sms
        Notification::factory()
            ->email()
            ->sent()
            ->create([
                'recipient' => 'user1@example.com',
                'content' => 'First notification',
                'subject' => 'Batch 1',
                'priority' => Priority::NORMAL,
            ]);
        Notification::factory()
            ->sms()
            ->sent()
            ->create([
                'recipient' => '+905551234567',
                'content' => 'Test notification',
                'subject' => null,
                'priority' => Priority::HIGH,
            ]);
        Notification::factory()
            ->email()
            ->sent()
            ->create([
                'recipient' => 'user2@example.com',
                'content' => 'Welcome message',
                'subject' => 'Welcome',
                'priority' => Priority::NORMAL,
                'template_id' => $templateId,
            ]);
        Notification::factory()
            ->sms()
            ->sent()
            ->create([
                'recipient' => '+905551111111',
                'content' => 'Second notification',
                'subject' => null,
                'priority' => Priority::HIGH,
            ]);

        // Pending (2)
        Notification::factory()
            ->email()
            ->pending()
            ->create([
                'recipient' => 'pending@example.com',
                'content' => 'Pending email',
                'priority' => Priority::NORMAL,
            ]);
        Notification::factory()
            ->sms()
            ->pending()
            ->create([
                'recipient' => '+905559999999',
                'content' => 'Pending SMS',
                'priority' => Priority::LOW,
            ]);

        // Failed (1–2)
        Notification::factory()
            ->email()
            ->failed()
            ->create([
                'recipient' => 'failed@example.com',
                'content' => 'Failed delivery',
                'priority' => Priority::HIGH,
                'last_error' => 'Connection timeout',
            ]);

        // Delivered (1)
        Notification::factory()
            ->email()
            ->create([
                'recipient' => 'delivered@example.com',
                'content' => 'Delivered notification',
                'subject' => 'Delivered',
                'channel' => Channel::EMAIL,
                'priority' => Priority::NORMAL,
                'status' => Status::DELIVERED,
            ]);

        // Queued (1)
        Notification::factory()
            ->queued()
            ->sms()
            ->create([
                'recipient' => '+905558888888',
                'content' => 'Queued SMS',
                'priority' => Priority::HIGH,
            ]);

        // Batch: 3 notifications with same batch_id (Get Batch Statistics)
        Notification::factory()
            ->email()
            ->sent()
            ->withBatch($batchId)
            ->create([
                'recipient' => 'batch1@example.com',
                'content' => 'Batch notification 1',
                'subject' => 'Batch',
                'priority' => Priority::NORMAL,
            ]);
        Notification::factory()
            ->sms()
            ->sent()
            ->withBatch($batchId)
            ->create([
                'recipient' => '+905557777777',
                'content' => 'Batch notification 2',
                'priority' => Priority::HIGH,
            ]);
        Notification::factory()
            ->email()
            ->pending()
            ->withBatch($batchId)
            ->create([
                'recipient' => 'batch2@example.com',
                'content' => 'Batch notification 3',
                'subject' => 'Batch',
                'priority' => Priority::NORMAL,
            ]);
    }
}
