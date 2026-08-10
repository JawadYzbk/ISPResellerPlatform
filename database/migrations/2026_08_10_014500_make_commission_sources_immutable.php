<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_entries', function (Blueprint $table): void {
            $table->dropUnique('commission_entries_source_unique');
            $table->unique(['tenant_id', 'partner_id', 'source_type', 'source_id'], 'commission_entries_source_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('commission_entries', function (Blueprint $table): void {
            $table->dropUnique('commission_entries_source_identity_unique');
            $table->unique(['tenant_id', 'partner_id', 'source_type', 'source_id', 'rule_version'], 'commission_entries_source_unique');
        });
    }
};
