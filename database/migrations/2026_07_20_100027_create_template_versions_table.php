<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('content')->nullable();
            $table->string('file_path')->nullable();
            $table->date('effective_from')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('active')->default(false);
            $table->timestamps();

            $table->unique(['template_document_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_versions');
    }
};
