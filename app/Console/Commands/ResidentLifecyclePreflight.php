<?php

namespace App\Console\Commands;

use App\Support\ResidentLifecycleInventory;
use Illuminate\Console\Command;

class ResidentLifecyclePreflight extends Command
{
    protected $signature = 'residents:lifecycle-preflight {--json : Print the complete report as JSON}';

    protected $description = 'Read-only resident lifecycle inventory; never converts or repairs legacy records';

    public function handle(ResidentLifecycleInventory $inventory): int
    {
        $report = $inventory->report();
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            foreach (['SUMMARY' => 'summary', 'STATUS INVENTORY' => 'status_inventory',
                'LEGACY / AMBIGUOUS RECORDS' => 'ambiguous', 'IDENTITY / CODE CHECKS' => 'identity',
                'OWNERSHIP CHECKS' => 'ownership', 'CORRECTION CHECKS' => 'corrections', 'BLOCKERS' => 'blockers'] as $title => $key) {
                $this->info($title);
                $this->line(json_encode($report[$key], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }
        }

        return $report['blockers'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
