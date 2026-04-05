<?php

namespace App\Console\Commands;

use App\Services\HmsLabCatalogSyncService;
use Illuminate\Console\Command;

class SyncLabCatalogCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'hms:sync-lab-catalog
                            {--dry-run : Show what would be created/updated without writing}
                            {--update-prices : Update name and price for tests that already exist locally}';

    /**
     * @var string
     */
    protected $description = 'Sync local lab_tests from the lab catalog API (GET /api/hms/tests; codes match POST lab-cases test_codes).';

    public function handle(HmsLabCatalogSyncService $catalog): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $updatePrices = (bool) $this->option('update-prices');

        try {
            $summary = $catalog->importCatalog($dryRun, $updatePrices);
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($dryRun ? 'Dry run (no database changes).' : 'Catalog sync finished.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Created (new test_code)', (string) $summary['created']],
                ['Updated (existing)', (string) $summary['updated']],
                ['Skipped', (string) $summary['skipped']],
            ]
        );

        if (! $dryRun && ! $updatePrices && $summary['skipped'] > 0 && $summary['created'] === 0) {
            $this->components->warn('Existing rows were skipped. Pass --update-prices to refresh names/prices from the lab.');
        }

        return self::SUCCESS;
    }
}
