<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('seller_type');
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approval_status')->default('pending');
            $table->string('bank_account_iban')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('payout_method')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('seller_type');
            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_profiles');
    }
};
