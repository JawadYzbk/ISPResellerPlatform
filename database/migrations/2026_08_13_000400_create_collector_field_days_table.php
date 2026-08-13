<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_field_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_in_at');
            $table->decimal('check_in_latitude', 10, 7);
            $table->decimal('check_in_longitude', 10, 7);
            $table->unsignedInteger('check_in_accuracy_meters')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->unsignedInteger('check_out_accuracy_meters')->nullable();
            $table->string('check_in_source', 32)->default('web_geolocation');
            $table->string('check_out_source', 32)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'public_id']);
            $table->index(['tenant_id', 'user_id', 'checked_out_at'], 'field_day_user_open_idx');
            $table->index(['tenant_id', 'checked_in_at'], 'field_day_started_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_field_days');
    }
};
