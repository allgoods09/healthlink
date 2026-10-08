<?php

namespace Tests\Feature\Mobile;

use App\Models\{Barangay, FieldVisit, Household, Purok, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MobileVisitPhotoPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function context(int $count): array
    {
        $disk = Storage::fake('local');
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $bhw = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id, 'approval_status' => 'approved', 'is_active' => true,
        ]);
        $recorder = User::factory()->create(['role' => 'bhw']);
        $household = Household::create([
            'purok_id' => $purok->id, 'household_no' => 'POLICY-1', 'household_address' => 'Test address',
            'is_active' => true, 'is_social_aid_beneficiary' => false,
        ]);
        $photos = [];
        for ($i = 0; $i < $count; $i++) {
            $path = 'visit-photos/2026/10/historical-'.$i.'.jpg';
            $disk->put($path, 'historical bytes '.$i);
            $photos[] = ['path' => $path, 'file_name' => 'historical-'.$i.'.jpg'];
        }
        $visit = FieldVisit::create([
            'mobile_uuid' => (string) Str::uuid(), 'household_id' => $household->id,
            'recorded_by_user_id' => $recorder->id, 'visited_at' => '2026-10-01 09:00:00',
            'notes' => 'Original notes', 'photos' => $photos, 'source' => 'mobile',
        ])->refresh();
        Sanctum::actingAs($bhw, ['mobile']);

        return [$household, $visit, $disk];
    }

    private function payload(Household $household, ?FieldVisit $visit, int $retained, int $new): array
    {
        $photos = [];
        for ($i = 0; $i < $new; $i++) {
            $photos[] = ['data' => base64_encode('new bytes '.$i), 'mime_type' => 'image/jpeg'];
        }

        return array_merge($visit ? [
            'id' => $visit->id, 'existing_photos' => array_column(array_slice($visit->photos, 0, $retained), 'path'),
        ] : [], [
            'mobile_uuid' => $visit?->mobile_uuid ?? (string) Str::uuid(), 'household_id' => $household->id,
            'visited_at' => '2026-10-07 10:00:00', 'notes' => 'Updated notes', 'photos' => $photos,
        ]);
    }

    private function assertRejectedWithoutWrites(array $payload, FieldVisit $visit, $disk): void
    {
        $original = $visit->getAttributes();
        $files = $disk->allFiles();
        $bytes = array_map(fn ($path) => $disk->get($path), $files);
        $proxy = Mockery::mock($disk);
        $proxy->shouldNotReceive('put');
        $proxy->shouldNotReceive('delete');
        Storage::shouldReceive('disk')->with('local')->andReturn($proxy);
        $this->postJson('/api/mobile/sync', ['field_visits' => [$payload]])->assertOk()
            ->assertJsonPath('status', 'failed')->assertJsonPath('success', false)
            ->assertJsonPath('records_synced', 0)->assertJsonCount(0, 'resolved_records.field_visits');
        $this->assertSame($original, $visit->fresh()->getAttributes());
        $this->assertDatabaseCount('field_visits', 1);
        $this->assertSame($files, $disk->allFiles());
        $this->assertSame($bytes, array_map(fn ($path) => $disk->get($path), $files));
    }

    #[DataProvider('normalCounts')]
    public function test_normal_records_enforce_five_total(int $retained, int $new, bool $creating): void
    {
        [$household, $visit, $disk] = $this->context($retained);
        $payload = $this->payload($household, $creating ? null : $visit, $retained, $new);
        if ($retained + $new > 5) {
            $this->assertRejectedWithoutWrites($payload, $visit, $disk);

            return;
        }
        $this->postJson('/api/mobile/sync', ['field_visits' => [$payload]])->assertOk()
            ->assertJsonPath('status', 'success')->assertJsonCount(1, 'resolved_records.field_visits');
        $saved = FieldVisit::where('mobile_uuid', $payload['mobile_uuid'])->firstOrFail();
        $this->assertCount($retained + $new, $saved->photos);
        $this->assertSame('Updated notes', $saved->notes);
        $this->assertSame($household->id, $saved->household_id);
        foreach (array_slice($saved->photos, $retained) as $i => $photo) {
            $this->assertSame('new bytes '.$i, $disk->get($photo['path']));
        }
        if (! $creating) {
            $this->assertSame($visit->recorded_by_user_id, $saved->recorded_by_user_id);
        }
    }

    public static function normalCounts(): array
    {
        $cases = [];
        for ($new = 0; $new <= 6; $new++) {
            $cases['new '.$new] = [0, $new, true];
        }
        for ($retained = 0; $retained <= 5; $retained++) {
            $cases['retain '.$retained] = [$retained, 0, false];
        }
        return array_merge($cases, [
            '2 retained plus 3 new' => [2, 3, false], '4 retained plus 1 new' => [4, 1, false],
            '5 retained plus 1 new' => [5, 1, false], '3 retained plus 3 new' => [3, 3, false],
        ]);
    }

    #[DataProvider('historicalCounts')]
    public function test_historical_records_can_retain_or_reduce_only_authorized_attachments(int $retained, int $new, bool $omit): void
    {
        [$household, $visit, $disk] = $this->context(10);
        $payload = $this->payload($household, $visit, $retained, $new);
        if ($omit) {
            unset($payload['existing_photos']);
            $retained = 10;
        }
        $notesOnly = $retained === 10 && $new === 0;
        if ($notesOnly) {
            unset($payload['visited_at']);
        }
        if ($retained + $new > 5 && $new > 0) {
            $this->assertRejectedWithoutWrites($payload, $visit, $disk);

            return;
        }
        $this->postJson('/api/mobile/sync', ['field_visits' => [$payload]])->assertOk()
            ->assertJsonPath('status', 'success')->assertJsonCount(1, 'resolved_records.field_visits');
        $saved = $visit->fresh();
        $this->assertCount($retained + $new, $saved->photos);
        $this->assertSame(array_slice($visit->photos, 0, $retained), array_slice($saved->photos, 0, $retained));
        $this->assertSame('Updated notes', $saved->notes);
        $this->assertSame($notesOnly ? '2026-10-01 09:00:00' : '2026-10-07 10:00:00', $saved->visited_at->format('Y-m-d H:i:s'));
        $this->assertSame($visit->recorded_by_user_id, $saved->recorded_by_user_id);
        $this->assertSame($visit->created_at->toISOString(), $saved->created_at->toISOString());
        $this->assertCount($retained + $new, $disk->allFiles());
        foreach (array_slice($visit->photos, 0, $retained) as $i => $photo) {
            $this->assertSame('historical bytes '.$i, $disk->get($photo['path']));
        }
        foreach (array_slice($visit->photos, $retained) as $photo) {
            $disk->assertMissing($photo['path']);
        }
    }

    public static function historicalCounts(): array
    {
        return [
            'retain ten' => [10, 0, false], 'retain eight' => [8, 0, false], 'retain five' => [5, 0, false],
            'reduce to four add one' => [4, 1, false], 'ten plus one' => [10, 1, false],
            'eight plus one' => [8, 1, false], 'omitted retains ten' => [10, 0, true],
            'omitted ten plus one' => [10, 1, true],
        ];
    }

    public function test_omitted_retention_on_normal_visit_still_counts_existing_photos(): void
    {
        [$household, $visit, $disk] = $this->context(5);
        $payload = $this->payload($household, $visit, 0, 1);
        unset($payload['existing_photos']);
        $this->assertRejectedWithoutWrites($payload, $visit, $disk);
    }

    public function test_repeated_retained_paths_count_actual_attachments_not_request_entries(): void
    {
        [$household, $visit] = $this->context(1);
        $payload = $this->payload($household, $visit, 1, 4);
        $payload['existing_photos'] = array_fill(0, 10, $visit->photos[0]['path']);
        $this->postJson('/api/mobile/sync', ['field_visits' => [$payload]])->assertOk()->assertJsonPath('status', 'success');
        $this->assertCount(5, $visit->fresh()->photos);
    }

    public function test_historical_allowance_cannot_retain_foreign_or_invented_paths(): void
    {
        [$household, $visit, $disk] = $this->context(10);
        $foreignPath = 'visit-photos/foreign.jpg';
        $disk->put($foreignPath, 'foreign bytes');
        FieldVisit::create([
            'mobile_uuid' => (string) Str::uuid(),
            'household_id' => $household->id, 'recorded_by_user_id' => $visit->recorded_by_user_id,
            'visited_at' => now(), 'photos' => [['path' => $foreignPath]],
        ]);
        foreach ([$foreignPath, 'visit-photos/invented.jpg'] as $path) {
            $payload = $this->payload($household, $visit, 10, 0);
            $payload['existing_photos'][] = $path;
            $original = $visit->getAttributes();
            $files = $disk->allFiles();
            $this->postJson('/api/mobile/sync', ['field_visits' => [$payload]])->assertOk()
                ->assertJsonPath('status', 'failed')->assertJsonCount(0, 'resolved_records.field_visits')
                ->assertJsonPath('failed_records.0.message', 'One or more retained photos are not attached to this visit.');
            $this->assertSame($original, $visit->fresh()->getAttributes());
            $this->assertSame($files, $disk->allFiles());
            $this->assertSame('foreign bytes', $disk->get($foreignPath));
        }
    }

    public function test_after_historical_reduction_normal_limit_applies_on_next_update(): void
    {
        [$household, $visit, $disk] = $this->context(10);
        $this->postJson('/api/mobile/sync', ['field_visits' => [$this->payload($household, $visit, 5, 0)]])
            ->assertOk()->assertJsonPath('status', 'success');
        $visit->refresh();
        $this->assertRejectedWithoutWrites($this->payload($household, $visit, 5, 1), $visit, $disk);
    }
}
