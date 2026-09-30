<?php

namespace Database\Seeders;

use App\Support\Population\PoocPopulation;
use Illuminate\Database\Seeder;

class PoocSyntheticPopulationSeeder extends Seeder
{
    public function run(): void
    {
        $report = app(PoocPopulation::class)->seed();
        $this->command?->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
