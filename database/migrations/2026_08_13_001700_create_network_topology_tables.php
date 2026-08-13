<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_buildings', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('code', 32);
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('floors')->nullable();
            $table->unsignedInteger('unit_count')->nullable();
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('distribution_boxes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('network_building_id')->constrained('network_buildings')->cascadeOnDelete();
            $table->foreignId('pop_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('code', 32);
            $table->string('box_type', 32)->default('distribution');
            $table->unsignedSmallInteger('capacity_ports');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'network_building_id', 'status']);
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->foreignId('network_building_id')->nullable()->after('router_id')->constrained('network_buildings')->nullOnDelete();
            $table->foreignId('distribution_box_id')->nullable()->after('network_building_id')->constrained('distribution_boxes')->nullOnDelete();
            $table->unsignedSmallInteger('network_port')->nullable()->after('distribution_box_id');
            $table->index(['tenant_id', 'distribution_box_id', 'network_port']);
            $table->unique(['distribution_box_id', 'network_port']);
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropUnique(['distribution_box_id', 'network_port']);
            $table->dropIndex(['tenant_id', 'distribution_box_id', 'network_port']);
            $table->dropConstrainedForeignId('distribution_box_id');
            $table->dropConstrainedForeignId('network_building_id');
            $table->dropColumn('network_port');
        });
        Schema::dropIfExists('distribution_boxes');
        Schema::dropIfExists('network_buildings');
    }
};
