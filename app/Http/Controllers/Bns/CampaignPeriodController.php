<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bns\StoreCampaignPeriodRequest;
use App\Http\Requests\Bns\UpdateCampaignPeriodRequest;
use App\Models\AuditLog;
use App\Models\NutritionCampaignPeriod;
use App\Support\ExportDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CampaignPeriodController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
    {
        $query = $this->listingQuery($request);

        return view('bns.campaign-periods.index', [
            'campaignPeriods' => $query->paginate(12)->withQueryString(),
            'campaignTypes' => NutritionCampaignPeriod::TYPES,
            'activeCampaignCount' => $this->bnsCampaignPeriodsQuery()->active()->count(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $columns = [
            'Campaign' => 'name',
            'Type' => fn (NutritionCampaignPeriod $period) => $period->campaign_type_label,
            'Starts On' => fn (NutritionCampaignPeriod $period) => $period->starts_on?->format('Y-m-d'),
            'Ends On' => fn (NutritionCampaignPeriod $period) => $period->ends_on?->format('Y-m-d') ?? 'Open-ended',
            'OPT+ Measurements' => 'opt_measurements_count',
            'Feeding Programs' => 'feeding_programs_count',
            'Status' => fn (NutritionCampaignPeriod $period) => $period->is_active ? 'Active' : 'Inactive',
            'Notes' => 'notes',
        ];
        $filters = [
            'Search' => $request->input('search'),
            'Campaign Type' => NutritionCampaignPeriod::TYPES[$request->input('campaign_type')] ?? null,
            'Status' => $request->filled('active') ? ($request->boolean('active') ? 'Active' : 'Inactive') : null,
        ];

        return ExportDownload::make($format, 'Nutrition Campaign Periods', 'Nutrition', 'bns_campaign_periods', $columns, $this->listingQuery($request)->get(), $filters, $this->bnsUser()->assignedBarangay?->name, NutritionCampaignPeriod::class, array_intersect_key($columns, array_flip(['Campaign', 'Type', 'Starts On', 'Ends On', 'OPT+ Measurements', 'Feeding Programs', 'Status'])));
    }

    private function listingQuery(Request $request): Builder
    {
        $query = $this->bnsCampaignPeriodsQuery()
            ->withCount(['optMeasurements', 'feedingPrograms'])
            ->latest('starts_on')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($campaignQuery) use ($search): void {
                $campaignQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($request->filled('campaign_type')) {
            $query->where('campaign_type', $request->string('campaign_type')->toString());
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        return $query;
    }

    public function create(): View
    {
        return view('bns.campaign-periods.create', [
            'campaignTypes' => config('opt.legacy_writes_enabled') ? NutritionCampaignPeriod::TYPES : array_diff_key(NutritionCampaignPeriod::TYPES, [NutritionCampaignPeriod::TYPE_OPT_PLUS => true]),
        ]);
    }

    public function store(StoreCampaignPeriodRequest $request): RedirectResponse
    {
        $campaignPeriod = NutritionCampaignPeriod::query()->create([
            ...$request->validated(),
            'barangay_id' => $this->assignedBarangayId(),
            'created_by_user_id' => Auth::id(),
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLog::logMutation('created', Auth::user(), $campaignPeriod);

        return redirect()
            ->route('bns.campaign-periods.index')
            ->with('success', 'Campaign period created successfully.');
    }

    public function edit(NutritionCampaignPeriod $campaignPeriod): View
    {
        $this->ensureCampaignPeriodBelongsToBarangay($campaignPeriod);
        abort_if($campaignPeriod->campaign_type === NutritionCampaignPeriod::TYPE_OPT_PLUS && ! config('opt.legacy_writes_enabled'), 410, 'Legacy OPT campaigns are read-only.');

        return view('bns.campaign-periods.edit', [
            'campaignPeriod' => $campaignPeriod,
            'campaignTypes' => NutritionCampaignPeriod::TYPES,
        ]);
    }

    public function update(UpdateCampaignPeriodRequest $request, NutritionCampaignPeriod $campaignPeriod): RedirectResponse
    {
        $this->ensureCampaignPeriodBelongsToBarangay($campaignPeriod);

        $oldValues = $campaignPeriod->toArray();

        $campaignPeriod->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLog::logMutation('updated', Auth::user(), $campaignPeriod, $oldValues, $campaignPeriod->fresh()->toArray());

        return redirect()
            ->route('bns.campaign-periods.index')
            ->with('success', 'Campaign period updated successfully.');
    }
}
