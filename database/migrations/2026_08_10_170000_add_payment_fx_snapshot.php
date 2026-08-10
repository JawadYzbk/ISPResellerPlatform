<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('ledger_amount')->nullable()->after('amount');
            $table->char('ledger_currency', 3)->nullable()->after('ledger_amount');
            $table->unsignedBigInteger('base_amount')->nullable()->after('ledger_currency');
            $table->unsignedBigInteger('fx_rate_numerator')->nullable()->after('base_amount');
            $table->unsignedBigInteger('fx_rate_denominator')->nullable()->after('fx_rate_numerator');
            $table->boolean('fx_rate_overridden')->default(false)->after('fx_rate_denominator');
            $table->string('fx_override_reason', 500)->nullable()->after('fx_rate_overridden');
            $table->string('reference', 128)->nullable()->after('fx_override_reason');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn([
                'ledger_amount',
                'ledger_currency',
                'base_amount',
                'fx_rate_numerator',
                'fx_rate_denominator',
                'fx_rate_overridden',
                'fx_override_reason',
                'reference',
            ]);
        });
    }
};
