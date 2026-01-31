<?php

namespace App\Modules\Auth\Commands;

use App\Modules\Auth\Models\ApiKey;
use Illuminate\Console\Command;

class GenerateApiKeyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:generate-key 
                            {name : The name/description for this API key}
                            {--permissions=read,write : Comma-separated permissions (read, write, admin)}
                            {--expires= : Expiration date (e.g., "30 days", "2026-12-31")}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new API key for authentication';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $permissionsInput = $this->option('permissions');
        $expiresInput = $this->option('expires');

        // Parse permissions
        $permissions = array_map('trim', explode(',', $permissionsInput));
        $validPermissions = [ApiKey::PERMISSION_READ, ApiKey::PERMISSION_WRITE, ApiKey::PERMISSION_ADMIN];
        
        foreach ($permissions as $permission) {
            if (!in_array($permission, $validPermissions)) {
                $this->error("Invalid permission: {$permission}");
                $this->info('Valid permissions: ' . implode(', ', $validPermissions));
                return self::FAILURE;
            }
        }

        // Parse expiration
        $expiresAt = null;
        if ($expiresInput) {
            try {
                $expiresAt = now()->parse($expiresInput);
                if ($expiresAt->isPast()) {
                    $this->error('Expiration date must be in the future.');
                    return self::FAILURE;
                }
            } catch (\Exception $e) {
                $this->error("Invalid expiration format: {$expiresInput}");
                $this->info('Examples: "30 days", "1 year", "2026-12-31"');
                return self::FAILURE;
            }
        }

        // Generate key
        $key = ApiKey::generateKey();

        // Create API key record
        $apiKey = ApiKey::create([
            'name' => $name,
            'key' => $key,
            'permissions' => $permissions,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        $this->newLine();
        $this->info('API key generated successfully!');
        $this->newLine();
        
        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $apiKey->id],
                ['Name', $apiKey->name],
                ['Permissions', implode(', ', $apiKey->permissions)],
                ['Expires', $expiresAt ? $expiresAt->toDateTimeString() : 'Never'],
                ['Created', $apiKey->created_at->toDateTimeString()],
            ]
        );

        $this->newLine();
        $this->warn('⚠️  IMPORTANT: Save this API key securely. It will not be shown again!');
        $this->newLine();
        $this->line("API Key: <fg=green;options=bold>{$key}</>");
        $this->newLine();
        
        $this->info('Usage examples:');
        $this->line("  curl -H \"Authorization: Bearer {$key}\" http://localhost:8000/api/v1/notifications");
        $this->line("  curl -H \"X-API-Key: {$key}\" http://localhost:8000/api/v1/notifications");

        return self::SUCCESS;
    }
}
