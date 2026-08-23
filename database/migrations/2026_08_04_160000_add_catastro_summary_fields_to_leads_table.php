<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'catastro_status')) {
                $table->string('catastro_status')->nullable()->default('not_checked')->after('cadastral_verified_at');
            }
            if (! Schema::hasColumn('leads', 'catastro_provider')) {
                $table->string('catastro_provider')->nullable()->after('catastro_status');
            }
            if (! Schema::hasColumn('leads', 'catastro_checked_at')) {
                $table->timestamp('catastro_checked_at')->nullable()->after('catastro_provider');
            }
            if (! Schema::hasColumn('leads', 'catastro_matched_address')) {
                $table->string('catastro_matched_address')->nullable()->after('catastro_checked_at');
            }
            if (! Schema::hasColumn('leads', 'catastro_municipality')) {
                $table->string('catastro_municipality')->nullable()->after('catastro_matched_address');
            }
            if (! Schema::hasColumn('leads', 'catastro_province')) {
                $table->string('catastro_province')->nullable()->after('catastro_municipality');
            }
            if (! Schema::hasColumn('leads', 'catastro_postcode')) {
                $table->string('catastro_postcode')->nullable()->after('catastro_province');
            }
            if (! Schema::hasColumn('leads', 'catastro_property_type')) {
                $table->string('catastro_property_type')->nullable()->after('catastro_postcode');
            }
            if (! Schema::hasColumn('leads', 'catastro_built_area')) {
                $table->decimal('catastro_built_area', 12, 2)->nullable()->after('catastro_property_type');
            }
            if (! Schema::hasColumn('leads', 'catastro_construction_year')) {
                $table->unsignedSmallInteger('catastro_construction_year')->nullable()->after('catastro_built_area');
            }
            if (! Schema::hasColumn('leads', 'catastro_raw_response_json')) {
                $table->json('catastro_raw_response_json')->nullable()->after('catastro_construction_year');
            }
            if (! Schema::hasColumn('leads', 'catastro_warnings_json')) {
                $table->json('catastro_warnings_json')->nullable()->after('catastro_raw_response_json');
            }
            if (! Schema::hasColumn('leads', 'catastro_error_message')) {
                $table->text('catastro_error_message')->nullable()->after('catastro_warnings_json');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $columns = [
                'catastro_status',
                'catastro_provider',
                'catastro_checked_at',
                'catastro_matched_address',
                'catastro_municipality',
                'catastro_province',
                'catastro_postcode',
                'catastro_property_type',
                'catastro_built_area',
                'catastro_construction_year',
                'catastro_raw_response_json',
                'catastro_warnings_json',
                'catastro_error_message',
            ];

            $existing = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn('leads', $column),
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }
};
