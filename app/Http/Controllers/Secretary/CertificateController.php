<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Http\Requests\Secretary\IssueBarangayCertificateRequest;
use App\Models\AuditLog;
use App\Models\Barangay;
use App\Models\BarangayCertificate;
use App\Models\Household;
use App\Models\Resident;
use App\Support\BarangayOfficialsRegistry;
use App\Support\ExportDownload;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CertificateController extends Controller
{
    use InteractsWithSecretaryScope;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', BarangayCertificate::class);

        $certificates = $this->listingQuery($request)
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

        $certificates = $this->listingQuery($request)->get();

        $columns = [
            'Certificate No.' => 'certificate_no',
            'Type' => fn (BarangayCertificate $certificate) => $certificate->certificate_type_label,
            'Recipient Type' => fn (BarangayCertificate $certificate) => $certificate->recipient_type_label,
            'Issued To' => 'issued_to_name',
            'Purok' => fn (BarangayCertificate $certificate) => $certificate->resident?->household?->purok?->display_name
                ?: $certificate->household?->purok?->display_name
                ?: 'N/A',
            'Purpose' => 'purpose',
            'Issued At' => fn (BarangayCertificate $certificate) => $certificate->issued_at?->copy()->timezone('Asia/Manila')->format('Y-m-d h:i A'),
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

        return ExportDownload::make($format, 'Barangay Certificate Log', 'Certificates', 'secretary_certificates', $columns, $certificates, $filters, $this->secretaryUser()->assignedBarangay?->name, BarangayCertificate::class, array_intersect_key($columns, array_flip(['Certificate No.', 'Type', 'Recipient Type', 'Issued To', 'Purok', 'Issued At', 'Issued By'])));
    }

    public function create(BarangayOfficialsRegistry $officialsRegistry): View
    {
        Gate::authorize('create', BarangayCertificate::class);

        $barangay = Barangay::query()->findOrFail($this->assignedBarangayId());
        $officialSecretary = $officialsRegistry->resolvedSecretaryName($barangay);
        $errors = request()->session()->get('errors', new \Illuminate\Support\ViewErrorBag);
        $initialStep = $errors->hasAny(['certificate_type', 'issued_at']) ? 1
            : ($errors->hasAny(['recipient_type', 'resident_id', 'household_id']) ? 2
            : ($errors->hasAny(['purpose', 'remarks', 'issued_to_name', 'use_printed_name']) ? 3
            : ($errors->any() ? 4 : 1)));

        return view('secretary.certificates.create', [
            'initialStep' => $initialStep,
            'officialSecretary' => $officialSecretary,
            'issuerName' => $this->secretaryUser()->display_name,
            'issuedAtLocal' => now()->timezone('Asia/Manila')->format('Y-m-d\TH:i'),
            'reviewToken' => Crypt::encryptString(json_encode(['user_id' => Auth::id(),
                'barangay_id' => $barangay->id, 'signatory' => $officialSecretary], JSON_THROW_ON_ERROR)),
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

    public function store(IssueBarangayCertificateRequest $request, BarangayOfficialsRegistry $officialsRegistry): RedirectResponse
    {
        Gate::authorize('create', BarangayCertificate::class);

        $data = $request->validated();
        $data['barangay_id'] = $this->assignedBarangayId();
        $barangay = Barangay::query()->findOrFail($data['barangay_id']);
        $signatoryName = $officialsRegistry->resolvedSecretaryName($barangay);
        if (blank($signatoryName)) {
            $message = 'An official Barangay Secretary name is required before issuing a certificate. Check the Secretary assignment or Barangay Officials roster.';
            $request->session()->flash('error', $message);
            throw ValidationException::withMessages([
                'signatory_name_at_issuance' => $message,
            ]);
        }
        $data['signatory_name_at_issuance'] = $signatoryName;
        try {
            $review = json_decode(Crypt::decryptString($data['review_token'] ?? ''), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            throw ValidationException::withMessages(['review_token' => 'Review the certificate again before issuing it.']);
        }
        if (! is_array($review) || ($review['user_id'] ?? null) !== Auth::id()
            || ($review['barangay_id'] ?? null) !== $barangay->id || ($review['signatory'] ?? null) !== $signatoryName) {
            throw ValidationException::withMessages(['review_token' => 'The official Secretary changed or the review is no longer valid. Review the certificate again before issuing it.']);
        }
        $recipient = $this->resolveActiveRecipient($data);
        $data['resident_id'] = $recipient instanceof Resident ? $recipient->id : null;
        $data['household_id'] = $recipient instanceof Household ? $recipient->id : null;
        $data['issued_to_name'] = $request->boolean('use_printed_name')
            ? $data['issued_to_name']
            : ($recipient instanceof Resident ? $recipient->formal_name : ($recipient->headResident?->formal_name ?: 'Household #'.$recipient->household_no));
        unset($data['review_token'], $data['use_printed_name']);
        $data['issued_by_user_id'] = Auth::id();
        $format = strlen($data['issued_at']) === 16 ? '!Y-m-d\TH:i' : '!Y-m-d\TH:i:s';
        $data['issued_at'] = CarbonImmutable::createFromFormat($format, $data['issued_at'], 'Asia/Manila')
            ->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
        $data['certificate_no'] = $this->generateCertificateNo($data['certificate_type']);

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

    private function listingQuery(Request $request): Builder
    {
        return $this->filteredQuery($request)
            ->with(['resident.household.purok', 'household.headResident', 'household.purok', 'issuedBy'])
            ->latest('issued_at')
            ->latest('id');
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
            $lower = $this->localDateBoundary($request->input('date_from'));
            if ($lower !== null) {
                $query->where('issued_at', '>=', $lower);
            } else {
                $query->whereDate('issued_at', '>=', $request->input('date_from'));
            }
        }

        if ($request->filled('date_to')) {
            $upper = $this->localDateBoundary($request->input('date_to'), true);
            if ($upper !== null) {
                $query->where('issued_at', '<', $upper);
            } else {
                $query->whereDate('issued_at', '<=', $request->input('date_to'));
            }
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

    private function localDateBoundary(string $date, bool $followingDay = false): ?string
    {
        // Keep the existing database comparison behavior for malformed filter values.
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        $boundary = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Manila');

        return ($followingDay ? $boundary->addDay() : $boundary)
            ->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    private function resolveActiveRecipient(array $data): Resident|Household
    {
        if (($data['recipient_type'] ?? null) === BarangayCertificate::RECIPIENT_RESIDENT) {
            $recipient = $this->secretaryResidentsQuery()->active()->where('resident_status', Resident::STATUS_ACTIVE)->find($data['resident_id']);
        } else {
            $recipient = $this->secretaryHouseholdsQuery()->active()->with('headResident')->find($data['household_id']);
        }
        if (! $recipient) {
            throw ValidationException::withMessages([$data['recipient_type'].'_id' => 'This recipient is no longer an available active record in your assigned barangay. Select it again.']);
        }

        return $recipient;
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
