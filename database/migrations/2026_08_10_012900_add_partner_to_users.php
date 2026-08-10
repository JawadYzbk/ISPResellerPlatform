<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('partner_id')->nullable()->after('tenant_id')->constrained('partners')->nullOnDelete();
            $table->index(['tenant_id', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('partner_id');
            $table->dropIndex(['tenant_id', 'partner_id']);
        });
    }
};
