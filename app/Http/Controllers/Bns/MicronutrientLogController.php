<?php

namespace App\Http\Controllers\Bns;

use App\Http\Controllers\Bns\Concerns\InteractsWithBnsScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bns\StoreMicronutrientLogRequest;
use App\Models\AuditLog;
use App\Models\MicronutrientSupplementationLog;
use App\Models\Resident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MicronutrientLogController extends Controller
{
    use InteractsWithBnsScope;

    public function index(Request $request): View
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

        return view('bns.micronutrients.index', [
            'logs' => $query->paginate(15)->withQueryString(),
            'supplementTypes' => MicronutrientSupplementationLog::SUPPLEMENT_TYPES,
            'recipientCategories' => MicronutrientSupplementationLog::RECIPIENT_CATEGORIES,
        ]);
    }

    public function create(): View
    {
        $residentOptions = $this->bnsResidentsQuery()
            ->with(['household.purok', 'maternalNutritionProfile'])
            ->where('resident_status', Resident::STATUS_ACTIVE)
            ->where('is_active', true)
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
