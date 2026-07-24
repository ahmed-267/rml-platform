<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_documents', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('name');
            $table->text('description')->nullable();
            // Soft reference to avoid circular FK with template_versions.
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->timestamps();

            $table->unique('type');
            $table->index('active_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_documents');
    }
};
