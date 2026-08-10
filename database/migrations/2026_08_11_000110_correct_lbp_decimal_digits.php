<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('currencies')->where('code', 'LBP')->update(['decimal_digits' => 0]);
    }

    public function down(): void
    {
        DB::table('currencies')->where('code', 'LBP')->update(['decimal_digits' => 2]);
    }
};
