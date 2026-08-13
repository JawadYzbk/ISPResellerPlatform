<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('collector_all_zones')->default(true)->after('timezone');
        });

        Schema::create('collector_zone_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'ended_at'], 'collector_zone_user_active_idx');
            $table->index(['tenant_id', 'zone_id', 'ended_at'], 'collector_zone_zone_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_zone_assignments');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('collector_all_zones');
        });
    }
};
