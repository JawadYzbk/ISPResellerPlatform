<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->string('path', 512);
            $table->unsignedInteger('depth')->default(0);
            $table->string('status')->default('active');
            $table->char('currency', 3);
            $table->unsignedBigInteger('credit_limit')->default(0);
            $table->unsignedBigInteger('low_balance_threshold')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'path']);
        });

        Schema::create('partner_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->bigInteger('balance_amount')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'partner_id', 'currency']);
        });

        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('partner_wallets')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->string('direction', 8);
            $table->unsignedBigInteger('amount');
            $table->bigInteger('balance_after');
            $table->string('idempotency_key', 120);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('partner_wallets');
        Schema::dropIfExists('partners');
    }
};
