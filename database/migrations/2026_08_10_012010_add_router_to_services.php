<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->foreignId('router_id')->nullable()->after('plan_id')->constrained('routers')->nullOnDelete();
            $table->index(['tenant_id', 'router_id']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropForeign(['router_id']);
            $table->dropIndex(['tenant_id', 'router_id']);
            $table->dropColumn('router_id');
        });
    }
};
