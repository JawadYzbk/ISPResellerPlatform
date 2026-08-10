<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->string('completion_idempotency_key', 120)->nullable()->after('completed_at');
            $table->unique(['tenant_id', 'completion_idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'completion_idempotency_key']);
            $table->dropColumn('completion_idempotency_key');
        });
    }
};
