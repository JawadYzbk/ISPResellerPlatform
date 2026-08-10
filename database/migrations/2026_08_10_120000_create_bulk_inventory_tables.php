<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'inventory_item_id', 'warehouse_id']);
            $table->index(['tenant_id', 'warehouse_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('movement_type', 16);
            $table->decimal('quantity', 12, 3);
            $table->text('note')->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'inventory_item_id', 'warehouse_id', 'occurred_at']);
            $table->index(['tenant_id', 'work_order_id']);
        });

        Schema::create('work_order_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('consumed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->text('note')->nullable();
            $table->timestamp('consumed_at');
            $table->timestamps();
            $table->index(['tenant_id', 'work_order_id', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_materials');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
    }
};
