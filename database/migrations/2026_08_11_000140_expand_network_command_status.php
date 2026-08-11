<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('network_commands', function (Blueprint $table): void {
            $table->string('status', 32)->change();
        });
    }

    public function down(): void
    {
        Schema::table('network_commands', function (Blueprint $table): void {
            $table->string('status', 16)->change();
        });
    }
};
