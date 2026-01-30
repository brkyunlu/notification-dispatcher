<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id')->nullable(); // For batch operations
            $table->string('recipient'); // Phone, email, or device token
            $table->enum('channel', ['sms', 'email', 'push']);
            $table->text('content');
            $table->string('subject')->nullable(); // For email channel
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->enum('status', ['pending', 'queued', 'processing', 'sent', 'delivered', 'failed', 'cancelled'])->default('pending');
            $table->uuid('template_id')->nullable();
            $table->timestamp('scheduled_at')->nullable(); // For scheduled notifications
            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->string('idempotency_key')->unique()->nullable();
            $table->string('external_message_id')->nullable(); // Provider's message ID
            $table->json('metadata')->nullable(); // Additional data
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('template_id')
                ->references('id')
                ->on('templates')
                ->onDelete('set null');
            
            // Indexes
            $table->index('batch_id');
            $table->index('channel');
            $table->index('status');
            $table->index('priority');
            $table->index('scheduled_at');
            $table->index('created_at');
            $table->index(['status', 'scheduled_at']); // Composite index for scheduled job
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
