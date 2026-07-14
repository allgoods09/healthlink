<?php

namespace App\Http\Controllers\Admin\Oversight;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\HouseholdDraft;
use App\Models\ProfileUpdateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class FieldOperationsMonitorController extends Controller
{
    public function __invoke(Request $request): View
    {
        $today = now()->toDateString();
        $barangayId = $request->integer('barangay_id');

        $draftsQuery = HouseholdDraft::query()
            ->with(['barangay', 'purok', 'submittedBy', 'reviewedBy'])
            ->withCount('residentDrafts')
            ->when($barangayId, fn ($query) => $query->where('barangay_id', $barangayId));

        $updateRequestsQuery = ProfileUpdateRequest::query()
            ->with(['barangay', 'submittedBy', 'reviewedBy', 'resident.household.purok', 'household.purok'])
            ->when($barangayId, fn ($query) => $query->where('barangay_id', $barangayId));

        $barangayBreakdown = Barangay::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function (Barangay $barangay): array {
                return [
                    'barangay' => $barangay,
                    'pending_draft_count' => HouseholdDraft::query()
                        ->where('barangay_id', $barangay->id)
                        ->pending()
                        ->count(),
                    'reviewed_draft_count' => HouseholdDraft::query()
                        ->where('barangay_id', $barangay->id)
                        ->whereIn('draft_status', [HouseholdDraft::STATUS_APPROVED, HouseholdDraft::STATUS_REJECTED])
                        ->count(),
                    'pending_update_count' => ProfileUpdateRequest::query()
                        ->where('barangay_id', $barangay->id)
                        ->pending()
                        ->count(),
                    'reviewed_update_count' => ProfileUpdateRequest::query()
                        ->where('barangay_id', $barangay->id)
                        ->whereIn('request_status', [ProfileUpdateRequest::STATUS_APPROVED, ProfileUpdateRequest::STATUS_REJECTED])
                        ->count(),
                ];
            });

        return view('admin.oversight.field-operations', [
            'barangays' => Barangay::query()->active()->orderBy('name')->get(),
            'selectedBarangay' => $barangayId ? Barangay::find($barangayId) : null,
            'pendingDraftCount' => (clone $draftsQuery)->pending()->count(),
            'reviewedDraftTodayCount' => (clone $draftsQuery)
                ->whereDate('reviewed_at', $today)
                ->whereIn('draft_status', [HouseholdDraft::STATUS_APPROVED, HouseholdDraft::STATUS_REJECTED])
                ->count(),
            'pendingUpdateRequestCount' => (clone $updateRequestsQuery)->pending()->count(),
            'reviewedUpdateTodayCount' => (clone $updateRequestsQuery)
                ->whereDate('reviewed_at', $today)
                ->whereIn('request_status', [ProfileUpdateRequest::STATUS_APPROVED, ProfileUpdateRequest::STATUS_REJECTED])
                ->count(),
            'recentPendingDrafts' => (clone $draftsQuery)
                ->pending()
                ->latest()
                ->limit(10)
                ->get(),
            'recentPendingUpdateRequests' => (clone $updateRequestsQuery)
                ->pending()
                ->latest()
                ->limit(10)
                ->get(),
            'recentlyReviewedDrafts' => (clone $draftsQuery)
                ->whereIn('draft_status', [HouseholdDraft::STATUS_APPROVED, HouseholdDraft::STATUS_REJECTED])
                ->latest('reviewed_at')
                ->limit(8)
                ->get(),
            'recentlyReviewedUpdateRequests' => (clone $updateRequestsQuery)
                ->whereIn('request_status', [ProfileUpdateRequest::STATUS_APPROVED, ProfileUpdateRequest::STATUS_REJECTED])
                ->latest('reviewed_at')
                ->limit(8)
                ->get(),
            'barangayBreakdown' => $barangayBreakdown,
            'breakdownPeak' => $this->resolveBreakdownPeak($barangayBreakdown),
        ]);
    }

    private function resolveBreakdownPeak(Collection $rows): int
    {
        return (int) $rows
            ->map(fn (array $row) => max(
                $row['pending_draft_count'],
                $row['reviewed_draft_count'],
                $row['pending_update_count'],
                $row['reviewed_update_count'],
                1
            ))
            ->max();
    }
}
