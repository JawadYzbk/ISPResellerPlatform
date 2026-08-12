<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_batches', function (Blueprint $table): void {
            $table->string('contract_reference', 64)->nullable()->after('reference');
            $table->unsignedBigInteger('unit_cost_amount')->nullable()->after('contract_reference');
            $table->unsignedBigInteger('total_cost_amount')->nullable()->after('unit_cost_amount');
            $table->char('currency', 3)->nullable()->after('total_cost_amount');
        });
    }

    public function down(): void
    {
        Schema::table('credential_batches', function (Blueprint $table): void {
            $table->dropColumn(['contract_reference', 'unit_cost_amount', 'total_cost_amount', 'currency']);
        });
    }
};
