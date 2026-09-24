<?php

namespace App\Http\Controllers\Admin\Oversight;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\HouseholdDraft;
use App\Models\ProfileUpdateRequest;
use App\Support\ExportDownload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class FieldOperationsMonitorController extends Controller
{
    public function __invoke(Request $request): View
    {
        $today = now()->toDateString();
        $barangayId = $request->integer('barangay_id');

        $draftsQuery = $this->draftsQuery($barangayId);
        $updateRequestsQuery = $this->updateRequestsQuery($barangayId);

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
            'recentPendingDrafts' => $this->panelQuery($barangayId, 'pending-drafts')->limit(10)->get(),
            'recentPendingUpdateRequests' => $this->panelQuery($barangayId, 'pending-requests')->limit(10)->get(),
            'recentlyReviewedDrafts' => $this->panelQuery($barangayId, 'reviewed-drafts')->limit(8)->get(),
            'recentlyReviewedUpdateRequests' => $this->panelQuery($barangayId, 'reviewed-requests')->limit(8)->get(),
            'barangayBreakdown' => $barangayBreakdown,
            'breakdownPeak' => $this->resolveBreakdownPeak($barangayBreakdown),
        ]);
    }

    public function export(Request $request, string $dataset, string $format): Response
    {
        $definitions = [
            'pending-drafts' => [
                'title' => 'Pending Draft Packages',
                'model' => HouseholdDraft::class,
                'columns' => [
                    'Reference' => 'draft_reference_code',
                    'Barangay' => fn (HouseholdDraft $draft) => $draft->barangay?->name,
                    'Purok' => fn (HouseholdDraft $draft) => $draft->purok?->display_name,
                    'Residents' => 'resident_drafts_count',
                    'Submitted By' => fn (HouseholdDraft $draft) => $draft->submittedBy?->name,
                    'Submitted At' => fn (HouseholdDraft $draft) => $draft->created_at?->format('Y-m-d H:i:s'),
                ],
            ],
            'pending-requests' => [
                'title' => 'Pending Correction Requests',
                'model' => ProfileUpdateRequest::class,
                'columns' => [
                    'Subject' => fn (ProfileUpdateRequest $item) => $item->subject_name,
                    'Type' => fn (ProfileUpdateRequest $item) => $item->subject_label,
                    'Barangay' => fn (ProfileUpdateRequest $item) => $item->barangay?->name,
                    'Reason' => 'request_reason',
                    'Submitted By' => fn (ProfileUpdateRequest $item) => $item->submittedBy?->name,
                    'Submitted At' => fn (ProfileUpdateRequest $item) => $item->created_at?->format('Y-m-d H:i:s'),
                ],
            ],
            'reviewed-drafts' => [
                'title' => 'Recently Reviewed Drafts',
                'model' => HouseholdDraft::class,
                'columns' => [
                    'Reference' => 'draft_reference_code',
                    'Barangay' => fn (HouseholdDraft $draft) => $draft->barangay?->name,
                    'Purok' => fn (HouseholdDraft $draft) => $draft->purok?->display_name,
                    'Status' => fn (HouseholdDraft $draft) => $draft->draft_status_label,
                    'Reviewed By' => fn (HouseholdDraft $draft) => $draft->reviewedBy?->name,
                    'Reviewed At' => fn (HouseholdDraft $draft) => $draft->reviewed_at?->format('Y-m-d H:i:s'),
                ],
            ],
            'reviewed-requests' => [
                'title' => 'Recently Reviewed Requests',
                'model' => ProfileUpdateRequest::class,
                'columns' => [
                    'Subject' => fn (ProfileUpdateRequest $item) => $item->subject_name,
                    'Type' => fn (ProfileUpdateRequest $item) => $item->subject_label,
                    'Barangay' => fn (ProfileUpdateRequest $item) => $item->barangay?->name,
                    'Status' => fn (ProfileUpdateRequest $item) => $item->request_status_label,
                    'Reviewed By' => fn (ProfileUpdateRequest $item) => $item->reviewedBy?->name,
                    'Reviewed At' => fn (ProfileUpdateRequest $item) => $item->reviewed_at?->format('Y-m-d H:i:s'),
                ],
            ],
        ];

        abort_unless(isset($definitions[$dataset]), 404);
        $definition = $definitions[$dataset];
        $barangayId = $request->integer('barangay_id');
        $barangayName = $barangayId ? Barangay::findOrFail($barangayId)->name : 'Municipality-wide';

        return ExportDownload::make($format, $definition['title'], 'Field Operations Oversight', 'field_'.$dataset, $definition['columns'], $this->panelQuery($barangayId, $dataset)->get(), ['Barangay' => $barangayId ? $barangayName : null], $barangayName, $definition['model']);
    }

    private function draftsQuery(int $barangayId): Builder
    {
        return HouseholdDraft::query()
            ->with(['barangay', 'purok', 'submittedBy', 'reviewedBy'])
            ->withCount('residentDrafts')
            ->when($barangayId, fn (Builder $query) => $query->where('barangay_id', $barangayId));
    }

    private function updateRequestsQuery(int $barangayId): Builder
    {
        return ProfileUpdateRequest::query()
            ->with(['barangay', 'submittedBy', 'reviewedBy', 'resident.household.purok', 'household.purok'])
            ->when($barangayId, fn (Builder $query) => $query->where('barangay_id', $barangayId));
    }

    private function panelQuery(int $barangayId, string $dataset): Builder
    {
        return match ($dataset) {
            'pending-drafts' => $this->draftsQuery($barangayId)->pending()->latest()->latest('id'),
            'pending-requests' => $this->updateRequestsQuery($barangayId)->pending()->latest()->latest('id'),
            'reviewed-drafts' => $this->draftsQuery($barangayId)
                ->whereIn('draft_status', [HouseholdDraft::STATUS_APPROVED, HouseholdDraft::STATUS_REJECTED])
                ->latest('reviewed_at')->latest('id'),
            'reviewed-requests' => $this->updateRequestsQuery($barangayId)
                ->whereIn('request_status', [ProfileUpdateRequest::STATUS_APPROVED, ProfileUpdateRequest::STATUS_REJECTED])
                ->latest('reviewed_at')->latest('id'),
            default => abort(404),
        };
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
