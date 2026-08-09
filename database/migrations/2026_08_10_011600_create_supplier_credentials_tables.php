<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32);
            $table->string('contact_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('credential_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('reference', 64);
            $table->timestamp('imported_at');
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'supplier_id', 'reference']);
        });

        Schema::create('upstream_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credential_batch_id')->constrained()->restrictOnDelete();
            $table->string('identifier', 128);
            $table->text('secret');
            $table->char('lookup_hash', 64);
            $table->string('status', 16)->default('available');
            $table->foreignId('assigned_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('quota_limit')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'lookup_hash']);
            $table->index(['tenant_id', 'status', 'expires_at']);
        });

        Schema::create('credential_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('upstream_credential_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('upstream_credential_id');
            $table->unique(['tenant_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_assignments');
        Schema::dropIfExists('upstream_credentials');
        Schema::dropIfExists('credential_batches');
        Schema::dropIfExists('suppliers');
    }
};
