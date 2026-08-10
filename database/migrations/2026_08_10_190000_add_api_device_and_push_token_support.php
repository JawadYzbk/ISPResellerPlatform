<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->string('device_id', 100)->nullable()->after('name')->index();
        });

        Schema::create('push_tokens', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->text('token_encrypted');
            $table->string('platform', 32);
            $table->string('app', 100);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_tokens');

        Schema::table('personal_access_tokens', function (Blueprint $table): void {
            $table->dropColumn('device_id');
        });
    }
};
