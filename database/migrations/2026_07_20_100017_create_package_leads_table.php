<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['lead_package_id', 'lead_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_leads');
    }
};
