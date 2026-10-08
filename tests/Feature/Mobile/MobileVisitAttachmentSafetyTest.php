<?php

namespace Tests\Feature\Mobile;

use App\Models\{Barangay, FieldVisit, Household, Purok, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MobileVisitAttachmentSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const RETAINED_PATH = 'visit-photos/2026/10/retained.jpg';

    private function context(): array
    {
        $disk = Storage::fake('local');
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $bhw = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $barangay->id,
            'assigned_purok_id' => $purok->id, 'approval_status' => 'approved', 'is_active' => true,
        ]);
        $recorder = User::factory()->create([
            'role' => 'bhw', 'assigned_barangay_id' => $barangay->id, 'assigned_purok_id' => $purok->id,
        ]);
        $household = Household::create([
            'purok_id' => $purok->id, 'mobile_uuid' => (string) Str::uuid(),
            'household_no' => 'PHOTO-1', 'household_address' => 'Test address',
            'is_active' => true, 'is_social_aid_beneficiary' => false,
        ]);
        $disk->put(self::RETAINED_PATH, 'historical bytes');
        $visit = FieldVisit::create([
            'mobile_uuid' => (string) Str::uuid(), 'household_id' => $household->id,
            'recorded_by_user_id' => $recorder->id, 'visited_at' => '2026-10-01 09:00:00',
            'notes' => 'Original notes', 'photos' => [['path' => self::RETAINED_PATH]], 'source' => 'mobile',
        ]);
        Sanctum::actingAs($bhw, ['mobile']);

        return [$bhw, $household, $visit->refresh(), $disk];
    }

    private function payload(Household $household, ?FieldVisit $visit = null, int $count = 2): array
    {
        $photos = [];
        for ($i = 0; $i < $count; $i++) {
            $photos[] = [
                'data' => base64_encode('new photo '.$i), 'file_name' => 'photo-'.$i.'.png',
                'mime_type' => 'image/png', 'captured_at' => '2026-10-07T09:00:00+08:00',
            ];
        }

        return array_merge($visit ? ['id' => $visit->id, 'existing_photos' => [self::RETAINED_PATH]] : [], [
            'mobile_uuid' => $visit?->mobile_uuid ?? (string) Str::uuid(), 'household_id' => $household->id,
            'visited_at' => '2026-10-07 09:00:00', 'notes' => 'Updated notes', 'photos' => $photos,
        ]);
    }

    private function controlledDisk($disk, string $failure, int $failAt, array &$written, array &$deleted): void
    {
        $attempt = 0;
        $proxy = Mockery::mock($disk);
        $proxy->shouldReceive('put')->andReturnUsing(function ($path, $bytes) use ($disk, $failure, $failAt, &$attempt, &$written) {
            if (++$attempt === $failAt) {
                if ($failure === 'throw') {
                    throw new \RuntimeException('Internal /private/storage/path must not be exposed');
                }

                return false;
            }
            $written[] = $path;

            return $disk->put($path, $bytes);
        });
        $proxy->shouldReceive('delete')->andReturnUsing(function ($path) use ($disk, &$deleted) {
            $deleted[] = $path;

            return $disk->delete($path);
        });
        Storage::shouldReceive('disk')->with('local')->andReturn($proxy);
    }

    private function assertRetainedOnly($disk, FieldVisit $visit, array $original): void
    {
        $this->assertSame([self::RETAINED_PATH], $disk->allFiles());
        $this->assertSame('historical bytes', $disk->get(self::RETAINED_PATH));
        $this->assertSame($original, $visit->fresh()->getAttributes());
        $this->assertDatabaseCount('field_visits', 1);
    }

    #[DataProvider('successfulPhotos')]
    public function test_successful_photo_writes_have_correct_metadata_and_visit_association(int $count, bool $creating): void
    {
        [$bhw, $household, $visit, $disk] = $this->context();
        $payload = $this->payload($household, $creating ? null : $visit, $count);
        $this->postJson('/api/mobile/sync', ['field_visits' => [$payload]])->assertOk()
            ->assertJsonPath('status', 'success')->assertJsonCount(1, 'resolved_records.field_visits');
        $saved = FieldVisit::where('mobile_uuid', $payload['mobile_uuid'])->firstOrFail();
        $this->assertSame($household->id, $saved->household_id);
        $this->assertSame($creating ? $bhw->id : $visit->recorded_by_user_id, $saved->recorded_by_user_id);
        $this->assertCount($count + ($creating ? 0 : 1), $saved->photos);
        foreach (array_slice($saved->photos, $creating ? 0 : 1) as $i => $photo) {
            $this->assertStringStartsWith('visit-photos/', $photo['path']);
            $this->assertStringEndsWith('.png', $photo['path']);
            $this->assertSame('new photo '.$i, $disk->get($photo['path']));
            $this->assertSame('photo-'.$i.'.png', $photo['file_name']);
            $this->assertSame('image/png', $photo['mime_type']);
            $this->assertSame(strlen('new photo '.$i), $photo['file_size_bytes']);
            $this->assertSame($payload['photos'][$i]['captured_at'], $photo['captured_at']);
        }
        $this->assertSame('historical bytes', $disk->get(self::RETAINED_PATH));
    }

    public static function successfulPhotos(): array
    {
        return ['single new' => [1, true], 'multiple new' => [2, true], 'single retained' => [1, false], 'multiple retained' => [2, false]];
    }

    #[DataProvider('failedPhotos')]
    public function test_photo_failure_has_no_acknowledgment_or_orphans_and_preserves_existing_visit(string $failure, int $failAt, bool $creating): void
    {
        [, $household, $visit, $disk] = $this->context();
        $original = $visit->getAttributes();
        $payload = $this->payload($household, $creating ? null : $visit);
        $written = $deleted = [];
        $this->controlledDisk($disk, $failure, $failAt, $written, $deleted);
        if ($failure === 'decode') {
            $payload['photos'][1]['data'] = 'invalid%%%base64';
        }
        $this->postJson('/api/mobile/sync', ['field_visits' => [$payload]])->assertOk()
            ->assertJsonPath('status', 'failed')->assertJsonPath('success', false)
            ->assertJsonPath('records_synced', 0)->assertJsonCount(0, 'resolved_records.field_visits')
            ->assertJsonPath('failed_records.0.message', 'Visit photo upload failed. Please retry.')
            ->assertDontSee('/private/storage/path');
        $this->assertSame($written, $deleted, 'Only successful new writes are deleted, once each.');
        $this->assertCount($failAt - 1, $written);
        $this->assertRetainedOnly($disk, $visit, $original);
    }

    public static function failedPhotos(): array
    {
        $cases = [];
        foreach ([true, false] as $creating) {
            foreach ([['false', 1], ['false', 2], ['throw', 1], ['throw', 2], ['decode', 2]] as [$failure, $at]) {
                $cases[($creating ? 'new' : 'existing')." $failure at $at"] = [$failure, $at, $creating];
            }
        }

        return $cases;
    }

    #[DataProvider('visitModes')]
    public function test_database_failure_rolls_back_visit_and_cleans_new_files_exactly_once(bool $creating): void
    {
        [, $household, $visit, $disk] = $this->context();
        $original = $visit->getAttributes();
        $written = $deleted = [];
        $this->controlledDisk($disk, 'none', 0, $written, $deleted);
        $connection = DB::connection();
        $database = Mockery::mock(DB::getFacadeRoot());
        DB::swap($database);
        $database->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) use ($connection) {
            return $connection->transaction(function () use ($callback) {
                $callback();
                throw new \RuntimeException('Internal database failure');
            });
        });
        $this->postJson('/api/mobile/sync', ['field_visits' => [$this->payload($household, $creating ? null : $visit)]])
            ->assertOk()->assertJsonPath('status', 'failed')->assertJsonCount(0, 'resolved_records.field_visits')
            ->assertJsonPath('failed_records.0.message', 'Field visit update failed. Please retry.');
        $this->assertCount(2, $written);
        $this->assertSame($written, $deleted);
        $this->assertRetainedOnly($disk, $visit, $original);
    }

    public static function visitModes(): array
    {
        return ['new' => [true], 'existing' => [false]];
    }

    public function test_failed_visit_does_not_prevent_an_unrelated_valid_visit_in_the_same_batch(): void
    {
        [, $household, $visit, $disk] = $this->context();
        $original = $visit->getAttributes();
        $written = $deleted = [];
        $this->controlledDisk($disk, 'false', 1, $written, $deleted);
        $valid = $this->payload($household, null, 0);
        $this->postJson('/api/mobile/sync', ['field_visits' => [$this->payload($household, $visit), $valid]])
            ->assertOk()->assertJsonPath('status', 'partial')->assertJsonPath('records_synced', 1)
            ->assertJsonCount(1, 'resolved_records.field_visits')
            ->assertJsonPath('resolved_records.field_visits.0.mobile_uuid', $valid['mobile_uuid'])
            ->assertJsonPath('failed_records.0.index', 0);
        $this->assertSame($original, $visit->fresh()->getAttributes());
        $this->assertDatabaseCount('field_visits', 2);
        $this->assertSame([self::RETAINED_PATH], $disk->allFiles());
    }

    public function test_retained_path_from_another_visit_is_rejected_before_writing(): void
    {
        [, $household, $visit, $disk] = $this->context();
        $original = $visit->getAttributes();
        $payload = $this->payload($household);
        $payload['existing_photos'] = [self::RETAINED_PATH];
        $this->postJson('/api/mobile/sync', ['field_visits' => [$payload]])->assertOk()
            ->assertJsonPath('status', 'failed')->assertJsonCount(0, 'resolved_records.field_visits')
            ->assertJsonPath('failed_records.0.message', 'One or more retained photos are not attached to this visit.');
        $this->assertRetainedOnly($disk, $visit, $original);
    }

    public function test_retry_after_partial_photo_failure_creates_one_visit_with_only_successful_retry_photos(): void
    {
        [, $household, $visit, $disk] = $this->context();
        $original = $visit->getAttributes();
        $payload = $this->payload($household);
        $written = $deleted = [];
        $this->controlledDisk($disk, 'false', 2, $written, $deleted);
        $this->postJson('/api/mobile/sync', ['field_visits' => [$payload]])->assertOk()
            ->assertJsonPath('status', 'failed')->assertJsonCount(0, 'resolved_records.field_visits');
        $this->assertRetainedOnly($disk, $visit, $original);
        $this->postJson('/api/mobile/sync', ['field_visits' => [$payload]])->assertOk()
            ->assertJsonPath('status', 'success')->assertJsonCount(1, 'resolved_records.field_visits');
        $saved = FieldVisit::where('mobile_uuid', $payload['mobile_uuid'])->firstOrFail();
        $this->assertCount(2, $saved->photos);
        $this->assertSame(array_slice($written, 1), array_column($saved->photos, 'path'));
        $this->assertSame([$written[0]], $deleted);
        $this->assertCount(3, $disk->allFiles());
        $this->assertSame('historical bytes', $disk->get(self::RETAINED_PATH));
        $this->assertSame($original, $visit->fresh()->getAttributes());
        $this->assertDatabaseCount('field_visits', 2);
    }

    public function test_missing_new_visit_date_preserves_existing_post_storage_cleanup(): void
    {
        [, $household, $visit, $disk] = $this->context();
        $original = $visit->getAttributes();
        $payload = $this->payload($household);
        unset($payload['visited_at']);
        $written = $deleted = [];
        $this->controlledDisk($disk, 'none', 0, $written, $deleted);
        $this->postJson('/api/mobile/sync', ['field_visits' => [$payload]])->assertOk()
            ->assertJsonPath('status', 'failed')->assertJsonCount(0, 'resolved_records.field_visits');
        $this->assertCount(2, $written);
        $this->assertSame($written, $deleted);
        $this->assertRetainedOnly($disk, $visit, $original);
    }
}
