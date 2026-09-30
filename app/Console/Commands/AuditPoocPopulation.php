<?php

namespace App\Console\Commands;

use App\Support\Population\PoocPopulationAudit;
use Illuminate\Console\Command;

class AuditPoocPopulation extends Command
{
    protected $signature = 'pooc:population-audit';

    protected $description = 'Read-only consistency and demographic audit at the fixed synthetic population reference date';

    public function handle(PoocPopulationAudit $audit): int
    {
        $report = $audit->report();
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return array_sum($report['invalid']) ? self::FAILURE : self::SUCCESS;
    }
}
