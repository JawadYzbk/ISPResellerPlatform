<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id');
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('counted_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->text('note')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('counted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'public_id']);
            $table->index(['tenant_id', 'status', 'counted_at']);
        });

        Schema::create('inventory_stock_count_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('expected_quantity', 12, 3);
            $table->decimal('counted_quantity', 12, 3);
            $table->decimal('variance_quantity', 12, 3);
            $table->timestamps();
            $table->unique(['inventory_stock_count_id', 'inventory_item_id'], 'inventory_count_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_count_lines');
        Schema::dropIfExists('inventory_stock_counts');
    }
};
