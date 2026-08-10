<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->string('address')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('routers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('api_port')->default(443);
            $table->string('username', 128);
            $table->text('password_encrypted');
            $table->boolean('tls_verify')->default(true);
            $table->string('status')->default('unknown');
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'host', 'api_port']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routers');
        Schema::dropIfExists('pops');
    }
};
