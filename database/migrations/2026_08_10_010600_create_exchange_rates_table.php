<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->char('base_currency', 3);
            $table->char('quote_currency', 3);
            $table->unsignedBigInteger('rate_numerator');
            $table->unsignedBigInteger('rate_denominator');
            $table->timestamp('effective_from');
            $table->string('source')->default('manual');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'base_currency', 'quote_currency', 'effective_from']);
            $table->index(['tenant_id', 'base_currency', 'quote_currency', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
