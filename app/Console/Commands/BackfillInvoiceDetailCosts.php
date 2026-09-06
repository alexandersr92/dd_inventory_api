<?php

namespace App\Console\Commands;

use App\Models\InvoiceDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillInvoiceDetailCosts extends Command
{
    protected $signature = 'backfill:invoice-detail-costs
                            {--dry-run : Show counts without modifying data}
                            {--chunk=500 : Number of records to process per batch}';

    protected $description = 'Populate the cost column in invoice_details with the current product cost for existing records that have cost = NULL';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        $totalNull = InvoiceDetail::whereNull('cost')->count();

        if ($totalNull === 0) {
            $this->info('✅ All invoice details already have a cost value. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Found {$totalNull} invoice detail(s) with cost = NULL.");

        if ($isDryRun) {
            $this->warn('🔍 Dry run mode — no changes will be made.');
            return self::SUCCESS;
        }

        if (!$this->confirm("This will update {$totalNull} records. Continue?")) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;

        InvoiceDetail::whereNull('cost')
            ->with('product:id,cost')
            ->chunkById($chunkSize, function ($details) use (&$updated, &$skipped) {
                foreach ($details as $detail) {
                    if (!$detail->product) {
                        $skipped++;
                        continue;
                    }

                    $detail->cost = $detail->product->cost ?? 0;
                    $detail->timestamps = false; // Don't touch updated_at
                    $detail->save();
                    $updated++;
                }

                $this->info("  Processed batch... ({$updated} updated, {$skipped} skipped so far)");
            });

        $this->newLine();
        $this->info("✅ Done. Updated: {$updated}, Skipped (product deleted): {$skipped}");

        return self::SUCCESS;
    }
}
