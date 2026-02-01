<?php

namespace Database\Seeders;

use App\Modules\Template\Models\Template;
use Illuminate\Database\Seeder;

/**
 * Seeds demo templates for Postman collection (List Templates, Get Template, Create Notification With Template).
 */
class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Welcome Email',
                'channel' => 'email',
                'subject' => 'Welcome {{user_name}}!',
                'content' => 'Hello {{user_name}}, welcome. Account: {{account_id}}',
                'description' => 'Welcome email',
                'is_active' => true,
            ],
            [
                'name' => 'SMS Alert',
                'channel' => 'sms',
                'subject' => null,
                'content' => 'Alert: {{message}}. Code: {{code}}',
                'description' => 'SMS alert template',
                'is_active' => true,
            ],
            [
                'name' => 'Order Shipped',
                'channel' => 'email',
                'subject' => 'Order {{order_id}} shipped',
                'content' => 'Hi {{customer_name}}, your order {{order_id}} has been shipped. Track: {{tracking_url}}',
                'description' => 'Order shipped notification',
                'is_active' => true,
            ],
        ];

        foreach ($templates as $data) {
            Template::create($data);
        }
    }
}
