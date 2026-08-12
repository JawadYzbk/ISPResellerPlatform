<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_bills', function (Blueprint $table): void {
            $table->foreignId('journal_entry_id')->nullable()->after('notes')->constrained('journal_entries')->restrictOnDelete();
            $table->unique('journal_entry_id');
        });

        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->foreignId('journal_entry_id')->nullable()->after('actor_id')->constrained('journal_entries')->restrictOnDelete();
            $table->unique('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_payments', function (Blueprint $table): void {
            $table->dropUnique(['journal_entry_id']);
            $table->dropConstrainedForeignId('journal_entry_id');
        });

        Schema::table('supplier_bills', function (Blueprint $table): void {
            $table->dropUnique(['journal_entry_id']);
            $table->dropConstrainedForeignId('journal_entry_id');
        });
    }
};
