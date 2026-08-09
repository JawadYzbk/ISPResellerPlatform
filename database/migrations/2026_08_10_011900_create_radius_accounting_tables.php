<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions_current', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('username', 64);
            $table->string('acct_session_id', 128);
            $table->string('nasname', 128);
            $table->string('framed_ip', 64)->nullable();
            $table->timestamp('acct_start_time')->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamp('stopped_at')->nullable();
            $table->string('terminate_cause', 64)->nullable();
            $table->unsignedBigInteger('input_octets')->default(0);
            $table->unsignedBigInteger('output_octets')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'acct_session_id']);
            $table->index(['tenant_id', 'last_seen_at']);
            $table->index(['tenant_id', 'service_id', 'stopped_at']);
        });

        Schema::create('usage_daily', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->unsignedBigInteger('input_octets')->default(0);
            $table->unsignedBigInteger('output_octets')->default(0);
            $table->unsignedBigInteger('total_octets')->default(0);
            $table->timestamp('rolled_up_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'service_id', 'usage_date']);
            $table->index(['tenant_id', 'usage_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_daily');
        Schema::dropIfExists('sessions_current');
    }
};
