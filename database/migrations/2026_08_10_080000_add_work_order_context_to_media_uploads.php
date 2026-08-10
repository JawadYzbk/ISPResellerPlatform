<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->foreignId('work_order_id')->nullable()->after('uploaded_by_id')->constrained('work_orders')->nullOnDelete();
            $table->string('purpose', 32)->default('evidence')->after('work_order_id');
            $table->index(['tenant_id', 'work_order_id']);
        });
    }

    public function down(): void
    {
        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->dropIndex('media_uploads_tenant_id_work_order_id_index');
            $table->dropConstrainedForeignId('work_order_id');
            $table->dropColumn('purpose');
        });
    }
};
