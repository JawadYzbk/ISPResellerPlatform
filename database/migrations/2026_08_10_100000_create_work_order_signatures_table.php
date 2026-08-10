<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_signatures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_upload_id')->constrained('media_uploads')->restrictOnDelete();
            $table->foreignId('captured_by_id')->constrained('users')->restrictOnDelete();
            $table->string('signer_name', 120);
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->unique('work_order_id');
            $table->index(['tenant_id', 'signed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_signatures');
    }
};
