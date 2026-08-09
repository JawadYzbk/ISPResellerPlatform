<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('username', 64);
            $table->text('password_encrypted')->nullable();
            $table->string('status')->default('pending');
            $table->string('provisioning_mode')->default('manual');
            $table->string('network_state')->default('unknown');
            $table->unsignedBigInteger('desired_state_version')->default(1);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'username']);
            $table->index(['tenant_id', 'status', 'expires_at']);
            $table->index(['tenant_id', 'network_state']);
        });

        Schema::create('service_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'service_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_events');
        Schema::dropIfExists('services');
    }
};
