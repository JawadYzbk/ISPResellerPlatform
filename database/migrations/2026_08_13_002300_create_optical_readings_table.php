<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('optical_readings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('optical_device_id')->nullable()->constrained('optical_devices')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('onu_serial', 120)->nullable();
            $table->decimal('rx_dbm', 8, 2)->nullable();
            $table->decimal('tx_dbm', 8, 2)->nullable();
            $table->decimal('temperature_c', 8, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->string('source', 32)->default('manual');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'optical_device_id', 'recorded_at']);
            $table->index(['tenant_id', 'service_id', 'recorded_at']);
            $table->index(['tenant_id', 'onu_serial', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optical_readings');
    }
};
