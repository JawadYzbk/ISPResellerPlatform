<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 5)->nullable()->default(null)->change();
        });

        // The old column default represented the workspace default, not a personal choice.
        DB::table('users')->where('locale', 'en')->update(['locale' => null]);
    }

    public function down(): void
    {
        DB::table('users')->whereNull('locale')->update(['locale' => 'en']);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 5)->default('en')->nullable(false)->change();
        });
    }
};
