<?php

namespace App\Console\Commands;

use App\Support\Population\PoocPopulation;
use Illuminate\Console\Command;

class SeedPoocPopulation extends Command
{
    protected $signature = 'pooc:population';

    protected $description = 'Generate the population-only synthetic Pooc pilot; refuse existing population/operational data';

    public function handle(PoocPopulation $population): int
    {
        try {
            $this->line(json_encode($population->seed(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
