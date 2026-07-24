<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('key');
            $table->boolean('required')->default(true);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['scheme_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_checklist_items');
    }
};
