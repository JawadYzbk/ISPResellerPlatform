<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->foreignId('collector_task_message_id')->nullable()->after('work_order_id')->constrained()->cascadeOnDelete();
            $table->index(['tenant_id', 'collector_task_message_id'], 'media_collector_task_message_idx');
        });
    }

    public function down(): void
    {
        Schema::table('media_uploads', function (Blueprint $table): void {
            $table->dropIndex('media_collector_task_message_idx');
            $table->dropConstrainedForeignId('collector_task_message_id');
        });
    }
};
