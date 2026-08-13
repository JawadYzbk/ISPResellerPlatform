<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_expense_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id');
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->string('frequency', 16);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->string('payment_source', 16);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->text('description');
            $table->string('reference', 120)->nullable();
            $table->date('starts_on');
            $table->date('next_run_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'public_id']);
            $table->index(['tenant_id', 'is_active', 'next_run_on'], 'recurring_expense_due_idx');
        });

        Schema::table('operational_expenses', function (Blueprint $table): void {
            $table->foreignId('recurring_expense_schedule_id')->nullable()->after('collector_custody_entry_id')->constrained()->nullOnDelete();
            $table->string('recurrence_key', 32)->nullable()->after('recurring_expense_schedule_id');
            $table->unique(['tenant_id', 'recurring_expense_schedule_id', 'recurrence_key'], 'operational_expense_recurrence_unique');
        });
    }

    public function down(): void
    {
        Schema::table('operational_expenses', function (Blueprint $table): void {
            $table->dropUnique('operational_expense_recurrence_unique');
            $table->dropColumn('recurrence_key');
            $table->dropConstrainedForeignId('recurring_expense_schedule_id');
        });
        Schema::dropIfExists('recurring_expense_schedules');
    }
};
