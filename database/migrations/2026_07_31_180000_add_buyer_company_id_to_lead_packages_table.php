<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_packages', function (Blueprint $table) {
            $table->foreignId('buyer_company_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('companies')
                ->nullOnDelete();

            $table->index('buyer_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('lead_packages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('buyer_company_id');
        });
    }
};
