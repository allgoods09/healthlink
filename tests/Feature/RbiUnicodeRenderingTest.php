<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RbiUnicodeRenderingTest extends TestCase
{
    use RefreshDatabase;

    public static function outputs(): array
    {
        $cases = [];
        foreach (['secretary', 'admin'] as $role) {
            foreach (['households', 'residents'] as $type) {
                foreach (['pdf', 'print'] as $output) {
                    $cases[$role.' '.$type.' '.$output] = [$role, $type, $output];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('outputs')]
    public function test_utf8_names_survive_real_form_a_and_b_download_and_print(string $role, string $type, string $output): void
    {
        $purok = Purok::factory()->create();
        $home = Household::create(['purok_id' => $purok->id, 'household_no' => 'UTF8-001',
            'household_address' => 'Synthetic Unicode fixture', 'is_active' => true]);
        $resident = Resident::create(['household_id' => $home->id, 'first_name' => 'José Céline',
            'middle_name' => 'Peña Muñoz', 'last_name' => 'Ybañez', 'birth_date' => '1990-01-01',
            'birth_place' => 'Tubigon', 'sex' => 'Female', 'civil_status' => 'Single',
            'citizenship' => 'Filipino', 'relationship_to_head' => 'Head',
            'resident_status' => Resident::STATUS_ACTIVE, 'is_active' => true]);
        $home->update(['head_resident_id' => $resident->id]);
        $before = $resident->fresh()->getAttributes();
        foreach (['first_name' => 'José Céline', 'middle_name' => 'Peña Muñoz', 'last_name' => 'Ybañez'] as $field => $name) {
            $this->assertSame($name, $before[$field]);
            $this->assertTrue(mb_check_encoding($before[$field], 'UTF-8'));
        }
        $user = User::factory()->create(['role' => $role, 'assigned_barangay_id' => $purok->barangay_id]);
        $subject = $type === 'households' ? $home : $resident;
        $response = $this->actingAs($user)->get(route($role.'.'.$type.'.'.$output, $subject))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith($output === 'print' ? 'inline;' : 'attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $path = tempnam(sys_get_temp_dir(), 'healthlink-rbi-unicode-');
        try {
            file_put_contents($path, $response->getContent());
            $process = new Process(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-']);
            $process->mustRun();
            $text = $process->getOutput();
            foreach (['Ybañez', 'Peña', 'Muñoz', 'José', 'Céline'] as $name) {
                $this->assertStringContainsString($name, $text);
            }
            foreach (['YbaÃ±ez', 'PeÃ±a', 'MuÃ±oz', 'JosÃ©', 'CÃ©line'] as $corrupt) {
                $this->assertStringNotContainsString($corrupt, $text);
            }
            // The table/name fields and the Prepared by/accomplishing-party line must both survive.
            $this->assertGreaterThanOrEqual(2, substr_count($text, 'Ybañez'));
            $this->assertStringContainsString('José Céline Peña Muñoz Ybañez', $text);
            if ($role === 'secretary' && $type === 'households' && $output === 'pdf'
                && ($artifact = getenv('HEALTHLINK_RBI_UNICODE_FIXTURE'))) {
                file_put_contents($artifact, $response->getContent());
            }
        } finally {
            unlink($path);
        }
        $this->assertSame($before, $resident->fresh()->getAttributes());
    }
}
