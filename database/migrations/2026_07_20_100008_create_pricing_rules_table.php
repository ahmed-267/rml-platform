<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_id')->constrained()->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('price_per_m2', 12, 2);
            $table->decimal('basic_price', 12, 2)->nullable();
            $table->decimal('zone_factor', 8, 4)->nullable();
            $table->decimal('size_factor', 8, 4)->nullable();
            $table->decimal('distance_factor', 8, 4)->nullable();
            $table->boolean('active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['scheme_id', 'zone_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
