<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('category', 32);
            $table->string('normal_balance', 6);
            $table->char('currency', 3)->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('occurred_at');
            $table->string('description');
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'source_type', 'source_id']);
            $table->index(['tenant_id', 'occurred_at']);
        });

        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('debit_amount')->default(0);
            $table->unsignedBigInteger('credit_amount')->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'customer_id', 'currency']);
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('journal_line_id')->constrained()->restrictOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('debit_amount')->default(0);
            $table->unsignedBigInteger('credit_amount')->default(0);
            $table->bigInteger('balance_after');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique('journal_line_id');
            $table->index(['tenant_id', 'customer_id', 'occurred_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE journal_lines ADD CONSTRAINT journal_lines_one_sided_amount CHECK ((debit_amount > 0 AND credit_amount = 0) OR (credit_amount > 0 AND debit_amount = 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('ledger_accounts');
    }
};
