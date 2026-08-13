<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->foreignId('network_building_id')->nullable()->after('service_id')->constrained('network_buildings')->nullOnDelete();
            $table->foreignId('distribution_box_id')->nullable()->after('network_building_id')->constrained('distribution_boxes')->nullOnDelete();
            $table->unsignedSmallInteger('network_port')->nullable()->after('distribution_box_id');
            $table->string('onu_serial', 128)->nullable()->after('network_port');
            $table->json('installation_survey')->nullable()->after('onu_serial');
            $table->timestamp('activation_accepted_at')->nullable()->after('completed_at');
            $table->foreignId('activation_accepted_by_id')->nullable()->after('activation_accepted_at')->constrained('users')->nullOnDelete();
            $table->text('activation_acceptance_note')->nullable()->after('activation_accepted_by_id');
            $table->index(['tenant_id', 'network_building_id', 'distribution_box_id']);
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropIndex('work_orders_tenant_id_network_building_id_distribution_box_id_index');
            $table->dropConstrainedForeignId('activation_accepted_by_id');
            $table->dropColumn(['activation_acceptance_note', 'activation_accepted_at', 'installation_survey', 'onu_serial', 'network_port']);
            $table->dropConstrainedForeignId('distribution_box_id');
            $table->dropConstrainedForeignId('network_building_id');
        });
    }
};
