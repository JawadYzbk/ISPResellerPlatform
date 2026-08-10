<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upstream_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_name');
            $table->unsignedInteger('capacity_mbps')->nullable();
            $table->unsignedBigInteger('monthly_cost_amount');
            $table->char('currency', 3);
            $table->date('contract_start');
            $table->date('contract_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'pop_id', 'contract_start', 'contract_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upstream_links');
    }
};
