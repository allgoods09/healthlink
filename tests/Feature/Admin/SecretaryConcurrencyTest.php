<?php

namespace Tests\Feature\Admin;

use App\Models\Barangay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SecretaryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function connectionsToTransact(): array
    {
        // The child connections must see the committed scope fixture.
        return [];
    }

    public static function transitions(): array
    {
        return ['creation' => [false], 'activation' => [true]];
    }

    #[DataProvider('transitions')]
    public function test_concurrent_assignment_waits_for_scope_lock_then_rejects_second_secretary(bool $activation): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $barangay = Barangay::factory()->create();
        $a = $activation ? User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id, 'is_active' => false]) : null;
        $b = $activation ? User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id, 'is_active' => false]) : null;
        $config = DB::connection()->getConfig();
        $environment = ['APP_ENV' => 'testing', 'DB_URL' => '', 'DB_DATABASE' => $config['database'],
            'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_USERNAME' => $config['username'],
            'DB_PASSWORD' => $config['password'], 'BCRYPT_ROUNDS' => '4'];
        $bootstrap = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();';
        $create = 'App\\Models\\User::factory()->create(["role"=>"secretary", "assigned_barangay_id"=>(int)$argv[1], "email"=>$argv[2]]);';
        if ($activation) {
            $create = 'App\\Models\\User::findOrFail((int)$argv[3])->update(["is_active"=>true]);';
        }
        $firstCode = $bootstrap.' Illuminate\\Support\\Facades\\DB::transaction(function () use ($argv) {
            Illuminate\\Support\\Facades\\DB::table("barangays")->where("id", (int)$argv[1])->lockForUpdate()->first();
            echo "locked\\n"; flush(); fgets(STDIN); '.$create.' }); echo "created\\n";';
        $secondCode = $bootstrap.' echo "attempting\\n"; flush(); try { '.$create.' echo "unexpected-success\\n";
            } catch (Illuminate\\Validation\\ValidationException $exception) { echo "rejected\\n"; }';
        $input = new InputStream;
        $first = new Process([PHP_BINARY, '-r', $firstCode, (string) $barangay->id, 'first@concurrency.test', (string) ($a?->id ?? 0)], base_path(), $environment, $input, 30);
        $second = new Process([PHP_BINARY, '-r', $secondCode, (string) $barangay->id, 'second@concurrency.test', (string) ($b?->id ?? 0)], base_path(), $environment, null, 30);
        try {
            $first->start();
            $this->assertTrue($first->waitUntil(fn ($type, $output) => str_contains($output, 'locked')));
            $second->start();
            $this->assertTrue($second->waitUntil(fn ($type, $output) => str_contains($output, 'attempting')));
            $this->assertTrue($first->isRunning());
            $input->write("release\n");
            $input->close();
            $first->wait();
            $second->wait();
            $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
            $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
            $this->assertStringContainsString('created', $first->getOutput());
            $this->assertStringContainsString('rejected', $second->getOutput());
            $this->assertSame(1, User::operationalSecretaries()->where('assigned_barangay_id', $barangay->id)->count());
        } finally {
            $first->stop();
            $second->stop();
            DB::table('users')->where('assigned_barangay_id', $barangay->id)->delete();
            DB::table('barangay_officials')->where('barangay_id', $barangay->id)->delete();
            DB::table('barangays')->where('id', $barangay->id)->delete();
        }
    }
}
