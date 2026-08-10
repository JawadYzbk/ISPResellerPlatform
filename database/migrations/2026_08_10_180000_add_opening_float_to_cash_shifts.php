<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table): void {
            $table->json('opening_float')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('cash_shifts', function (Blueprint $table): void {
            $table->dropColumn('opening_float');
        });
    }
};
