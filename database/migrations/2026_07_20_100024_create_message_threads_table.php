<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->string('thread_reference')->unique();
            $table->string('subject');
            $table->string('category');
            $table->string('status')->default('open');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('related_lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->foreignId('related_purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_threads');
    }
};
