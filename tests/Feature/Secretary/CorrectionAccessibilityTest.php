<?php

namespace Tests\Feature\Secretary;

use App\Models\Barangay;
use App\Models\Household;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CorrectionAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public static function reviews(): array
    {
        return [['resident', false], ['resident', true], ['household', false], ['household', true]];
    }

    #[DataProvider('reviews')]
    public function test_labels_errors_and_submission_contract(string $type, bool $invalid): void
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $household = Household::create(['purok_id' => $purok->id, 'household_no' => '7', 'household_address' => 'Fixture address', 'is_active' => true]);
        $resident = Resident::create(['household_id' => $household->id, 'first_name' => 'Original', 'last_name' => 'Resident',
            'birth_date' => '2000-01-01', 'birth_place' => 'Tubigon', 'sex' => 'Female', 'civil_status' => 'Single',
            'citizenship' => 'Filipino', 'relationship_to_head' => 'Child', 'resident_status' => 'active', 'is_active' => true]);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);
        $request = ProfileUpdateRequest::create(['barangay_id' => $barangay->id, 'submitted_by_user_id' => $secretary->id,
            'subject_type' => $type, 'subject_id' => $type === 'resident' ? $resident->id : $household->id,
            'request_status' => ProfileUpdateRequest::STATUS_PENDING, 'current_snapshot' => [],
            'proposed_changes' => ['first_name' => 'Proposed', 'household_no' => '8'], 'request_reason' => 'Fixture correction']);
        $before = $request->fresh()->getAttributes();
        $fields = $type === 'resident'
            ? ['household_id', 'philsys_card_no', 'last_name', 'first_name', 'middle_name', 'suffix', 'birth_date', 'birth_place',
                'sex', 'civil_status', 'citizenship', 'religion', 'contact_number', 'email_address', 'relationship_to_head',
                'set_as_household_head', 'resident_status', 'moved_in_at', 'moved_out_at', 'date_of_death', 'is_active', 'status_notes', 'review_notes']
            : ['purok_id', 'household_no', 'household_address', 'drinking_water_source', 'sanitary_toilet_type', 'head_resident_id',
                'has_sanitary_toilet', 'is_social_aid_beneficiary', 'is_active', 'review_notes'];
        $messages = $invalid ? array_combine($fields, array_map(fn ($field) => 'Invalid '.$field, $fields)) : [];
        $errors = (new ViewErrorBag)->put('default', new MessageBag($messages));
        $response = $this->actingAs($secretary)->withSession([
            '_old_input' => ['first_name' => 'Old first name', 'household_no' => 'OLD-7']])
            ->get(route('secretary.update-requests.edit', $request))->assertOk();
        $response->assertSee('Apply Approved Changes')->assertSee('Reject Correction Request');
        // Render the authorized controller view with a deterministic Laravel validation bag.
        view()->share('errors', $errors);
        $html = $response->original->with('errors', $errors)->render();
        $invalid ? $this->assertStringContainsString('Please review the correction approval form.', $html)
            : $this->assertStringNotContainsString('Please review the correction approval form.', $html);
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        foreach ($fields as $field) {
            $id = 'correction_'.$field;
            $controls = $xpath->query('//*[@id="'.$id.'"]');
            $this->assertCount(1, $controls, $field);
            $control = $controls->item(0);
            $this->assertCount(1, $xpath->query('//label[@for="'.$id.'"]'), $field);
            if (in_array($field, ['household_id', 'head_resident_id'], true)) {
                $this->assertCount(1, $xpath->query('//input[@type="hidden" and @name="'.$field.'"]'));
            } else {
                $this->assertSame($field, $control->getAttribute('name'));
            }
            $this->assertSame($invalid ? 'true' : 'false', $control->getAttribute('aria-invalid'));
            if ($invalid) {
                $this->assertSame($id.'-error', $control->getAttribute('aria-describedby'));
                $this->assertCount(1, $xpath->query('//*[@id="'.$id.'-error"]'));
                $this->assertStringContainsString('Invalid '.$field, $xpath->query('//*[@id="'.$id.'-error"]')->item(0)->textContent);
            } else {
                $this->assertFalse($control->hasAttribute('aria-describedby'));
                $this->assertCount(0, $xpath->query('//*[@id="'.$id.'-error"]'));
            }
        }
        foreach (['approve', 'reject'] as $action) {
            $forms = $xpath->query('//form[@action="'.route('secretary.update-requests.'.$action, $request).'"]');
            $this->assertCount(1, $forms);
            $form = $forms->item(0);
            $this->assertSame('POST', $form->getAttribute('method'));
            $this->assertCount(1, $xpath->query('.//input[@name="_method" and @value="PATCH"]', $form));
            $this->assertCount(1, $xpath->query('.//input[@name="_token"]', $form));
        }
        $this->assertSame($type === 'resident' ? 'Old first name' : 'OLD-7',
            $xpath->query('//*[@id="correction_'.($type === 'resident' ? 'first_name' : 'household_no').'"]')->item(0)->getAttribute('value'));
        $this->assertSame($before, $request->fresh()->getAttributes());
    }
}
