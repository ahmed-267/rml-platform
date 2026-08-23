<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('formatted_address')->nullable()->after('longitude');
            $table->string('geocoding_status')->nullable()->after('formatted_address');
            $table->timestamp('geocoded_at')->nullable()->after('geocoding_status');
            $table->text('geocoding_error')->nullable()->after('geocoded_at');
            $table->string('cadastral_reference')->nullable()->after('geocoding_error');
            $table->string('cadastral_lookup_status')->nullable()->after('cadastral_reference');
            $table->timestamp('cadastral_verified_at')->nullable()->after('cadastral_lookup_status');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->string('formatted_address')->nullable()->after('longitude');
            $table->string('geocoding_status')->nullable()->after('formatted_address');
            $table->timestamp('geocoded_at')->nullable()->after('geocoding_status');
            $table->text('geocoding_error')->nullable()->after('geocoded_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'formatted_address',
                'geocoding_status',
                'geocoded_at',
                'geocoding_error',
                'cadastral_reference',
                'cadastral_lookup_status',
                'cadastral_verified_at',
            ]);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'formatted_address',
                'geocoding_status',
                'geocoded_at',
                'geocoding_error',
            ]);
        });
    }
};
