<?php

namespace Tests\Feature\Database;

use App\Models\Resident;
use App\Support\ResidentCodeAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\LifecycleResidentFixture;
use Tests\TestCase;

class ResidentCodeAllocatorConcurrencyTest extends TestCase
{
    use LifecycleResidentFixture, RefreshDatabase;

    protected function connectionsToTransact(): array
    {
        return [];
    }

    public static function namespaces(): array
    {
        return [[false, false, false], [true, false, false], [false, true, false], [true, true, false],
            [false, false, true], [true, false, true], [false, true, true], [true, true, true]];
    }

    #[DataProvider('namespaces')]
    public function test_parallel_creators_use_unique_codes_and_unrelated_namespaces_do_not_wait(bool $firstUse, bool $differentNamespace, bool $outerTransaction): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $base = $this->residentFixture();
        $other = $differentNamespace ? $this->residentFixture() : $base;
        $namespace = $base->household->purok->barangay_id;
        $otherNamespace = $other->household->purok->barangay_id;
        $ids = array_unique([$namespace, $otherNamespace]);
        if ($firstUse) {
            DB::table('resident_code_sequences')->whereIn('origin_barangay_id', $ids)->delete();
        }
        $config = DB::connection()->getConfig();
        $env = ['APP_ENV' => 'testing', 'DB_URL' => '', 'DB_DATABASE' => $config['database'], 'DB_HOST' => $config['host'],
            'DB_PORT' => (string) $config['port'], 'DB_USERNAME' => $config['username'], 'DB_PASSWORD' => $config['password']];
        $bootstrap = 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();';
        $create = '$base=App\\Models\\Resident::findOrFail((int)$argv[1]); $data=$base->getAttributes(); foreach(["id","official_resident_code","mobile_uuid","philsys_card_no","created_at","updated_at","deleted_at"] as $key)unset($data[$key]); $data["first_name"]=$argv[2]; $resident=App\\Models\\Resident::create($data); echo "code:".$resident->official_resident_code."\\n"; flush();';
        $firstCode = $bootstrap.' Illuminate\\Support\\Facades\\DB::transaction(function()use($argv){ '.$create.' echo "inserted\\n"; flush(); fgets(STDIN); });';
        $secondCode = $bootstrap.' echo "attempting\\n"; flush(); '.($outerTransaction
            ? 'Illuminate\\Support\\Facades\\DB::transaction(function()use($argv){ '.$create.' });'
            : $create);
        $input = new InputStream;
        $first = new Process([PHP_BINARY, '-r', $firstCode, (string) $base->id, 'Concurrent first'], base_path(), $env, $input, 30);
        $workers = [];
        try {
            $first->start();
            $this->assertTrue($first->waitUntil(fn ($type, $output) => str_contains($output, 'inserted')));
            for ($i = 0; $i < 3; $i++) {
                $worker = new Process([PHP_BINARY, '-r', $secondCode, (string) $other->id, 'Concurrent worker '.$i], base_path(), $env, null, 30);
                $workers[] = $worker;
                $worker->start();
                $this->assertTrue($worker->waitUntil(fn ($type, $output) => str_contains($output, 'attempting')));
            }
            if ($differentNamespace) {
                // These must finish while the first namespace's transaction is still held open.
                foreach ($workers as $worker) {
                    $worker->wait();
                }
                $this->assertTrue($first->isRunning());
            }
            $input->write("release\n");
            $input->close();
            $first->wait();
            foreach ($workers as $worker) {
                $worker->wait();
            }
            foreach ([$first, ...$workers] as $worker) {
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            }
            $residents = Resident::whereIn('household_id', [$base->household_id, $other->household_id])->get();
            $codes = $residents->pluck('official_resident_code')->all();
            $this->assertCount($differentNamespace ? 6 : 5, $codes);
            $this->assertCount(count($codes), array_unique($codes));
            foreach ($ids as $id) {
                $suffixes = $residents->map(fn ($r) => ResidentCodeAllocator::parse($r->official_resident_code))
                    ->filter(fn ($parts) => $parts['namespace'] === $id)->pluck('value')->all();
                $this->assertCount(count($suffixes), array_unique($suffixes));
                $this->assertGreaterThanOrEqual(max($suffixes), (int) DB::table('resident_code_sequences')->where('origin_barangay_id', $id)->value('last_value'));
            }
        } finally {
            $first->stop();
            foreach ($workers as $worker) {
                $worker->stop();
            }
            DB::table('barangays')->whereIn('id', $ids)->delete();
            // Test-only cleanup of its own namespaces; production never deletes sequence history.
            DB::table('resident_code_sequences')->whereIn('origin_barangay_id', $ids)->delete();
        }
    }
}
