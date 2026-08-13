<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->string('content_hash', 64)->nullable()->after('idempotency_key');
            $table->index(['tenant_id', 'channel', 'recipient', 'content_hash', 'created_at'], 'messages_delivery_dedupe_index');
        });

        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->timestamp('next_send_at')->nullable()->after('last_ready_at');
            $table->timestamp('cooldown_until')->nullable()->after('next_send_at');
            $table->unsignedSmallInteger('failure_streak')->default(0)->after('cooldown_until');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->dropColumn(['next_send_at', 'cooldown_until', 'failure_streak']);
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex('messages_delivery_dedupe_index');
            $table->dropColumn('content_hash');
        });
    }
};
