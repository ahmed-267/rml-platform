<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_evidence_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_type');
            $table->string('original_name');
            $table->string('path');
            $table->string('disk')->default('local');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('visibility')->default('private');
            $table->string('status')->default('uploaded');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'file_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_evidence_files');
    }
};
