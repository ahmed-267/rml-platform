<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->decimal('rate_per_m2', 10, 2)->nullable()->after('percentage');
        });

        Schema::table('commission_rules', function (Blueprint $table) {
            $table->decimal('percentage', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('commission_rules', function (Blueprint $table) {
            $table->dropColumn('rate_per_m2');
        });

        Schema::table('commission_rules', function (Blueprint $table) {
            $table->decimal('percentage', 5, 2)->nullable(false)->change();
        });
    }
};
