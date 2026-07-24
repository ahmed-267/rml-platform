<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_reference')->unique();
            $table->foreignId('submitted_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('scheme_id')->constrained()->restrictOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft');
            $table->string('customer_first_name');
            $table->string('customer_last_name');
            $table->string('customer_phone');
            $table->string('customer_whatsapp')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city');
            $table->string('postcode');
            $table->string('country', 2)->default('ES');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('property_type')->nullable();
            $table->string('epc_rating')->nullable();
            $table->decimal('size_m2', 12, 2)->nullable();
            $table->decimal('distance_km', 10, 2)->nullable();
            $table->decimal('buying_price', 12, 2)->nullable();
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->decimal('expected_margin', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('listed_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['seller_company_id', 'status']);
            $table->index(['submitted_by_user_id', 'status']);
            $table->index(['scheme_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
