<?php

namespace App\Console\Commands;

use App\Services\Demo\DemoLocationBackfillService;
use Illuminate\Console\Command;

class BackfillDemoLocationsCommand extends Command
{
    protected $signature = 'demo:backfill-locations
                            {--force : Refresh coordinates even when latitude/longitude already set}
                            {--seed : Also re-run DomainDemoSeeder + ExpandedDemoDataSeeder first}';

    protected $description = 'Backfill realistic Spanish coordinates on demo leads/companies without calling Google Geocoding. Safe for local/demo DBs.';

    public function handle(DemoLocationBackfillService $backfill): int
    {
        if ($this->option('seed')) {
            $this->info('Re-seeding DomainDemoSeeder + ExpandedDemoDataSeeder…');
            $this->call('db:seed', [
                '--class' => 'Database\\Seeders\\DomainDemoSeeder',
                '--force' => true,
            ]);
            $this->call('db:seed', [
                '--class' => 'Database\\Seeders\\ExpandedDemoDataSeeder',
                '--force' => true,
            ]);
        }

        $onlyMissing = ! $this->option('force');
        $result = $backfill->backfill(onlyMissing: $onlyMissing);

        $this->info(sprintf(
            'Leads updated: %d (already OK: %d)',
            $result['leads_updated'],
            $result['leads_already_ok'],
        ));
        $this->info(sprintf(
            'Companies updated: %d (already OK: %d)',
            $result['companies_updated'],
            $result['companies_already_ok'],
        ));

        $this->newLine();
        $this->line('Local usage:');
        $this->line('  php artisan demo:backfill-locations');
        $this->line('  php artisan demo:backfill-locations --force');
        $this->line('  php artisan demo:backfill-locations --seed');
        $this->line('  php artisan migrate:fresh --seed   # full reset (destroys local data)');

        return self::SUCCESS;
    }
}
