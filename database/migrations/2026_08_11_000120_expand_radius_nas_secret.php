<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('radius_nas', function (Blueprint $table): void {
            $table->text('secret')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('radius_nas', function (Blueprint $table): void {
            $table->string('secret')->nullable()->change();
        });
    }
};
