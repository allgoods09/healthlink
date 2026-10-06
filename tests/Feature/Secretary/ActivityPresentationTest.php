<?php

namespace Tests\Feature\Secretary;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretary_index_preserves_meaning_and_controls_without_prominent_technical_data(): void
    {
        [$secretary, $audit] = $this->activity();
        $original = $audit->fresh()->getRawOriginal();
        $response = $this->actingAs($secretary)->get(route('secretary.activity.index'))->assertOk();

        foreach (['Barangay Activity Feed', $secretary->name, 'Record Updated', 'Registry detail updated', 'Resident', 'Feb 10, 2026 10:45 AM', 'Description or record type...'] as $text) {
            $response->assertSee($text);
        }
        $response->assertDontSee('198.51.100.17')->assertDontSee('IP Address');
        $dom = $this->xpath($response->getContent());
        $headings = [];
        foreach ($dom->query('//table/thead/tr/th') as $heading) {
            $headings[] = trim($heading->textContent);
        }
        $this->assertSame(['Date & Time', 'Actor', 'Action', 'Description', 'Record Type', 'Actions'], $headings);
        foreach (['search', 'event_type', 'user_id', 'date_from', 'date_to'] as $name) {
            $this->assertSame(1, $dom->query('//*[@name="'.$name.'"]')->length);
        }
        $this->assertSame(1, $dom->query('//form[@method="GET"]')->length);
        $this->assertSame($original, $audit->fresh()->getRawOriginal());
    }

    public function test_secretary_empty_search_uses_activity_wording(): void
    {
        [$secretary] = $this->activity();
        $this->actingAs($secretary)->get(route('secretary.activity.index', ['search' => 'unmatched-example']))
            ->assertOk()->assertSee('No activity found.')->assertDontSee('No audit logs found.');
    }

    public function test_secretary_detail_keeps_meaning_outside_a_closed_native_technical_disclosure(): void
    {
        [$secretary, $audit] = $this->activity();
        $original = $audit->fresh()->getRawOriginal();
        $response = $this->actingAs($secretary)->get(route('secretary.activity.show', $audit))->assertOk();
        $response->assertSee('Activity Entry')->assertSee('Activity details')->assertSee('Back to Activity');
        $dom = $this->xpath($response->getContent());
        $this->assertSame(1, $dom->query('//main//details')->length);
        $this->assertSame(0, $dom->query('//main//details[@open or @x-data]')->length);
        $this->assertSame('Technical details', trim($dom->query('//main//details/summary')->item(0)->textContent));
        $primary = $dom->query('//main//dl[not(ancestor::details)]')->item(0)->textContent;
        foreach ([$secretary->name, 'Record Updated', 'Registry detail updated', 'Resident', 'Feb 10, 2026 10:45 AM', 'Philippine time'] as $text) {
            $this->assertStringContainsString($text, $primary);
        }
        $technical = $dom->query('//main//details')->item(0)->textContent;
        foreach (['198.51.100.17', 'Synthetic browser agent', '#'.$audit->model_id, 'Previous family', 'Corrected family', 'review-context'] as $text) {
            $this->assertStringContainsString($text, $technical);
            $this->assertStringNotContainsString($text, $primary);
        }
        $this->assertSame(3, $dom->query('//main//details//pre')->length);
        $this->assertSame(0, $dom->query('//main//pre[not(ancestor::details)]')->length);
        $this->assertSame($original, $audit->fresh()->getRawOriginal());
    }

    public function test_admin_retains_existing_forensic_presentation_and_timestamp_semantics(): void
    {
        [, $audit] = $this->activity();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.audit.index'))->assertOk()
            ->assertSee('IP Address')->assertSee('198.51.100.17')->assertSee('2026-02-10 02:45:37')
            ->assertSee('Description, IP...');
        $response = $this->get(route('admin.audit.show', $audit))->assertOk();
        foreach (['Event Details', 'Back to Logs', 'Data Changes', '198.51.100.17', 'Synthetic browser agent', '#'.$audit->model_id, 'Previous family', 'Corrected family', 'review-context', 'February 10, 2026 02:45:37 AM'] as $text) {
            $response->assertSee($text);
        }
        $dom = $this->xpath($response->getContent());
        $this->assertSame(0, $dom->query('//main//details')->length);
        $this->assertSame(3, $dom->query('//main//pre')->length);
    }

    public function test_foreign_record_is_not_accessible_even_when_its_actor_is_the_local_secretary(): void
    {
        [$secretary] = $this->activity();
        [, $foreign] = $this->activity();
        $foreign->update(['user_id' => $secretary->id, 'event_description' => 'Foreign registry activity']);
        $response = $this->actingAs($secretary)->get(route('secretary.activity.index', ['search' => 'Foreign registry activity']))
            ->assertOk()->assertSee('No activity found.');
        $this->assertStringNotContainsString('Foreign registry activity', $this->xpath($response->getContent())->query('//table/tbody')->item(0)->textContent);
        $this->get(route('secretary.activity.show', $foreign))->assertNotFound();
    }

    public function test_unauthorized_role_cannot_use_secretary_or_admin_audit_routes(): void
    {
        [$secretary, $audit] = $this->activity();
        $bhw = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => $secretary->assigned_barangay_id]);
        $this->actingAs($bhw);
        foreach (['secretary.activity', 'admin.audit'] as $prefix) {
            $this->get(route($prefix.'.index'))->assertForbidden();
            $this->get(route($prefix.'.show', $audit))->assertForbidden();
        }
    }

    public function test_secretary_csv_contract_remains_curated_with_original_timestamp_format(): void
    {
        [$secretary] = $this->activity();
        $response = $this->actingAs($secretary)->get(route('secretary.activity.export', ['format' => 'csv', 'search' => 'Registry detail updated']))->assertOk();
        $csv = $response->streamedContent();
        $lines = preg_split('/\r?\n/', trim($csv));
        $this->assertSame(['User', 'Event Type', 'Description', 'Record Type', 'Timestamp'], str_getcsv(ltrim($lines[0], "\xEF\xBB\xBF")));
        $this->assertSame([$secretary->name, 'Record Updated', 'Registry detail updated', 'Resident', '2026-02-10 02:45:37'], str_getcsv($lines[1]));
        foreach (['198.51.100.17', 'Synthetic browser agent', 'Previous family', 'Corrected family', 'review-context'] as $text) {
            $this->assertStringNotContainsString($text, $csv);
        }
    }

    private function activity(): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $household = Household::query()->create(['purok_id' => $purok->id, 'household_no' => '001', 'household_address' => 'Synthetic address', 'is_active' => true]);
        $resident = Resident::query()->create([
            'household_id' => $household->id,
            'first_name' => 'Juana', 'last_name' => 'Example',
            'birth_date' => '1990-01-01', 'birth_place' => 'Tubigon, Bohol',
            'sex' => 'Female', 'civil_status' => 'Single', 'citizenship' => 'Filipino',
            'relationship_to_head' => 'Other Relative', 'resident_status' => Resident::STATUS_ACTIVE, 'is_active' => true,
        ]);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id, 'assigned_purok_id' => null]);
        $audit = AuditLog::query()->create([
            'user_id' => $secretary->id, 'event_type' => 'updated', 'event_description' => 'Registry detail updated',
            'model_type' => Resident::class, 'model_id' => $resident->id,
            'ip_address' => '198.51.100.17', 'user_agent' => 'Synthetic browser agent',
            'old_values' => ['last_name' => 'Previous family'], 'new_values' => ['last_name' => 'Corrected family'],
            'metadata' => ['context' => 'review-context'],
        ]);
        $audit->forceFill(['created_at' => '2026-02-10 02:45:37', 'updated_at' => '2026-02-10 02:45:37'])->save();

        return [$secretary, $audit];
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML($html);

        return new DOMXPath($document);
    }
}
