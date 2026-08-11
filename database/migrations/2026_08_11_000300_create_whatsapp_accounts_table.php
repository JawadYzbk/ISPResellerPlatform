<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80);
            $table->string('job', 64)->default('general');
            $table->string('bridge_id', 80)->unique();
            $table->string('status', 32)->default('disconnected');
            $table->string('phone', 32)->nullable();
            $table->string('push_name', 128)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_ready_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'is_active', 'job']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
