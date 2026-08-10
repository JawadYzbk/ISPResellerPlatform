<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('uploaded_by_id')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->dropIndex('media_uploads_tenant_id_customer_id_index');
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
