<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->foreignId('partner_id')->nullable()->after('customer_id')->constrained('partners')->restrictOnDelete();
            $table->index(['tenant_id', 'partner_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'partner_id', 'currency']);
            $table->dropConstrainedForeignId('partner_id');
        });
    }
};
