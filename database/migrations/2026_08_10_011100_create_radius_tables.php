<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('radius_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('username', 64);
            $table->string('attribute', 64);
            $table->string('op', 2)->default(':=');
            $table->text('value');
            $table->timestamps();
            $table->unique(['tenant_id', 'username', 'attribute']);
        });

        Schema::create('radius_user_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->string('username', 64);
            $table->string('groupname', 64);
            $table->timestamps();
            $table->unique(['tenant_id', 'username']);
            $table->index(['tenant_id', 'groupname']);
        });

        Schema::create('radius_group_replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('groupname', 64);
            $table->string('attribute', 64);
            $table->string('op', 2)->default(':=');
            $table->text('value');
            $table->timestamps();
            $table->unique(['tenant_id', 'groupname', 'attribute']);
        });

        Schema::create('radius_nas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nasname');
            $table->string('shortname');
            $table->string('secret');
            $table->unsignedSmallInteger('coa_port')->default(1700);
            $table->timestamps();
            $table->unique(['tenant_id', 'nasname']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radius_nas');
        Schema::dropIfExists('radius_group_replies');
        Schema::dropIfExists('radius_user_groups');
        Schema::dropIfExists('radius_users');
    }
};
