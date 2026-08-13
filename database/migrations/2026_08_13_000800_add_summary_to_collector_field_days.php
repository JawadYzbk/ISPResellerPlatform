<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collector_field_days', function (Blueprint $table): void {
            $table->json('summary')->nullable()->after('check_out_source');
            $table->text('summary_note')->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('collector_field_days', function (Blueprint $table): void {
            $table->dropColumn(['summary', 'summary_note']);
        });
    }
};
