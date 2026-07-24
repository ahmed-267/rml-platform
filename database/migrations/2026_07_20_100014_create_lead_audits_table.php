<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auditor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('final_decision_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('audit_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('requested_info')->nullable();
            $table->decimal('buying_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->decimal('suggested_price', 12, 2)->nullable();
            $table->decimal('expected_margin', 12, 2)->nullable();
            $table->text('override_reason')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index('auditor_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_audits');
    }
};
