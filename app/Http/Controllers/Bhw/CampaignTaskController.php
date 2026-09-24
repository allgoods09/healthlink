<?php

namespace App\Http\Controllers\Bhw;

use App\Http\Controllers\Bhw\Concerns\InteractsWithBhwScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bhw\UpdateCommunityCampaignAssignmentRequest;
use App\Models\AuditLog;
use App\Models\CommunityCampaignAssignment;
use App\Support\ExportDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CampaignTaskController extends Controller
{
    use InteractsWithBhwScope;

    public function index(Request $request): View
    {
        return view('bhw.campaigns.index', [
            'assignments' => $this->listingQuery($request)->paginate(12)->withQueryString(),
            'dueTodayCount' => $this->bhwCampaignAssignmentsQuery()
                ->whereHas('campaign', fn ($campaignQuery) => $campaignQuery->whereDate('scheduled_for', now()->toDateString()))
                ->count(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $columns = [
            'Campaign' => fn (CommunityCampaignAssignment $assignment) => $assignment->campaign?->title ?? 'Untitled',
            'Type' => fn (CommunityCampaignAssignment $assignment) => $assignment->campaign?->campaign_type_label ?? 'Unknown',
            'Scheduled For' => fn (CommunityCampaignAssignment $assignment) => $assignment->campaign?->scheduled_for?->format('Y-m-d'),
            'Target' => fn (CommunityCampaignAssignment $assignment) => $assignment->target_label,
            'Status' => fn (CommunityCampaignAssignment $assignment) => $assignment->assignment_status_label,
            'Completed At' => fn (CommunityCampaignAssignment $assignment) => $assignment->completed_at?->format('Y-m-d H:i:s'),
        ];

        return ExportDownload::make($format, 'BHW Campaign Tasks', 'Community Campaigns', 'bhw_campaign_tasks', $columns, $this->listingQuery($request)->get(), ['Status' => $request->input('status'), 'Due Today' => $request->boolean('due_today') ? 'Yes' : null], $this->bhwUser()->assignedBarangay?->name, CommunityCampaignAssignment::class);
    }

    private function listingQuery(Request $request): Builder
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

        return $query;
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
