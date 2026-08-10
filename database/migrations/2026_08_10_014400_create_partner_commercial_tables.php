<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->cascadeOnDelete();
            $table->string('type', 16);
            $table->bigInteger('value');
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'partner_id', 'status', 'effective_from']);
            $table->index(['tenant_id', 'plan_id', 'zone_id', 'effective_from']);
        });

        Schema::create('price_books', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 16)->default('active');
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'partner_id', 'status', 'effective_from']);
        });

        Schema::create('price_book_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('commission_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->char('currency', 3);
            $table->unsignedBigInteger('buy_amount_minor');
            $table->unsignedBigInteger('sell_amount_minor');
            $table->unsignedBigInteger('min_amount_minor')->nullable();
            $table->unsignedBigInteger('max_amount_minor')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();
            $table->unique(['price_book_id', 'plan_id', 'currency', 'effective_from'], 'price_book_items_version_unique');
            $table->index(['tenant_id', 'plan_id', 'currency', 'effective_from']);
        });

        Schema::create('commission_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();
            $table->string('source_type');
            $table->string('source_id');
            $table->unsignedInteger('rule_version');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('accrued');
            $table->timestamps();
            $table->unique(['tenant_id', 'partner_id', 'source_type', 'source_id', 'rule_version'], 'commission_entries_source_unique');
            $table->index(['tenant_id', 'partner_id', 'status', 'created_at']);
        });

        Schema::create('settlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->char('currency', 3);
            $table->bigInteger('opening_amount');
            $table->bigInteger('activity_amount');
            $table->bigInteger('closing_amount');
            $table->bigInteger('due_amount');
            $table->string('status', 16)->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'partner_id', 'period_start', 'period_end', 'currency'], 'settlements_period_unique');
            $table->index(['tenant_id', 'partner_id', 'status', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('price_book_items');
        Schema::dropIfExists('price_books');
        Schema::dropIfExists('commission_rules');
    }
};
