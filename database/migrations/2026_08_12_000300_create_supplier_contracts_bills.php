<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_contracts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('service_type', 64);
            $table->json('terms')->nullable();
            $table->char('wholesale_currency', 3);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'supplier_id', 'status', 'effective_from']);
        });

        Schema::table('credential_batches', function (Blueprint $table): void {
            $table->foreignId('supplier_contract_id')->nullable()->after('supplier_id')->constrained('supplier_contracts')->nullOnDelete();
        });

        Schema::create('supplier_bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('reference', 64);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('status', 16)->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'supplier_id', 'reference']);
            $table->index(['tenant_id', 'supplier_id', 'status', 'period_end']);
        });

        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_bill_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->timestamp('paid_at');
            $table->string('method', 32);
            $table->string('reference', 128)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'supplier_bill_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_bills');
        Schema::table('credential_batches', function (Blueprint $table): void {
            $table->dropForeign(['supplier_contract_id']);
            $table->dropColumn('supplier_contract_id');
        });
        Schema::dropIfExists('supplier_contracts');
    }
};
