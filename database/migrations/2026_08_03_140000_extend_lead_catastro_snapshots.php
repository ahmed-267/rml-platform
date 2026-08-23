<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_catastro_snapshots', function (Blueprint $table) {
            if (! Schema::hasColumn('lead_catastro_snapshots', 'search_method')) {
                $table->string('search_method')->nullable()->after('provider');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'search_input')) {
                $table->json('search_input')->nullable()->after('search_method');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'province')) {
                $table->string('province')->nullable()->after('cadastral_address');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'municipality')) {
                $table->string('municipality')->nullable()->after('province');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'street_type')) {
                $table->string('street_type')->nullable()->after('municipality');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'street_name')) {
                $table->string('street_name')->nullable()->after('street_type');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'street_number')) {
                $table->string('street_number')->nullable()->after('street_name');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'block')) {
                $table->string('block')->nullable()->after('street_number');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'staircase')) {
                $table->string('staircase')->nullable()->after('block');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'floor')) {
                $table->string('floor')->nullable()->after('staircase');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'door')) {
                $table->string('door')->nullable()->after('floor');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'postcode')) {
                $table->string('postcode', 16)->nullable()->after('door');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'unit_label')) {
                $table->string('unit_label')->nullable()->after('postcode');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'is_current')) {
                $table->boolean('is_current')->default(false)->after('unit_label');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'is_selected')) {
                $table->boolean('is_selected')->default(false)->after('is_current');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'selected_at')) {
                $table->timestamp('selected_at')->nullable()->after('is_selected');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'selected_by_user_id')) {
                $table->foreignId('selected_by_user_id')->nullable()->after('selected_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'auditor_confirmed_at')) {
                $table->timestamp('auditor_confirmed_at')->nullable()->after('selected_by_user_id');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'auditor_confirmed_by_user_id')) {
                $table->foreignId('auditor_confirmed_by_user_id')->nullable()->after('auditor_confirmed_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'auditor_notes')) {
                $table->text('auditor_notes')->nullable()->after('auditor_confirmed_by_user_id');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'warnings')) {
                $table->json('warnings')->nullable()->after('auditor_notes');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'provider_request_id')) {
                $table->string('provider_request_id')->nullable()->after('warnings');
            }
            if (! Schema::hasColumn('lead_catastro_snapshots', 'coordinate_distance_m')) {
                $table->decimal('coordinate_distance_m', 12, 2)->nullable()->after('provider_request_id');
            }
        });

        $this->ensureLeadCurrentIndex();
        $this->backfillCurrentSnapshots();
        $this->ensureOneCurrentPerLeadIndex();
    }

    public function down(): void
    {
        Schema::table('lead_catastro_snapshots', function (Blueprint $table) {
            if (Schema::hasColumn('lead_catastro_snapshots', 'selected_by_user_id')) {
                $table->dropConstrainedForeignId('selected_by_user_id');
            }
            if (Schema::hasColumn('lead_catastro_snapshots', 'auditor_confirmed_by_user_id')) {
                $table->dropConstrainedForeignId('auditor_confirmed_by_user_id');
            }

            try {
                $table->dropIndex(['lead_id', 'is_current']);
            } catch (\Throwable) {
                // Index may not exist on older installs.
            }

            try {
                DB::statement('DROP INDEX IF EXISTS lead_catastro_snapshots_one_current_per_lead');
            } catch (\Throwable) {
                // SQLite / older installs.
            }

            $columns = [
                'search_method',
                'search_input',
                'province',
                'municipality',
                'street_type',
                'street_name',
                'street_number',
                'block',
                'staircase',
                'floor',
                'door',
                'postcode',
                'unit_label',
                'is_current',
                'is_selected',
                'selected_at',
                'auditor_confirmed_at',
                'auditor_notes',
                'warnings',
                'provider_request_id',
                'coordinate_distance_m',
            ];

            $existing = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn('lead_catastro_snapshots', $column),
            ));

            if ($existing !== []) {
                $table->dropColumn($existing);
            }
        });
    }

    private function ensureLeadCurrentIndex(): void
    {
        $indexName = 'lead_catastro_snapshots_lead_id_is_current_index';
        $indexes = Schema::getIndexes('lead_catastro_snapshots');
        $exists = collect($indexes)->contains(
            fn (array $index) => ($index['name'] ?? '') === $indexName
                || (($index['columns'] ?? []) === ['lead_id', 'is_current']),
        );

        if ($exists) {
            return;
        }

        Schema::table('lead_catastro_snapshots', function (Blueprint $table) {
            $table->index(['lead_id', 'is_current']);
        });
    }

    private function ensureOneCurrentPerLeadIndex(): void
    {
        if (! Schema::hasColumn('lead_catastro_snapshots', 'is_current')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('
                CREATE UNIQUE INDEX IF NOT EXISTS lead_catastro_snapshots_one_current_per_lead
                ON lead_catastro_snapshots (lead_id)
                WHERE is_current = true
            ');

            return;
        }

        if ($driver === 'sqlite') {
            try {
                DB::statement('
                    CREATE UNIQUE INDEX IF NOT EXISTS lead_catastro_snapshots_one_current_per_lead
                    ON lead_catastro_snapshots (lead_id)
                    WHERE is_current = 1
                ');
            } catch (\Throwable) {
                // Partial unique indexes are best-effort on SQLite test DBs.
            }
        }
    }

    private function backfillCurrentSnapshots(): void
    {
        if (! Schema::hasColumn('lead_catastro_snapshots', 'is_current')) {
            return;
        }

        DB::table('lead_catastro_snapshots')->update(['is_current' => false]);

        $leadIds = DB::table('lead_catastro_snapshots')
            ->distinct()
            ->orderBy('lead_id')
            ->pluck('lead_id');

        foreach ($leadIds as $leadId) {
            $latestId = DB::table('lead_catastro_snapshots')
                ->where('lead_id', $leadId)
                ->orderByRaw('COALESCE(lookup_at, created_at) DESC')
                ->orderByDesc('id')
                ->value('id');

            if ($latestId) {
                DB::table('lead_catastro_snapshots')
                    ->where('id', $latestId)
                    ->update(['is_current' => true]);
            }
        }
    }
};
