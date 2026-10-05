<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Models\Barangay;
use App\Models\BarangayOfficial;
use App\Models\Household;
use App\Models\Resident;
use App\Support\BarangayOfficialsRegistry;
use App\Support\ExportAudit;
use App\Support\RbiTemplatePdfGenerator;
use App\Support\SecretaryRbiSelection;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    use InteractsWithSecretaryScope;

    public function index(Request $request, SecretaryRbiSelection $selector, BarangayOfficialsRegistry $officialsRegistry): View|RedirectResponse
    {
        Gate::authorize('viewAny', Resident::class);
        Gate::authorize('viewAny', Household::class);

        $barangay = Barangay::query()->findOrFail($this->assignedBarangayId());
        $step = max(1, min(4, (int) $request->input('step', 1)));
        try {
            $selection = $selector->normalize($request->all(), $barangay->id, $step);
        } catch (ValidationException $exception) {
            return $this->selectionError($request->all(), $exception);
        }
        $state = $this->draftState($request->all());
        $puroks = $this->secretaryPuroksQuery()->orderBy('purok_number')->get();
        $households = $selector->households($barangay->id)->whereHas('currentMembers')->with('purok')->orderBy('purok_id')->orderBy('household_no')->get();
        $officials = $barangay->officials()->get()->keyBy('role_key');
        $count = $step === 4 ? $selector->query($selection, $barangay->id)->count() : null;
        $reviewToken = $step === 4 && $count > 0 ? Crypt::encryptString(json_encode([
            'user_id' => $request->user()->id,
            'barangay_id' => $barangay->id,
            'selection' => $selection,
        ], JSON_THROW_ON_ERROR)) : null;

        return view('secretary.documents.index', [
            'step' => $step,
            'barangay' => $barangay,
            'officials' => $officials,
            ...$officialsRegistry->secretaryPresentation($barangay),
            'state' => $state,
            'selection' => $selection,
            'documentTypes' => SecretaryRbiSelection::DOCUMENTS,
            'coverageTypes' => SecretaryRbiSelection::COVERAGES,
            'puroks' => $puroks,
            'households' => $households,
            'previewCount' => $count,
            'reviewToken' => $reviewToken,
            'filterLabels' => $step === 4 ? $selector->filterLabels($selection) : [],
        ]);
    }

    public function export(
        Request $request,
        RbiTemplatePdfGenerator $generator,
        BarangayOfficialsRegistry $officialsRegistry,
        SecretaryRbiSelection $selector
    ): Response|RedirectResponse {
        Gate::authorize('viewAny', Resident::class);
        Gate::authorize('viewAny', Household::class);

        $barangay = Barangay::query()->findOrFail($this->assignedBarangayId());
        // Export only the reviewed selection, never newly edited query controls.
        try {
            $token = $request->input('review');
            if (! is_string($token) || $token === '') {
                return redirect()->route('secretary.documents.index')->with('error', 'Review your selection before generating the PDF.');
            }
            $review = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException $exception) {
            return redirect()->route('secretary.documents.index')->with('error', 'Please review your selection again before generating the PDF.');
        }
        abort_unless(is_array($review) && ($review['user_id'] ?? null) === $request->user()->id
            && ($review['barangay_id'] ?? null) === $barangay->id && is_array($review['selection'] ?? null), 403);
        try {
            $filters = $selector->normalize($review['selection'], $barangay->id);
        } catch (ValidationException $exception) {
            return $this->selectionError($review['selection'], $exception);
        }
        if (array_intersect(SecretaryRbiSelection::FIELDS, array_keys($request->all()))) {
            return redirect()->route('secretary.documents.index', $filters + ['step' => 4])
                ->with('error', 'Return to Review after changing your selection.');
        }
        $officials = $officialsRegistry->keyed($barangay);

        if ($filters['document_type'] === 'household_rbi') {
            $records = $selector->query($filters, $barangay->id)
                ->with(['purok.barangay', 'headResident', 'currentMembers.socioEconomicProfile'])
                ->get();

            if ($records->isEmpty()) {
                return redirect()->route('secretary.documents.index', $filters + ['step' => 4])
                    ->with('error', 'No household records matched the selected document filters.');
            }

            $content = $generator->generateHouseholds($records, [
                'officials' => [
                    'barangay_secretary_name' => $officialsRegistry->resolvedSecretaryName($barangay),
                    'punong_barangay_name' => $officials->get(BarangayOfficial::ROLE_PUNONG_BARANGAY)?->official_name,
                ],
            ]);

            ExportAudit::log('secretary household RBI documents', 'pdf', [
                'model_type' => Household::class,
                'record_count' => $records->count(),
                'record_ids' => $records->pluck('id')->all(),
                'filters' => $this->auditFilters($filters, $barangay),
                'document_type' => 'RBI Form A',
                'barangay_id' => $barangay->id,
                'barangay_name' => $barangay->name,
            ]);

            return $this->downloadResponse(
                $content,
                'rbi-form-a-households-'.$barangay->id.'-'.now()->format('Ymd_His').'.pdf'
            );
        }

        $records = $selector->query($filters, $barangay->id)
            ->with(['household.purok.barangay', 'socioEconomicProfile'])
            ->get();

        if ($records->isEmpty()) {
            return redirect()->route('secretary.documents.index', $filters + ['step' => 4])
                ->with('error', 'No resident records matched the selected document filters.');
        }

        $content = $generator->generateResidents($records, [
            'barangay_secretary_name' => $officialsRegistry->resolvedSecretaryName($barangay),
        ]);

        ExportAudit::log('secretary resident RBI documents', 'pdf', [
            'model_type' => Resident::class,
            'record_count' => $records->count(),
            'record_ids' => $records->pluck('id')->all(),
            'filters' => $this->auditFilters($filters, $barangay),
            'document_type' => 'RBI Form B',
            'barangay_id' => $barangay->id,
            'barangay_name' => $barangay->name,
        ]);

        return $this->downloadResponse(
            $content,
            'rbi-form-b-residents-'.$barangay->id.'-'.now()->format('Ymd_His').'.pdf'
        );
    }

    public function updateOfficials(Request $request, BarangayOfficialsRegistry $officialsRegistry): RedirectResponse
    {
        Gate::authorize('viewAny', Household::class);

        $barangay = Barangay::query()->findOrFail($this->assignedBarangayId());
        if (is_array($request->input('officials'))) {
            $request->merge(['officials' => $officialsRegistry->editableNameInput($barangay, $request->input('officials'))]);
        }
        $validated = $request->validate([
            'officials' => ['required', 'array'],
            'officials.*' => ['nullable', 'string', 'max:150'],
        ]);

        $officialsRegistry->updateNames($barangay, $validated['officials']);

        return back()->with('success', 'Barangay officials updated for document attestation fields.');
    }

    private function draftState(array $input): array
    {
        $state = ['document_type' => '', 'coverage' => 'barangay', 'purok_ids' => [], 'household_ids' => [],
            'record_status' => 'active', 'social_aid' => 'all', 'sex' => '', 'resident_status' => '', 'age_min' => '', 'age_max' => ''];
        foreach ($state as $field => $default) {
            if (is_array($default)) {
                $state[$field] = array_values(array_filter((array) ($input[$field] ?? []), fn ($id) => is_scalar($id)));
            } elseif (isset($input[$field]) && is_scalar($input[$field])) {
                $state[$field] = (string) $input[$field];
            }
        }

        return $state;
    }

    private function selectionError(array $input, ValidationException $exception): RedirectResponse
    {
        $keys = array_keys($exception->errors());
        $step = in_array('document_type', $keys, true) ? 1
            : (collect($keys)->contains(fn ($key) => $key === 'coverage' || str_starts_with($key, 'purok_ids') || str_starts_with($key, 'household_ids')) ? 2 : 3);

        return redirect()->route('secretary.documents.index', $this->draftState($input) + ['step' => $step])
            ->withErrors($exception->errors())->withInput($input);
    }

    private function auditFilters(array $filters, Barangay $barangay): array
    {
        return array_filter([
            'document_type' => SecretaryRbiSelection::DOCUMENTS[$filters['document_type']],
            'barangay' => $barangay->name,
            'coverage' => $filters['coverage'],
            'purok_ids' => $filters['purok_ids'] ?? null,
            'household_ids' => $filters['household_ids'] ?? null,
            'sex' => $filters['sex'] ?? null,
            'resident_status' => $filters['resident_status'] ?? null,
            'record_status' => $filters['record_status'] ?? null,
            'social_aid' => $filters['social_aid'] ?? null,
            'age_min' => $filters['age_min'] ?? null,
            'age_max' => $filters['age_max'] ?? null,
        ], fn (mixed $value) => ! is_null($value) && $value !== '');
    }

    private function downloadResponse(string $content, string $filename): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
