<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('planned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('route_date');
            $table->string('status', 24)->default('planned');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'public_id']);
            $table->unique(['tenant_id', 'user_id', 'route_date']);
            $table->index(['tenant_id', 'route_date', 'status']);
        });

        Schema::create('collector_route_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id');
            $table->foreignId('collector_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('outcome', 32)->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('visited_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('accuracy_meters')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'public_id']);
            $table->unique(['collector_route_id', 'customer_id']);
            $table->unique(['collector_route_id', 'position']);
            $table->index(['tenant_id', 'outcome', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_route_stops');
        Schema::dropIfExists('collector_routes');
    }
};
