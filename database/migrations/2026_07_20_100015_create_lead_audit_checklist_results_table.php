<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_audit_checklist_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_checklist_item_id')->constrained()->cascadeOnDelete();
            $table->boolean('checked')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['lead_audit_id', 'audit_checklist_item_id'], 'lead_audit_checklist_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_audit_checklist_results');
    }
};
