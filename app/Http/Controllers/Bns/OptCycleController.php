<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bns\ConfirmOptCaregiverRequest;
use App\Http\Requests\Bns\SaveOptCycleMeasurementRequest;
use App\Http\Requests\Bns\StoreOptCycleRequest;
use App\Models\OptCycle;
use App\Models\OptCycleEntry;
use App\Models\Resident;
use App\Support\ExportDownload;
use App\Support\Nutrition\OptCycleDataset;
use App\Support\Nutrition\OptCycleRules;
use App\Support\Nutrition\OptCycleWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OptCycleController extends Controller
{
    public function __construct(private readonly OptCycleWorkflow $workflow, private readonly OptCycleDataset $dataset) {}

    private function cyclesQuery(Request $request)
    {
        $filters = $request->validate(['year' => ['nullable', 'integer'], 'status' => ['nullable', 'in:in_progress,completed']]);

        return OptCycle::query()->where('barangay_id', $this->workflow->barangayId($request->user()))->with('barangay')->withProgress()
            ->when($filters['year'] ?? null, fn ($q, $year) => $q->where('year', $year))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('reference_date')->orderByDesc('id');
    }

    public function index(Request $request)
    {
        return view('bns.opt-cycles.index', ['cycles' => $this->cyclesQuery($request)->paginate(12)->withQueryString()]);
    }

    public function create(Request $request)
    {
        $this->workflow->barangayId($request->user());

        return view('bns.opt-cycles.create', ['rounds' => OptCycleRules::ROUNDS, 'roundMonths' => OptCycleRules::ROUND_MONTHS]);
    }

    public function store(StoreOptCycleRequest $request)
    {
        $cycle = $this->workflow->create($request->user(), $request->validated());

        return redirect()->route('bns.opt-cycles.show', $cycle)->with('success', 'Cycle created. The child list is ready.');
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'], 'purok_key' => ['nullable', 'integer'],
            'measurement_status' => ['nullable', 'in:measured,unmeasured'], 'readiness' => ['nullable', 'in:ready,incomplete'],
            'sort' => ['nullable', Rule::in(array_keys(OptCycleDataset::SORTS))], 'direction' => ['nullable', 'in:asc,desc'],
        ]);
    }

    public function show(Request $request, OptCycle $optCycle)
    {
        $this->workflow->authorize($request->user(), $optCycle);
        $optCycle->loadCount(['entries', 'entries as measured_count' => fn ($q) => $q->whereHas('measurement')]);

        return view('bns.opt-cycles.show', [
            'cycle' => $optCycle, 'entries' => $this->dataset->query($optCycle, $this->filters($request))->paginate(15)->withQueryString(),
            'puroks' => $optCycle->entries()->select('purok_key', 'purok_name')->distinct()->orderBy('purok_name')->get(),
            'sorts' => OptCycleDataset::SORTS,
            'incompleteExportCount' => $optCycle->entries()->whereNotIn('id', $optCycle->entries()->dataReady()->select('id'))->count(),
        ]);
    }

    public function entry(Request $request, OptCycle $optCycle, OptCycleEntry $entry)
    {
        $this->workflow->authorize($request->user(), $optCycle, $entry);
        $entry->load(['cycle', 'measurement', 'resident.childNutritionProfile']);

        return view('bns.opt-cycles.entry', $this->entryViewData($optCycle, $entry));
    }

    private function entryViewData(OptCycle $optCycle, OptCycleEntry $entry): array
    {
        $entry->loadMissing(['cycle', 'measurement', 'resident.childNutritionProfile']);

        return [
            'cycle' => $optCycle, 'entry' => $entry,
            'caregivers' => Resident::currentPopulation()->with('household.purok')
                ->whereHas('household.purok', fn ($q) => $q->where('barangay_id', $optCycle->barangay_id))
                ->whereKeyNot($entry->resident_id)->orderBy('last_name')->orderBy('first_name')->get(),
            'canUpdateProfile' => $entry->resident && ! $entry->resident->trashed()
                && (int) $entry->resident->household?->purok?->barangay_id === (int) $optCycle->barangay_id,
            'defaultPosture' => $entry->measurement?->measurement_posture ?? (OptCycleRules::ageMonths($entry->birth_date, now()) < 24 ? 'recumbent' : 'standing'),
        ];
    }

    public function historicalInformation(Request $request, OptCycle $optCycle, OptCycleEntry $entry)
    {
        $this->workflow->authorize($request->user(), $optCycle, $entry);

        return view('bns.opt-cycles.historical-information', $this->entryViewData($optCycle, $entry));
    }

    public function correctHistoricalInformation(Request $request, OptCycle $optCycle, OptCycleEntry $entry)
    {
        $this->workflow->correctHistoricalInformation($request->user(), $optCycle, $entry, $request->all());

        return redirect()->route('bns.opt-cycles.entry', [$optCycle, $entry])->with('success', 'Historical information updated.');
    }

    public function measure(SaveOptCycleMeasurementRequest $request, OptCycle $optCycle, OptCycleEntry $entry)
    {
        $this->workflow->measure($request->user(), $optCycle, $entry, $request->validated());

        return redirect()->route('bns.opt-cycles.show', $optCycle)->with('success', 'Measurement saved for '.$entry->child_name.'.');
    }

    public function caregiver(ConfirmOptCaregiverRequest $request, OptCycle $optCycle, OptCycleEntry $entry)
    {
        $this->workflow->confirmProfile($request->user(), $optCycle, $entry, $request->validated());

        return back()->with('success', 'Current child nutrition profile saved. Only explicitly selected cycle snapshots were changed.');
    }

    public function complete(Request $request, OptCycle $optCycle)
    {
        $data = $request->validate(['confirm_unmeasured' => ['nullable', 'boolean'], 'completion_note' => ['nullable', 'string', 'max:1500']]);
        $this->workflow->complete($request->user(), $optCycle, $request->boolean('confirm_unmeasured'), $data['completion_note'] ?? null);

        return back()->with('success', 'Cycle completed.');
    }

    public function reopen(Request $request, OptCycle $optCycle)
    {
        $data = $request->validate(['reopening_reason' => ['required', 'string', 'max:1500']]);
        $this->workflow->reopen($request->user(), $optCycle, $data['reopening_reason']);

        return back()->with('success', 'Cycle reopened.');
    }

    public function exportHistory(Request $request, string $format)
    {
        $columns = ['Cycle' => 'title', 'Reference Date' => fn ($c) => $c->reference_date->format('Y-m-d'),
            'Eligible Children' => 'entries_count', 'Measured' => 'measured_count', 'Unmeasured' => 'unmeasured_count',
            'Coverage (%)' => 'coverage', 'Status' => fn ($c) => $c->status === OptCycle::COMPLETED ? 'Completed' : 'In Progress'];

        return ExportDownload::make($format, 'Internal OPT+ Cycle History', 'OPT+', 'internal_opt_cycle_history', $columns,
            $this->cyclesQuery($request)->get(), ['Year' => $request->input('year'), 'Status' => $request->input('status')],
            $request->user()->assignedBarangay?->name, OptCycle::class);
    }

    public function export(Request $request, OptCycle $optCycle, string $format)
    {
        $this->workflow->authorize($request->user(), $optCycle);
        $filters = $this->filters($request);
        $columns = $this->dataset->columns();
        $purok = $optCycle->entries()->where('purok_key', $filters['purok_key'] ?? 0)->value('purok_name');

        return ExportDownload::make($format, $optCycle->title.' - Internal OPT+ Dataset', 'OPT+ (not official e-OPT submission)',
            'internal_opt_'.$optCycle->year.'_'.$optCycle->round.'_'.$optCycle->barangay_id, $columns, $this->dataset->rows($optCycle, $filters),
            ['Search' => $filters['search'] ?? null, 'Purok' => $purok, 'Measurement Status' => $filters['measurement_status'] ?? null,
                'Readiness' => $filters['readiness'] ?? null, 'Order' => (OptCycleDataset::SORTS[$filters['sort'] ?? 'name']).' '.($filters['direction'] ?? 'asc'),
                'Cycle' => $optCycle->title, 'Reference Date' => $optCycle->reference_date->format('Y-m-d'),
                'Roster Captured' => $optCycle->roster_captured_at->toIso8601String(), 'Input Contract' => OptCycleDataset::VERSION],
            $optCycle->barangay?->name, OptCycleEntry::class,
            array_intersect_key($columns, array_flip(['Child', 'Age at Reference (Months)', 'Purok', 'Mother/Caregiver', 'Measurement Date', 'Weight (kg)', 'Height/Length (cm)', 'Measurement Status'])),
            'landscape', ['opt_cycle_id' => $optCycle->id, 'barangay_id' => $optCycle->barangay_id]);
    }
}
