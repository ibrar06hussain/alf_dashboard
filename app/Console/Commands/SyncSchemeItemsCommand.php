<?php

namespace App\Console\Commands;

use App\Models\Scheme;
use App\Services\SchemeItemSyncService;
use Illuminate\Console\Command;

class SyncSchemeItemsCommand extends Command
{
    protected $signature = 'schemes:sync-items
                            {scheme_id? : Optional ID of a specific scheme to sync (omit to sync all schemes with billable item fields)}
                            {--dry-run : Preview changes and rollback without writing to the database}';

    protected $description = 'Backfill missing application_items from legacy form values, sync unpaid item pricing from Form Builder, and regenerate/cancel unpaid challans & PSIDs.';

    public function handle(SchemeItemSyncService $syncService): int
    {
        $schemeId = $this->argument('scheme_id');
        $dryRun = (bool) $this->option('dry-run');

        $schemes = $schemeId
            ? Scheme::where('id', (int) $schemeId)->get()
            : Scheme::whereHas('forms.fields', fn ($q) => $q->where('field_type', 'checkbox_with_text')->orWhere('is_billable_items_field', true))->get();

        if ($schemes->isEmpty()) {
            $this->warn('No matching schemes found.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info('[DRY RUN] Simulating sync without persisting database changes...');
        }

        foreach ($schemes as $scheme) {
            $stats = $syncService->syncScheme($scheme, $dryRun);
            $this->line(sprintf(
                'Scheme #%d (%s): scanned=%d, skipped_paid_or_terminal=%d, items_created=%d, items_updated=%d, demanded_qty_updated=%d, approved_synced=%d, challans_regenerated=%d, challans_cancelled_free=%d',
                $scheme->id,
                $scheme->title,
                $stats['apps_scanned'],
                $stats['apps_skipped_paid_or_terminal'],
                $stats['items_created'],
                $stats['items_updated'],
                $stats['demanded_qty_updated'],
                $stats['approved_apps_synced'],
                $stats['challans_updated_or_generated'],
                $stats['challans_cancelled_to_eligible']
            ));
        }

        return self::SUCCESS;
    }
}
