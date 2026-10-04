<?php

namespace Tests\Feature\Secretary;

use App\Models\Barangay;
use App\Models\BarangayCertificate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CertificateTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public static function issuanceTimes(): array
    {
        return ['daytime' => ['2026-10-04 06:15:00', '02:15 PM'],
            'local date rollover' => ['2026-10-03 18:00:00', '02:00 AM']];
    }

    #[DataProvider('issuanceTimes')]
    public function test_all_issuance_surfaces_use_manila_without_mutating_storage(string $utc, string $time): void
    {
        $actor = $this->actor();
        $certificate = $this->certificate($actor, 'PRESENTATION', $utc);
        $this->actingAs($actor)->get(route('secretary.certificates.index'))->assertOk()->assertSee('Oct 04, 2026');
        $this->get(route('secretary.certificates.show', $certificate))->assertOk()->assertSee("October 04, 2026 {$time}");
        $this->get(route('secretary.dashboard'))->assertOk()->assertSee("Oct 04, 2026 {$time}");
        $csv = $this->get(route('secretary.certificates.export', 'csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString("2026-10-04 {$time}", $csv);

        $response = $this->get(route('secretary.certificates.export', 'xlsx'))->assertOk();
        $file = $response->baseResponse->getFile()->getPathname();
        try {
            $book = IOFactory::load($file);
            $cell = $book->getSheetByName('Data')->getCell('G2');
            $this->assertSame("2026-10-04 {$time}", $cell->getValue());
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            $this->assertStringEndsWith(' UTC', $book->getSheetByName('Export Information')->getCell('B4')->getValue());
            $book->disconnectWorksheets();
        } finally {
            unlink($file);
        }

        $html = view('secretary.certificates.pdf', ['certificate' => $certificate->load(['barangay', 'issuedBy'])])->render();
        $this->assertStringContainsString('Issued this 4th day of October 2026', $html);
        $this->assertStringContainsString("Issued at: October 04, 2026 {$time}", $html);
        $this->assertSame($utc, $certificate->issued_at->format('Y-m-d H:i:s'));

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('report'));
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($time): bool {
            $this->assertSame('exports.table', $view);
            $this->assertSame("2026-10-04 {$time}", $data['rows'][0][5]);
            $this->assertStringEndsWith(' UTC', $data['information']['Generated at']);
            return true;
        })->andReturn($pdf);
        $this->get(route('secretary.certificates.export', 'pdf'))->assertOk();
        $this->assertSame($utc, $certificate->fresh()->issued_at->format('Y-m-d H:i:s'));
    }

    public static function ranges(): array
    {
        return [
            'same day' => [['date_from' => '2026-10-04', 'date_to' => '2026-10-04'], ['END', 'MIDDAY', 'START']],
            'from only' => [['date_from' => '2026-10-04'], ['AFTER', 'END', 'MIDDAY', 'START']],
            'to only' => [['date_to' => '2026-10-04'], ['END', 'MIDDAY', 'START', 'BEFORE']],
            'multi day' => [['date_from' => '2026-10-03', 'date_to' => '2026-10-04'], ['END', 'MIDDAY', 'START', 'BEFORE']],
            'reversed' => [['date_from' => '2026-10-05', 'date_to' => '2026-10-03'], []],
        ];
    }

    #[DataProvider('ranges')]
    public function test_local_ranges_match_log_and_all_exports_with_scope_and_order(array $filters, array $expected): void
    {
        $actor = $this->actor();
        foreach (['BEFORE' => '2026-10-03 15:59:59', 'START' => '2026-10-03 16:00:00',
            'MIDDAY' => '2026-10-04 04:00:00', 'END' => '2026-10-04 15:59:59', 'AFTER' => '2026-10-04 16:00:00'] as $no => $utc) {
            $this->certificate($actor, $no, $utc);
        }
        $this->certificate($this->actor(), 'FOREIGN', '2026-10-04 04:00:00');
        $this->actingAs($actor);
        $page = $this->get(route('secretary.certificates.index', $filters))->assertOk();
        $this->assertSame($expected, $page->viewData('certificates')->pluck('certificate_no')->all());
        $csv = $this->get(route('secretary.certificates.export', ['format' => 'csv'] + $filters))->assertOk()->streamedContent();
        $lines = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $this->assertSame($expected, array_column(array_slice($lines, 1), 0));
        $response = $this->get(route('secretary.certificates.export', ['format' => 'xlsx'] + $filters))->assertOk();
        $file = $response->baseResponse->getFile()->getPathname();
        try {
            $book = IOFactory::load($file);
            $rows = $book->getSheetByName('Data')->toArray();
            $this->assertSame($expected, array_column(array_slice($rows, 1), 0));
            $book->disconnectWorksheets();
        } finally {
            unlink($file);
        }
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('report'));
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($expected): bool {
            $this->assertSame($expected, array_column($data['rows'], 0));
            return $view === 'exports.table';
        })->andReturn($pdf);
        $this->get(route('secretary.certificates.export', ['format' => 'pdf'] + $filters))->assertOk();
    }

    public function test_monthly_count_uses_manila_month_even_when_current_utc_month_is_previous(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-30 18:00:00', 'UTC'));
        $actor = $this->actor();
        foreach (['BEFORE' => '2026-09-30 15:59:59', 'START' => '2026-09-30 16:00:00',
            'END' => '2026-10-31 15:59:59', 'AFTER' => '2026-10-31 16:00:00'] as $no => $utc) {
            $this->certificate($actor, $no, $utc);
        }
        $this->certificate($this->actor(), 'FOREIGN', '2026-10-01 00:00:00');
        $this->actingAs($actor)->get(route('secretary.dashboard'))->assertOk()
            ->assertViewHas('monthlyCertificateCount', 2)->assertViewHas('certificateCount', 4);
    }

    public function test_malformed_filter_keeps_existing_database_comparison_semantics(): void
    {
        $actor = $this->actor();
        $this->certificate($actor, 'EXISTING', '2026-10-04 04:00:00');
        foreach (['date_from' => '>=', 'date_to' => '<='] as $filter => $operator) {
            $expected = BarangayCertificate::where('barangay_id', $actor->assigned_barangay_id)
                ->whereDate('issued_at', $operator, 'invalid-date')->pluck('certificate_no')->all();
            $page = $this->actingAs($actor)->get(route('secretary.certificates.index', [$filter => 'invalid-date']))->assertOk();
            $this->assertSame($expected, $page->viewData('certificates')->pluck('certificate_no')->all());
        }
    }

    private function actor(): User
    {
        return User::factory()->create(['role' => 'secretary', 'assigned_barangay_id' => Barangay::factory()->create()->id]);
    }

    private function certificate(User $actor, string $number, string $utc): BarangayCertificate
    {
        return BarangayCertificate::create(['barangay_id' => $actor->assigned_barangay_id,
            'certificate_type' => 'barangay_clearance', 'recipient_type' => 'resident', 'certificate_no' => $number,
            'issued_to_name' => 'Synthetic Recipient', 'purpose' => 'Timezone regression', 'issued_at' => $utc,
            'issued_by_user_id' => $actor->id, 'signatory_name_at_issuance' => 'Frozen Secretary']);
    }
}
