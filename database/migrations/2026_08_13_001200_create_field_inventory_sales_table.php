<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_inventory_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('posted');
            $table->char('currency', 3);
            $table->unsignedBigInteger('total_amount');
            $table->string('payment_method', 32);
            $table->string('idempotency_key', 120);
            $table->text('note')->nullable();
            $table->timestamp('sold_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'public_id']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'collector_id', 'sold_at']);
        });

        Schema::create('field_inventory_sale_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_inventory_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->unsignedBigInteger('unit_amount');
            $table->unsignedBigInteger('total_amount');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_inventory_sale_lines');
        Schema::dropIfExists('field_inventory_sales');
    }
};
