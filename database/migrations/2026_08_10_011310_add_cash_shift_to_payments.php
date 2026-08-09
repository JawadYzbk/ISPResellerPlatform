<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('cash_shift_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'cash_shift_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cash_shift_id');
            $table->dropIndex(['tenant_id', 'cash_shift_id']);
        });
    }
};
