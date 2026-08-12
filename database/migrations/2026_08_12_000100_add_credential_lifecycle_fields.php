<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credential_assignments', function (Blueprint $table): void {
            $table->timestamp('released_at')->nullable()->after('assigned_at');
            $table->string('release_reason', 64)->nullable()->after('released_at');
        });

        Schema::table('credential_assignments', function (Blueprint $table): void {
            $table->dropUnique('credential_assignments_upstream_credential_id_unique');
            $table->dropUnique('credential_assignments_tenant_id_service_id_unique');
            $table->index(['tenant_id', 'upstream_credential_id', 'released_at']);
            $table->index(['tenant_id', 'service_id', 'released_at']);
        });

        DB::statement('CREATE UNIQUE INDEX credential_assignments_active_credential_unique ON credential_assignments (tenant_id, upstream_credential_id) WHERE released_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX credential_assignments_active_service_unique ON credential_assignments (tenant_id, service_id) WHERE released_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS credential_assignments_active_service_unique');
        DB::statement('DROP INDEX IF EXISTS credential_assignments_active_credential_unique');

        Schema::table('credential_assignments', function (Blueprint $table): void {
            $table->dropIndex('credential_assignments_tenant_id_service_id_released_at_index');
            $table->dropIndex('credential_assignments_tenant_id_upstream_credential_id_released_at_index');
            $table->unique('upstream_credential_id');
            $table->unique(['tenant_id', 'service_id']);
            $table->dropColumn(['released_at', 'release_reason']);
        });
    }
};
