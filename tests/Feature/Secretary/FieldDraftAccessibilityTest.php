<?php

namespace Tests\Feature\Secretary;

use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\Household;
use App\Models\HouseholdDraft;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentDraft;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FieldDraftAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private const RESIDENT_FIELDS = [
        'philsys_card_no', 'relationship_to_head', 'last_name', 'first_name', 'middle_name', 'suffix',
        'birth_date', 'birth_place', 'sex', 'civil_status', 'citizenship', 'religion', 'contact_number', 'email_address',
    ];

    private const REQUIRED_FIELDS = [
        'relationship_to_head', 'last_name', 'first_name', 'birth_date', 'birth_place', 'sex', 'civil_status', 'citizenship',
    ];

    private const PACKAGE_ERRORS = ['purok_id', 'household_no', 'household_address', 'head_draft_id'];

    public static function reviews(): array
    {
        return [
            'new household healthy' => [false, null],
            'new household first row errors' => [false, 0],
            'new household second row errors' => [false, 1],
            'existing household healthy' => [true, null],
            'existing household first row errors' => [true, 0],
            'existing household second row errors' => [true, 1],
        ];
    }

    #[DataProvider('reviews')]
    public function test_review_labels_nested_errors_and_submission_contract(bool $existingHousehold, ?int $invalidRow): void
    {
        [$secretary, $draft, $purok] = $this->draftContext($existingHousehold);
        $rows = $draft->residentDrafts;
        $oldRows = [];
        foreach ($rows as $index => $row) {
            $oldRows[$index] = $row->only(self::RESIDENT_FIELDS);
            $oldRows[$index]['draft_id'] = $row->id;
            $oldRows[$index]['first_name'] = 'Restored name '.$index;
            $oldRows[$index]['birth_date'] = '2010-02-03';
            $oldRows[$index]['sex'] = $index === 0 ? 'Female' : 'Male';
        }
        $messages = [];
        if ($invalidRow !== null) {
            foreach (array_merge(self::PACKAGE_ERRORS, ['residents']) as $key) {
                $messages[$key] = 'Invalid '.$key;
            }
            foreach (self::RESIDENT_FIELDS as $field) {
                $key = "residents.{$invalidRow}.{$field}";
                $messages[$key] = 'Invalid '.$key;
            }
        }
        $before = [$draft->getAttributes(), $rows->map->getAttributes()->all(),
            Household::count(), Resident::count(), AuditLog::count(), $draft->targetHousehold?->getAttributes()];
        $response = $this->actingAs($secretary)->withSession(['_old_input' => [
            'residents' => $oldRows, 'purok_id' => $purok->id, 'household_no' => 'RESTORED-7',
            'household_address' => 'Restored address', 'head_draft_id' => $rows[1]->id,
            'verification_notes' => 'Restored secretary notes', 'has_sanitary_toilet' => '0', 'is_social_aid_beneficiary' => '1',
        ]])->get(route('secretary.drafts.edit', $draft))->assertOk()->assertViewIs('secretary.drafts.edit');

        // Match the established correction test: render the authorized view with a deterministic error bag.
        $errors = (new ViewErrorBag)->put('default', new MessageBag($messages));
        view()->share('errors', $errors);
        $html = $response->original->with('errors', $errors)->render();
        $xpath = $this->xpath($html);
        $invalidRow === null
            ? $this->assertStringNotContainsString('Please review the draft approval form.', $html)
            : $this->assertStringContainsString('Please review the draft approval form.', $html);
        foreach ($messages as $message) {
            $this->assertStringContainsString($message, $html);
        }

        $approval = $xpath->query('//form[@action="'.route('secretary.drafts.approve', $draft).'"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $approval);
        $ids = [];
        foreach ($xpath->query('.//*[@id]', $approval) as $element) {
            $ids[] = $element->getAttribute('id');
        }
        $this->assertSame(count($ids), count(array_unique($ids)));
        foreach ($rows as $index => $row) {
            $hidden = $xpath->query('.//input[@type="hidden" and @name="residents['.$index.'][draft_id]"]', $approval);
            $this->assertCount(1, $hidden);
            $this->assertSame((string) $row->id, $hidden->item(0)->getAttribute('value'));
            foreach (self::RESIDENT_FIELDS as $field) {
                $id = "draft_resident_{$index}_{$field}";
                $controls = $xpath->query('//*[@id="'.$id.'"]');
                $this->assertCount(1, $controls);
                $control = $controls->item(0);
                $this->assertSame("residents[{$index}][{$field}]", $control->getAttribute('name'));
                $this->assertCount(1, $xpath->query('//label[@for="'.$id.'"]'));
                $this->assertSame(in_array($field, self::REQUIRED_FIELDS, true), $control->hasAttribute('required'));
                $this->assertErrorAssociation($xpath, $control, $id, $invalidRow === $index, "residents.{$index}.{$field}");
                $value = $field === 'sex'
                    ? $xpath->query('./option[@selected]', $control)->item(0)->getAttribute('value')
                    : $control->getAttribute('value');
                $this->assertSame((string) $oldRows[$index][$field], $value);
            }
        }
        foreach (self::PACKAGE_ERRORS as $field) {
            if ($existingHousehold) {
                $this->assertCount(0, $xpath->query('//*[@id="'.$field.'"]'));
            } else {
                $control = $xpath->query('//*[@id="'.$field.'"]')->item(0);
                $this->assertSame($field, $control->getAttribute('name'));
                $this->assertCount(1, $xpath->query('//label[@for="'.$field.'"]'));
                $this->assertSame($field !== 'head_draft_id', $control->hasAttribute('required'));
                $this->assertErrorAssociation($xpath, $control, $field, $invalidRow !== null, $field);
            }
        }
        foreach (['drinking_water_source', 'sanitary_toilet_type', 'has_sanitary_toilet', 'is_social_aid_beneficiary'] as $field) {
            $controls = $xpath->query('.//*[@name="'.$field.'" and not(@type="hidden")]', $approval);
            $this->assertCount($existingHousehold ? 0 : 1, $controls);
            if (! $existingHousehold && str_starts_with($field, 'has_')) {
                $this->assertCount(1, $xpath->query('ancestor::label', $controls->item(0)));
                $this->assertFalse($controls->item(0)->hasAttribute('checked'));
            }
            if (! $existingHousehold && $field === 'is_social_aid_beneficiary') {
                $this->assertCount(1, $xpath->query('ancestor::label', $controls->item(0)));
                $this->assertTrue($controls->item(0)->hasAttribute('checked'));
            }
        }
        if ($existingHousehold) {
            foreach (['purok_id' => $purok->id, 'household_no' => $draft->targetHousehold->household_no] as $field => $value) {
                $this->assertSame((string) $value, $xpath->query('.//input[@type="hidden" and @name="'.$field.'"]', $approval)->item(0)->getAttribute('value'));
            }
        } else {
            $this->assertSame('RESTORED-7', $xpath->query('//*[@id="household_no"]')->item(0)->getAttribute('value'));
            $this->assertSame('Restored address', $xpath->query('//*[@id="household_address"]')->item(0)->textContent);
            $this->assertSame((string) $purok->id, $xpath->query('//*[@id="purok_id"]/option[@selected]')->item(0)->getAttribute('value'));
            $this->assertSame((string) $rows[1]->id, $xpath->query('//*[@id="head_draft_id"]/option[@selected]')->item(0)->getAttribute('value'));
        }
        $this->assertSame('Restored secretary notes', $xpath->query('//*[@id="verification_notes"]')->item(0)->textContent);
        foreach (['approve', 'reject'] as $action) {
            $forms = $xpath->query('//form[@action="'.route('secretary.drafts.'.$action, $draft).'"]');
            $this->assertCount(1, $forms);
            $form = $forms->item(0);
            $this->assertSame('POST', $form->getAttribute('method'));
            $this->assertCount(1, $xpath->query('.//input[@name="_method" and @value="PATCH"]', $form));
            $this->assertCount(1, $xpath->query('.//input[@name="_token"]', $form));
            $button = $xpath->query('.//button[@type="submit"]', $form)->item(0);
            $this->assertSame($action === 'approve' ? 'Approve Draft Package' : 'Reject Draft Package', trim($button->textContent));
            $this->assertStringContainsString('rounded-full', $button->getAttribute('class'));
            if ($action === 'reject') {
                $this->assertSame('', $xpath->query('.//input[@type="hidden" and @name="review_notes"]', $form)->item(0)->getAttribute('value'));
            }
        }
        $this->assertSame($before, [$draft->fresh()->getAttributes(), $draft->residentDrafts()->get()->map->getAttributes()->all(),
            Household::count(), Resident::count(), AuditLog::count(), $draft->targetHousehold?->fresh()->getAttributes()]);
    }

    public function test_nested_validation_failure_keeps_old_values_and_draft_pending(): void
    {
        [$secretary, $draft, $purok] = $this->draftContext(false);
        $payload = ['purok_id' => $purok->id, 'household_no' => 'RESTORED-7', 'household_address' => 'Restored address',
            'residents' => $draft->residentDrafts->map(fn (ResidentDraft $row) => array_merge($row->only(self::RESIDENT_FIELDS),
                ['draft_id' => $row->id, 'birth_date' => $row->birth_date->toDateString()]))->all()];
        $payload['residents'][0]['last_name'] = '';
        $payload['residents'][0]['first_name'] = 'Restored after failure';
        $this->actingAs($secretary)->from(route('secretary.drafts.edit', $draft))
            ->patch(route('secretary.drafts.approve', $draft), $payload)
            ->assertRedirect(route('secretary.drafts.edit', $draft))->assertSessionHasErrors('residents.0.last_name')
            ->assertSessionHasInput('residents.0.first_name', 'Restored after failure');
        $this->get(route('secretary.drafts.edit', $draft))->assertOk()->assertSee('Restored after failure');
        $this->assertSame(HouseholdDraft::STATUS_PENDING, $draft->fresh()->draft_status);
        $this->assertSame(0, Household::count());
        $this->assertSame(0, Resident::count());
    }

    private function assertErrorAssociation(DOMXPath $xpath, DOMElement $control, string $id, bool $invalid, string $key): void
    {
        $this->assertSame($invalid ? 'true' : 'false', $control->getAttribute('aria-invalid'));
        $errors = $xpath->query('//*[@id="'.$id.'-error"]');
        $this->assertCount($invalid ? 1 : 0, $errors);
        if ($invalid) {
            $this->assertSame($id.'-error', $control->getAttribute('aria-describedby'));
            $this->assertStringContainsString('Invalid '.$key, $errors->item(0)->textContent);
            $this->assertSame($id.'-error', $xpath->query('following-sibling::*[1]', $control)->item(0)->getAttribute('id'));
        } else {
            $this->assertFalse($control->hasAttribute('aria-describedby'));
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }

    private function draftContext(bool $existingHousehold): array
    {
        $barangay = Barangay::factory()->create();
        $purok = Purok::factory()->create(['barangay_id' => $barangay->id]);
        $secretary = User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => $barangay->id]);
        $bhw = User::factory()->create(['role' => 'bhw', 'assigned_barangay_id' => $barangay->id, 'assigned_purok_id' => $purok->id]);
        $household = $existingHousehold ? Household::create(['purok_id' => $purok->id, 'household_no' => '7',
            'household_address' => 'Existing address', 'is_active' => true]) : null;
        $draft = HouseholdDraft::create(['barangay_id' => $barangay->id, 'purok_id' => $purok->id,
            'submitted_by_user_id' => $bhw->id, 'target_household_id' => $household?->id, 'proposed_household_no' => '8',
            'household_address' => 'Draft address', 'has_sanitary_toilet' => true, 'is_social_aid_beneficiary' => false,
            'draft_status' => HouseholdDraft::STATUS_PENDING]);
        foreach (['Male', 'Female'] as $index => $sex) {
            ResidentDraft::create(['household_draft_id' => $draft->id, 'last_name' => 'Fixture', 'first_name' => 'Child '.$index,
                'birth_date' => '2012-03-04', 'birth_place' => 'Tubigon', 'sex' => $sex, 'civil_status' => 'Single',
                'citizenship' => 'Filipino', 'relationship_to_head' => 'Child', 'is_household_head_candidate' => $index === 0]);
        }

        return [$secretary, $draft->fresh(['residentDrafts', 'targetHousehold']), $purok];
    }
}
