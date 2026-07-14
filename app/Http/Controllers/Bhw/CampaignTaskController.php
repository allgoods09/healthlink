<?php

namespace App\Http\Controllers\Bhw;

use App\Http\Controllers\Bhw\Concerns\InteractsWithBhwScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bhw\UpdateCommunityCampaignAssignmentRequest;
use App\Models\AuditLog;
use App\Models\CommunityCampaignAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CampaignTaskController extends Controller
{
    use InteractsWithBhwScope;

    public function index(Request $request): View
    {
        $query = $this->bhwCampaignAssignmentsQuery()
            ->with(['campaign.assignedPurok', 'resident.household.purok', 'household.purok'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('assignment_status', $request->string('status')->toString());
        }

        if ($request->boolean('due_today')) {
            $query->whereHas('campaign', fn ($campaignQuery) => $campaignQuery->whereDate('scheduled_for', now()->toDateString()));
        }

        return view('bhw.campaigns.index', [
            'assignments' => $query->paginate(12)->withQueryString(),
            'dueTodayCount' => $this->bhwCampaignAssignmentsQuery()
                ->whereHas('campaign', fn ($campaignQuery) => $campaignQuery->whereDate('scheduled_for', now()->toDateString()))
                ->count(),
        ]);
    }

    public function show(CommunityCampaignAssignment $assignment): View
    {
        $this->ensureCampaignAssignmentBelongsToBhw($assignment);

        $assignment->load(['campaign.assignedPurok', 'resident.household.purok', 'household.purok']);

        return view('bhw.campaigns.show', [
            'assignment' => $assignment,
        ]);
    }

    public function update(UpdateCommunityCampaignAssignmentRequest $request, CommunityCampaignAssignment $assignment): RedirectResponse
    {
        $this->ensureCampaignAssignmentBelongsToBhw($assignment);

        $oldValues = $assignment->toArray();
        $status = $request->string('assignment_status')->toString();

        $assignment->update([
            'assignment_status' => $status,
            'field_notes' => $request->input('field_notes'),
            'completed_at' => $status === CommunityCampaignAssignment::STATUS_PENDING ? null : now(),
        ]);

        AuditLog::logMutation('updated', Auth::user(), $assignment, $oldValues, $assignment->fresh()->toArray());

        return redirect()
            ->route('bhw.campaigns.show', $assignment)
            ->with('success', 'Campaign roster entry updated successfully.');
    }
}
