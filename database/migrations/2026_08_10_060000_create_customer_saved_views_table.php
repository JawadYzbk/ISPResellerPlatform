<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_saved_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('filters');
            $table->json('columns');
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'name']);
            $table->index(['tenant_id', 'user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_saved_views');
    }
};
