<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bns\StoreMicronutrientLogRequest;
use App\Models\AuditLog;
use App\Models\MicronutrientSupplementationLog;
use App\Support\ExportDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MicronutrientLogController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
    {
        $query = $this->listingQuery($request);

        return view('bns.micronutrients.index', [
            'logs' => $query->paginate(15)->withQueryString(),
            'supplementTypes' => MicronutrientSupplementationLog::SUPPLEMENT_TYPES,
            'recipientCategories' => MicronutrientSupplementationLog::RECIPIENT_CATEGORIES,
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $columns = [
            'Resident' => fn (MicronutrientSupplementationLog $log) => $log->resident?->formal_name ?? 'Unknown',
            'Purok' => fn (MicronutrientSupplementationLog $log) => $log->resident?->household?->purok?->display_name ?? 'Unknown',
            'Supplement' => fn (MicronutrientSupplementationLog $log) => $log->supplement_type_label,
            'Recipient Category' => fn (MicronutrientSupplementationLog $log) => $log->recipient_category_label,
            'Dose' => 'dose_description',
            'Administered On' => fn (MicronutrientSupplementationLog $log) => $log->administered_on?->format('Y-m-d'),
            'Distributed By' => fn (MicronutrientSupplementationLog $log) => $log->distributedBy?->name ?? 'Unknown',
            'Remarks' => 'remarks',
        ];
        $filters = [
            'Search' => $request->input('search'),
            'Supplement Type' => MicronutrientSupplementationLog::SUPPLEMENT_TYPES[$request->input('supplement_type')] ?? null,
            'Recipient Category' => MicronutrientSupplementationLog::RECIPIENT_CATEGORIES[$request->input('recipient_category')] ?? null,
        ];

        return ExportDownload::make($format, 'Micronutrient Supplementation Log', 'Nutrition', 'bns_micronutrients', $columns, $this->listingQuery($request)->get(), $filters, $this->bnsUser()->assignedBarangay?->name, MicronutrientSupplementationLog::class, array_intersect_key($columns, array_flip(['Resident', 'Purok', 'Supplement', 'Recipient Category', 'Dose', 'Administered On', 'Distributed By'])));
    }

    private function listingQuery(Request $request): Builder
    {
        $query = $this->bnsMicronutrientLogsQuery()
            ->with(['resident.household.purok', 'distributedBy'])
            ->latest('administered_on')
            ->latest('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search): void {
                $builder->where('dose_description', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('resident', function ($residentQuery) use ($search): void {
                        $residentQuery->where('official_resident_code', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('supplement_type')) {
            $query->where('supplement_type', $request->string('supplement_type')->toString());
        }

        if ($request->filled('recipient_category')) {
            $query->where('recipient_category', $request->string('recipient_category')->toString());
        }

        return $query;
    }

    public function create(): View
    {
        $residentOptions = $this->bnsResidentsQuery()
            ->with(['household.purok', 'maternalNutritionProfile'])
            ->currentPopulation()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return view('bns.micronutrients.create', [
            'residentOptions' => $residentOptions,
            'supplementTypes' => MicronutrientSupplementationLog::SUPPLEMENT_TYPES,
            'recipientCategories' => MicronutrientSupplementationLog::RECIPIENT_CATEGORIES,
        ]);
    }

    public function store(StoreMicronutrientLogRequest $request): RedirectResponse
    {
        $log = MicronutrientSupplementationLog::query()->create([
            ...$request->validated(),
            'barangay_id' => $this->assignedBarangayId(),
            'distributed_by_user_id' => Auth::id(),
        ]);

        AuditLog::logMutation('created', Auth::user(), $log);

        return redirect()
            ->route('bns.micronutrients.index')
            ->with('success', 'Micronutrient supplementation log recorded successfully.');
    }
}
