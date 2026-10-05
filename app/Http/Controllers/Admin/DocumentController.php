<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\BarangayOfficial;
use App\Models\Household;
use App\Models\Purok;
use App\Models\Resident;
use App\Support\BarangayOfficialsRegistry;
use App\Support\ExportAudit;
use App\Support\RbiTemplatePdfGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DocumentController extends Controller
{
    public function index(Request $request, BarangayOfficialsRegistry $officialsRegistry): View
    {
        Gate::authorize('viewAny', Resident::class);
        Gate::authorize('viewAny', Household::class);

        $filters = $this->filters($request);
        $barangays = Barangay::query()->active()->orderBy('name')->get();
        $barangay = ! empty($filters['barangay_id']) ? $barangays->firstWhere('id', (int) $filters['barangay_id']) : null;
        $officials = $barangay ? $officialsRegistry->syncDefaults($barangay) : collect();

        return view('documents.module.index', [
            'layout' => 'layouts.admin',
            'routePrefix' => 'admin',
            'pageTitle' => 'Documents - HealthLink Admin',
            'pageHeader' => 'Documents',
            'pageSubheader' => 'Supervisory document generation and barangay attestation management across Tubigon.',
            'barangay' => $barangay,
            'barangays' => $barangays,
            'officials' => $officials,
            ...($barangay ? $officialsRegistry->secretaryPresentation($barangay) : ['secretaryLinked' => false, 'resolvedSecretaryName' => null]),
            'filters' => $filters,
            'documentTypes' => $this->documentTypes(),
            'puroks' => $barangay ? Purok::query()->where('barangay_id', $barangay->id)->active()->orderBy('purok_number')->get() : collect(),
            'households' => $this->availableHouseholds($filters),
            'previewCount' => $barangay ? $this->previewCount($filters) : 0,
        ]);
    }

    public function export(
        Request $request,
        RbiTemplatePdfGenerator $generator,
        BarangayOfficialsRegistry $officialsRegistry
    ): Response|RedirectResponse {
        Gate::authorize('viewAny', Resident::class);
        Gate::authorize('viewAny', Household::class);

        $filters = $this->filters($request);
        $barangay = ! empty($filters['barangay_id']) ? Barangay::query()->find($filters['barangay_id']) : null;

        if (! $barangay) {
            return back()->with('error', 'Please select a barangay before generating documents.');
        }

        $officials = $officialsRegistry->keyed($barangay);

        if ($filters['document_type'] === 'household_rbi') {
            $records = $this->householdQuery($filters)
                ->with(['purok.barangay', 'headResident', 'currentMembers.socioEconomicProfile'])
                ->get();

            if ($records->isEmpty()) {
                return back()->with('error', 'No household records matched the selected document filters.');
            }

            $content = $generator->generateHouseholds($records, [
                'officials' => [
                    'barangay_secretary_name' => $officialsRegistry->resolvedSecretaryName($barangay),
                    'punong_barangay_name' => $officials->get(BarangayOfficial::ROLE_PUNONG_BARANGAY)?->official_name,
                ],
            ]);

            ExportAudit::log('admin household RBI documents', 'pdf', [
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

        $records = $this->residentQuery($filters)
            ->with(['household.purok.barangay', 'socioEconomicProfile'])
            ->get();

        if ($records->isEmpty()) {
            return back()->with('error', 'No resident records matched the selected document filters.');
        }

        $content = $generator->generateResidents($records, [
            'barangay_secretary_name' => $officialsRegistry->resolvedSecretaryName($barangay),
        ]);

        ExportAudit::log('admin resident RBI documents', 'pdf', [
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
        Gate::authorize('viewAny', Barangay::class);

        $barangayId = filter_var($request->input('barangay_id'), FILTER_VALIDATE_INT);
        $selected = $barangayId ? Barangay::query()->find($barangayId) : null;
        if ($selected && is_array($request->input('officials'))) {
            $request->merge(['officials' => $officialsRegistry->editableNameInput($selected, $request->input('officials'))]);
        }

        $validated = $request->validate([
            'barangay_id' => ['required', 'integer', 'exists:barangays,id'],
            'officials' => ['required', 'array'],
            'officials.*' => ['nullable', 'string', 'max:150'],
        ]);

        $barangay = Barangay::query()->findOrFail($validated['barangay_id']);
        $officialsRegistry->updateNames($barangay, $validated['officials']);

        return back()->with('success', 'Barangay officials updated for the selected document workspace.');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'document_type' => ['nullable', 'in:resident_rbi,household_rbi'],
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,id'],
            'purok_id' => ['nullable', 'integer'],
            'household_id' => ['nullable', 'integer'],
            'sex' => ['nullable', 'in:Male,Female'],
            'record_status' => $request->input('document_type', 'resident_rbi') === 'household_rbi' ? ['nullable', 'in:all,active,inactive'] : ['exclude'],
            'social_aid' => ['nullable', 'in:all,yes,no'],
            'age_min' => ['nullable', 'integer', 'min:0', 'max:150'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:150'],
        ]) + [
            'document_type' => $request->input('document_type', 'resident_rbi'),
            'record_status' => $request->input('document_type', 'resident_rbi') === 'household_rbi' ? $request->input('record_status', 'active') : 'all',
            'social_aid' => $request->input('social_aid', 'all'),
        ];
    }

    private function residentQuery(array $filters): Builder
    {
        $query = Resident::currentPopulation()->orderBy('last_name')->orderBy('first_name');

        if (! empty($filters['barangay_id'])) {
            $query->whereHas('household.purok', fn (Builder $builder) => $builder->where('barangay_id', $filters['barangay_id']));
        }

        if (! empty($filters['purok_id'])) {
            $query->whereHas('household', fn (Builder $builder) => $builder->where('purok_id', $filters['purok_id']));
        }

        if (! empty($filters['household_id'])) {
            $query->where('household_id', $filters['household_id']);
        }

        if (! empty($filters['sex'])) {
            $query->where('sex', $filters['sex']);
        }

        if (isset($filters['age_min']) && $filters['age_min'] !== null) {
            $query->whereDate('birth_date', '<=', now()->subYears((int) $filters['age_min'])->endOfDay());
        }

        if (isset($filters['age_max']) && $filters['age_max'] !== null) {
            $query->whereDate('birth_date', '>=', now()->subYears((int) $filters['age_max'] + 1)->addDay()->startOfDay());
        }

        return $query;
    }

    private function householdQuery(array $filters): Builder
    {
        $query = Household::query()->whereHas('currentMembers')->orderBy('purok_id')->orderBy('household_no');

        if (! empty($filters['barangay_id'])) {
            $query->whereHas('purok', fn (Builder $builder) => $builder->where('barangay_id', $filters['barangay_id']));
        }

        if (! empty($filters['purok_id'])) {
            $query->where('purok_id', $filters['purok_id']);
        }

        if (! empty($filters['household_id'])) {
            $query->whereKey($filters['household_id']);
        }

        if (($filters['record_status'] ?? 'active') !== 'all') {
            $query->where('is_active', ($filters['record_status'] ?? 'active') === 'active');
        }

        if (($filters['social_aid'] ?? 'all') !== 'all') {
            $query->where('is_social_aid_beneficiary', ($filters['social_aid'] ?? 'all') === 'yes');
        }

        return $query;
    }

    private function availableHouseholds(array $filters)
    {
        $query = Household::query()
            ->whereHas('currentMembers')
            ->with('purok')
            ->active()
            ->orderBy('household_no');

        if (! empty($filters['barangay_id'])) {
            $query->whereHas('purok', fn (Builder $builder) => $builder->where('barangay_id', $filters['barangay_id']));
        }

        if (! empty($filters['purok_id'])) {
            $query->where('purok_id', $filters['purok_id']);
        }

        return $query->get();
    }

    private function previewCount(array $filters): int
    {
        return $filters['document_type'] === 'household_rbi'
            ? $this->householdQuery($filters)->count()
            : $this->residentQuery($filters)->count();
    }

    private function documentTypes(): array
    {
        return [
            'resident_rbi' => 'RBI Form B - Individual Records',
            'household_rbi' => 'RBI Form A - Household Records',
        ];
    }

    private function auditFilters(array $filters, Barangay $barangay): array
    {
        return array_filter([
            'document_type' => $this->documentTypes()[$filters['document_type']] ?? $filters['document_type'],
            'barangay' => $barangay->name,
            'purok_id' => $filters['purok_id'] ?? null,
            'household_id' => $filters['household_id'] ?? null,
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
