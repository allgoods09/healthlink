<?php

namespace App\Http\Controllers\Bhw;

use App\Http\Controllers\Bhw\Concerns\InteractsWithBhwScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bhw\StoreNutritionAssessmentFlagRequest;
use App\Models\AuditLog;
use App\Models\ChildNutritionAssessmentFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NutritionFlagController extends Controller
{
    use InteractsWithBhwScope;

    public function index(Request $request): View
    {
        $query = ChildNutritionAssessmentFlag::query()
            ->where('flagged_by_user_id', Auth::id())
            ->where('barangay_id', $this->assignedBarangayId())
            ->with(['resident.household.purok', 'closedBy', 'resolvedMeasurement'])
            ->latest('flagged_at');

        if ($request->filled('status')) {
            $query->where('flag_status', $request->string('status')->toString());
        }

        return view('bhw.nutrition-flags.index', [
            'flags' => $query->paginate(12)->withQueryString(),
            'openCount' => $this->bhwOpenNutritionFlagsQuery()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $selectedResident = null;

        if ($request->filled('resident_id')) {
            $selectedResident = $this->bhwEligibleChildrenQuery()
                ->with('household.purok')
                ->find($request->integer('resident_id'));
        }

        return view('bhw.nutrition-flags.create', [
            'selectedResident' => $selectedResident,
            'residentOptions' => $this->bhwEligibleChildrenQuery()
                ->with('household.purok')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(StoreNutritionAssessmentFlagRequest $request): RedirectResponse
    {
        $resident = $this->bhwEligibleChildrenQuery()
            ->with('household.purok')
            ->findOrFail($request->integer('resident_id'));

        $flag = ChildNutritionAssessmentFlag::query()->create([
            'resident_id' => $resident->id,
            'barangay_id' => $this->assignedBarangayId(),
            'purok_id' => $resident->household->purok_id,
            'flagged_by_user_id' => Auth::id(),
            'flag_status' => ChildNutritionAssessmentFlag::STATUS_OPEN,
            'flag_reason' => $request->string('flag_reason')->toString(),
            'flagged_at' => now(),
        ]);

        AuditLog::logMutation('created', Auth::user(), $flag);

        return redirect()
            ->route('bhw.nutrition-flags.index')
            ->with('success', 'Nutrition assessment flag sent to the BNS follow-up queue.');
    }
}
