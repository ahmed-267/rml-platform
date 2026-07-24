<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_packages', function (Blueprint $table) {
            $table->id();
            $table->string('package_reference')->unique();
            $table->string('name');
            $table->string('package_type');
            $table->foreignId('scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('requested_leads_count')->default(0);
            $table->decimal('size_range_min', 12, 2)->nullable();
            $table->decimal('size_range_max', 12, 2)->nullable();
            $table->decimal('distance_range_min', 10, 2)->nullable();
            $table->decimal('distance_range_max', 10, 2)->nullable();
            $table->json('zone_mix')->nullable();
            $table->decimal('avg_price_per_m2', 12, 2)->nullable();
            $table->decimal('estimated_total', 12, 2)->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('package_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_packages');
    }
};
