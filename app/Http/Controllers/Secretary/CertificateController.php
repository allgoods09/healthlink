<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Http\Requests\Secretary\IssueBarangayCertificateRequest;
use App\Models\AuditLog;
use App\Models\BarangayCertificate;
use App\Models\Household;
use App\Models\Resident;
use App\Support\ExportAudit;
use App\Support\TabularExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CertificateController extends Controller
{
    use InteractsWithSecretaryScope;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', BarangayCertificate::class);

        $certificates = $this->filteredQuery($request)
            ->with(['resident.household.purok', 'household.headResident', 'household.purok', 'issuedBy'])
            ->latest('issued_at')
            ->paginate(15)
            ->withQueryString();

        return view('secretary.certificates.index', [
            'certificates' => $certificates,
            'puroks' => $this->secretaryPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        Gate::authorize('viewAny', BarangayCertificate::class);

        $certificates = $this->filteredQuery($request)
            ->with(['resident.household.purok', 'household.headResident', 'household.purok', 'issuedBy'])
            ->latest('issued_at')
            ->get();

        $columns = [
            'Certificate No.' => 'certificate_no',
            'Type' => fn (BarangayCertificate $certificate) => $certificate->certificate_type_label,
            'Recipient Type' => fn (BarangayCertificate $certificate) => $certificate->recipient_type_label,
            'Issued To' => 'issued_to_name',
            'Purok' => fn (BarangayCertificate $certificate) => $certificate->resident?->household?->purok?->display_name
                ?: $certificate->household?->purok?->display_name
                ?: 'N/A',
            'Purpose' => 'purpose',
            'Issued At' => fn (BarangayCertificate $certificate) => optional($certificate->issued_at)?->format('Y-m-d h:i A'),
            'Issued By' => fn (BarangayCertificate $certificate) => $certificate->issuedBy?->name ?: 'System',
        ];

        $filters = [
            'Search' => $request->string('search')->toString(),
            'Type' => match ($request->input('certificate_type')) {
                BarangayCertificate::TYPE_CLEARANCE => 'Barangay Clearance',
                BarangayCertificate::TYPE_INDIGENCY => 'Certificate of Indigency',
                default => null,
            },
            'Recipient Type' => match ($request->input('recipient_type')) {
                BarangayCertificate::RECIPIENT_RESIDENT => 'Resident',
                BarangayCertificate::RECIPIENT_HOUSEHOLD => 'Household',
                default => null,
            },
            'Purok' => $this->secretaryPuroksQuery()->find($request->integer('purok_id'))?->display_name,
            'Date From' => $request->input('date_from'),
            'Date To' => $request->input('date_to'),
        ];

        ExportAudit::log('barangay certificates', $format, [
            'model_type' => BarangayCertificate::class,
            'record_count' => $certificates->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("secretary_certificates_{$timestamp}.csv", $columns, $certificates),
            'xlsx' => TabularExport::xlsx("secretary_certificates_{$timestamp}.xlsx", 'Secretary Certificates', $columns, $certificates),
            'pdf' => TabularExport::pdf("secretary_certificates_{$timestamp}.pdf", 'Barangay Certificate Log', $columns, $certificates, $filters),
            default => abort(404),
        };
    }

    public function create(): View
    {
        Gate::authorize('create', BarangayCertificate::class);

        return view('secretary.certificates.create', [
            'residents' => $this->secretaryResidentsQuery()
                ->with('household.purok')
                ->where('resident_status', Resident::STATUS_ACTIVE)
                ->active()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
            'households' => $this->secretaryHouseholdsQuery()
                ->with(['purok', 'headResident'])
                ->active()
                ->orderBy('household_no')
                ->get(),
        ]);
    }

    public function store(IssueBarangayCertificateRequest $request): RedirectResponse
    {
        Gate::authorize('create', BarangayCertificate::class);

        $data = $request->validated();
        $data['barangay_id'] = $this->assignedBarangayId();
        $data['issued_by_user_id'] = Auth::id();
        $data['issued_at'] = $data['issued_at'] ?? now();
        $data['certificate_no'] = $this->generateCertificateNo($data['certificate_type']);
        $data['issued_to_name'] = ($data['issued_to_name'] ?? null) ?: $this->resolveIssuedToName($data);

        $certificate = BarangayCertificate::create($data);

        AuditLog::logMutation('created', Auth::user(), $certificate);

        return redirect()
            ->route('secretary.certificates.show', $certificate)
            ->with('success', "Certificate {$certificate->certificate_no} issued successfully.");
    }

    public function show(BarangayCertificate $certificate): View
    {
        Gate::authorize('view', $certificate);
        $this->ensureCertificateBelongsToBarangay($certificate);

        $certificate->load(['barangay', 'resident.household.purok', 'household.headResident', 'household.purok', 'issuedBy']);

        return view('secretary.certificates.show', [
            'certificate' => $certificate,
        ]);
    }

    public function pdf(BarangayCertificate $certificate): Response
    {
        Gate::authorize('view', $certificate);
        $this->ensureCertificateBelongsToBarangay($certificate);

        $certificate->load(['barangay', 'resident.household.purok', 'household.headResident', 'household.purok', 'issuedBy']);

        return Pdf::loadView('secretary.certificates.pdf', [
            'certificate' => $certificate,
        ])->setPaper('a4')->download($certificate->certificate_no.'.pdf');
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->secretaryCertificatesQuery();

        if ($request->filled('certificate_type')) {
            $query->where('certificate_type', $request->input('certificate_type'));
        }

        if ($request->filled('recipient_type')) {
            $query->where('recipient_type', $request->input('recipient_type'));
        }

        if ($request->filled('purok_id')) {
            $purokId = $request->integer('purok_id');

            $query->where(function (Builder $builder) use ($purokId): void {
                $builder->whereHas('resident.household', function (Builder $nested) use ($purokId): void {
                    $nested->where('purok_id', $purokId);
                })->orWhereHas('household', function (Builder $nested) use ($purokId): void {
                    $nested->where('purok_id', $purokId);
                });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('issued_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('issued_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('certificate_no', 'like', "%{$search}%")
                    ->orWhere('issued_to_name', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function resolveIssuedToName(array $data): string
    {
        if (($data['recipient_type'] ?? null) === BarangayCertificate::RECIPIENT_RESIDENT) {
            $resident = $this->secretaryResidentsQuery()->findOrFail($data['resident_id']);

            return $resident->formal_name;
        }

        $household = $this->secretaryHouseholdsQuery()->with('headResident')->findOrFail($data['household_id']);

        return $household->headResident?->formal_name ?: 'Household #'.$household->household_no;
    }

    private function generateCertificateNo(string $certificateType): string
    {
        $prefix = match ($certificateType) {
            BarangayCertificate::TYPE_CLEARANCE => 'BCL',
            BarangayCertificate::TYPE_INDIGENCY => 'COI',
            default => 'CERT',
        };

        $year = now()->format('Y');
        $barangaySegment = 'B'.$this->assignedBarangayId();

        $sequence = $this->secretaryCertificatesQuery()
            ->where('certificate_type', $certificateType)
            ->whereYear('issued_at', now()->year)
            ->count() + 1;

        return sprintf('%s-%s-%s-%04d', $prefix, $barangaySegment, $year, $sequence);
    }
}
