<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // WhatsApp messages log
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('whatsapp_id')->nullable(); // WhatsApp message ID
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('type', ['text', 'voice', 'image', 'document', 'location', 'button_reply']);
            $table->text('message')->nullable();
            $table->string('media_url')->nullable();
            $table->string('media_path')->nullable(); // Local storage path
            $table->string('media_mime_type')->nullable();
            $table->string('transcription')->nullable(); // For voice messages
            $table->enum('status', ['sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['order_id']);
            $table->index(['direction', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
