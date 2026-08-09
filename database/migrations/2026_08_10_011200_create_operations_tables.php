<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32);
            $table->string('type', 32);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('checklist')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->index(['tenant_id', 'status', 'scheduled_at']);
        });

        Schema::create('work_order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32);
            $table->string('from_status', 16)->nullable();
            $table->string('to_status', 16)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->string('type', 16)->default('warehouse');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64);
            $table->string('name');
            $table->string('category', 32);
            $table->boolean('is_serialized')->default(false);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'sku']);
        });

        Schema::create('inventory_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('serial_number', 128);
            $table->string('status', 16)->default('available');
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'serial_number']);
            $table->index(['tenant_id', 'warehouse_id', 'status']);
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('movement_type', 16);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_units');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('work_order_events');
        Schema::dropIfExists('work_orders');
    }
};
