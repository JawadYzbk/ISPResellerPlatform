<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'partners',
        'wallet_transactions',
        'network_commands',
        'portal_otp_challenges',
        'settlements',
        'routers',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->ulid('public_id')->nullable()->unique()->after('id');
            });

            DB::table($tableName)->whereNull('public_id')->orderBy('id')->each(function (object $record) use ($tableName): void {
                DB::table($tableName)->where('id', $record->id)->update(['public_id' => (string) Str::ulid()]);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropUnique($tableName.'_public_id_unique');
                $table->dropColumn('public_id');
            });
        }
    }
};
