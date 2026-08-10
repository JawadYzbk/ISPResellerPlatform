<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->text('radius_secret_encrypted')->nullable()->after('password_encrypted');
            $table->unsignedSmallInteger('coa_port')->default(1700)->after('radius_secret_encrypted');
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table): void {
            $table->dropColumn(['radius_secret_encrypted', 'coa_port']);
        });
    }
};
