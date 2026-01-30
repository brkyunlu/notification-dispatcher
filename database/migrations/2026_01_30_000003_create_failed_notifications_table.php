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
        Schema::create('failed_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_id');
            $table->enum('channel', ['sms', 'email', 'push']);
            $table->text('error_message');
            $table->unsignedSmallInteger('http_status_code')->nullable();
            $table->json('error_context')->nullable(); // Additional error details
            $table->timestamp('failed_at');
            
            // Foreign keys
            $table->foreign('notification_id')
                ->references('id')
                ->on('notifications')
                ->onDelete('cascade');
            
            // Indexes
            $table->index('notification_id');
            $table->index('channel');
            $table->index('failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('failed_notifications');
    }
};
