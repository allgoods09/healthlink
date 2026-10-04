<?php

namespace App\Console\Commands;

use App\Support\ResidentCodeAllocator;
use Illuminate\Console\Command;

class InitializeResidentCodeSequences extends Command
{
    protected $signature = 'residents:initialize-code-sequences';

    protected $description = 'Reserve Resident code high-water marks from physical residents and archived snapshots; never change issued codes';

    public function handle(ResidentCodeAllocator $allocator): int
    {
        $report = $allocator->initialize();
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
