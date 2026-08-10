<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_sessions', function (Blueprint $table): void {
            $table->string('device_id', 100)->nullable()->after('customer_id');
            $table->index(['tenant_id', 'customer_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::table('portal_sessions', function (Blueprint $table): void {
            $table->dropIndex('portal_sessions_tenant_id_customer_id_device_id_index');
            $table->dropColumn('device_id');
        });
    }
};
