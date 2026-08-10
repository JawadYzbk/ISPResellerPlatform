<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_otp_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_normalized', 32)->nullable();
            $table->char('phone_hash', 64);
            $table->char('code_hash', 64);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('request_ip', 64)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'phone_hash', 'created_at']);
        });

        Schema::create('portal_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_sessions');
        Schema::dropIfExists('portal_otp_challenges');
    }
};
