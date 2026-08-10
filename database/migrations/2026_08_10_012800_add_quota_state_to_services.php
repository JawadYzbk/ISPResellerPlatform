<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedBigInteger('current_period_bytes')->default(0)->after('desired_state_version');
            $table->timestamp('fup_applied_at')->nullable()->after('current_period_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['current_period_bytes', 'fup_applied_at']);
        });
    }
};
