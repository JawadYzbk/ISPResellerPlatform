<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->string('document_type', 32)->nullable()->after('purpose');
            $table->date('retention_until')->nullable()->after('document_type');
            $table->index(['tenant_id', 'purpose', 'retention_until']);
        });
    }

    public function down(): void
    {
        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->dropIndex(['media_uploads_tenant_id_purpose_retention_until_index']);
            $table->dropColumn(['document_type', 'retention_until']);
        });
    }
};
