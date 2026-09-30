<?php

namespace App\Console\Commands;

use App\Support\Nutrition\LegacyOptInventory;
use Illuminate\Console\Command;

class ReportLegacyOpt extends Command
{
    protected $signature = 'opt:legacy-report';

    protected $description = 'Read-only legacy OPT reconciliation; never guesses or converts historical rounds';

    public function handle(LegacyOptInventory $inventory): int
    {
        $this->line(json_encode($inventory->report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
