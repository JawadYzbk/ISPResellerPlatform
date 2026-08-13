<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('optical_devices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pop_id')->nullable()->constrained('pops')->nullOnDelete();
            $table->string('name', 160);
            $table->string('code', 32);
            $table->string('device_type', 32)->default('olt');
            $table->string('vendor', 80)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('host', 255)->nullable();
            $table->unsignedSmallInteger('management_port')->nullable();
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'device_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optical_devices');
    }
};
