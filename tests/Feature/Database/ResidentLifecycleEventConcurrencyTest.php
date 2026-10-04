<?php

namespace Tests\Feature\Database;

use App\Models\ResidentLifecycleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\LifecycleResidentFixture;
use Tests\TestCase;

class ResidentLifecycleEventConcurrencyTest extends TestCase
{
    use LifecycleResidentFixture, RefreshDatabase;

    protected function connectionsToTransact(): array
    {
        return [];
    }

    public static function transactions(): array
    {
        return [['standalone'], ['outer_transaction']];
    }

    #[DataProvider('transactions')]
    public function test_concurrent_retry_reuses_the_winner_even_inside_repeatable_read(string $mode): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $resident = $this->residentFixture();
        $barangayId = $resident->household->purok->barangay_id;
        $config = DB::connection()->getConfig();
        $env = ['APP_ENV' => 'testing', 'DB_URL' => '', 'DB_DATABASE' => $config['database'],
            'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'],
            'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password']];
        $bootstrap = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();';
        $record = '$resident=App\\Models\\Resident::findOrFail((int)$argv[1]); $event=app(App\\Support\\Lifecycle\\LifecycleEventRecorder::class)->record($resident, App\\Models\\ResidentLifecycleEvent::REGISTRY_CAPTURE, "race:test", metadata: ["value"=>1.0]); echo "event:".$event->id."\\n"; flush();';
        $firstCode = $bootstrap.' Illuminate\\Support\\Facades\\DB::transaction(function() use($argv){ '.$record.' echo "inserted\\n"; flush(); fgets(STDIN); });';
        $secondCode = $bootstrap.' App\\Models\\ResidentLifecycleEvent::creating(function(){ echo "insert-attempt\\n"; flush(); });';
        $secondCode .= $mode === 'outer_transaction' ? 'Illuminate\\Support\\Facades\\DB::transaction(function() use($argv){ '.$record.' });' : $record;
        $input = new InputStream;
        $first = new Process([PHP_BINARY, '-r', $firstCode, (string) $resident->id], base_path(), $env, $input, 30);
        $second = new Process([PHP_BINARY, '-r', $secondCode, (string) $resident->id], base_path(), $env, null, 30);
        try {
            $first->start();
            $this->assertTrue($first->waitUntil(fn ($type, $output) => str_contains($output, 'inserted')));
            $second->start();
            $this->assertTrue($second->waitUntil(fn ($type, $output) => str_contains($output, 'insert-attempt')));
            $input->write("release\n");
            $input->close();
            $first->wait();
            $second->wait();
            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
            $events = ResidentLifecycleEvent::where('resident_id', $resident->id)->get();
            $this->assertCount(1, $events);
            $this->assertStringContainsString('event:'.$events->sole()->id, $first->getOutput());
            $this->assertStringContainsString('event:'.$events->sole()->id, $second->getOutput());
        } finally {
            $first->stop();
            $second->stop();
            // Explicit test-only cleanup; production Eloquent mutation remains forbidden.
            DB::table('resident_lifecycle_events')->where('resident_id', $resident->id)->delete();
            DB::table('barangays')->where('id', $barangayId)->delete();
        }
    }
}
