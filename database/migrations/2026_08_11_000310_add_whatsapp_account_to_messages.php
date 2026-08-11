<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->foreignId('whatsapp_account_id')->nullable()->after('customer_id')->constrained('whatsapp_accounts')->nullOnDelete();
            $table->index(['tenant_id', 'whatsapp_account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropForeign(['whatsapp_account_id']);
            $table->dropIndex(['tenant_id', 'whatsapp_account_id', 'created_at']);
            $table->dropColumn('whatsapp_account_id');
        });
    }
};
