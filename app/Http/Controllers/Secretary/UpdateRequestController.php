<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Http\Requests\Secretary\ApplyProfileUpdateRequestRequest;
use App\Http\Requests\Secretary\ReviewDecisionRequest;
use App\Models\AuditLog;
use App\Models\Household;
use App\Models\ProfileUpdateRequest;
use App\Models\Resident;
use App\Support\ExportAudit;
use App\Support\SecretaryPipelineProcessor;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class UpdateRequestController extends Controller
{
    use InteractsWithSecretaryScope;

    public function index(Request $request): View
    {
        $updateRequests = $this->filteredQuery($request)
            ->with([
                'submittedBy.assignedPurok',
                'reviewedBy',
                'resident.household.purok',
                'household.purok',
            ])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('secretary.update-requests.index', [
            'updateRequests' => $updateRequests,
            'puroks' => $this->secretaryPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $updateRequests = $this->filteredQuery($request)
            ->with([
                'submittedBy.assignedPurok',
                'reviewedBy',
                'resident.household.purok',
                'household.purok',
            ])
            ->latest()
            ->get();

        $columns = [
            'Subject Type' => fn (ProfileUpdateRequest $updateRequest) => $updateRequest->subject_label,
            'Subject' => fn (ProfileUpdateRequest $updateRequest) => $updateRequest->subject_name,
            'Submitted By' => fn (ProfileUpdateRequest $updateRequest) => $updateRequest->submittedBy?->name ?? 'Unknown',
            'Status' => fn (ProfileUpdateRequest $updateRequest) => $updateRequest->request_status_label,
            'Reason' => 'request_reason',
            'Submitted At' => fn (ProfileUpdateRequest $updateRequest) => $updateRequest->created_at?->format('Y-m-d H:i:s'),
            'Reviewed At' => fn (ProfileUpdateRequest $updateRequest) => $updateRequest->reviewed_at?->format('Y-m-d H:i:s') ?? 'N/A',
        ];

        $filters = [
            'Search' => $request->input('search'),
            'Type' => $request->input('subject_type'),
            'Status' => $request->input('status'),
        ];

        ExportAudit::log('secretary update requests', $format, [
            'model_type' => ProfileUpdateRequest::class,
            'record_count' => $updateRequests->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("secretary_update_requests_{$timestamp}.csv", $columns, $updateRequests),
            'xlsx' => TabularExport::xlsx("secretary_update_requests_{$timestamp}.xlsx", 'Update Requests', $columns, $updateRequests),
            'pdf' => TabularExport::pdf("secretary_update_requests_{$timestamp}.pdf", 'Secretary Correction Request Queue', $columns, $updateRequests, $filters),
            default => abort(404),
        };
    }

    public function show(ProfileUpdateRequest $profileUpdateRequest): View
    {
        $this->ensureProfileUpdateRequestBelongsToBarangay($profileUpdateRequest);

        $profileUpdateRequest->load([
            'submittedBy.assignedPurok',
            'reviewedBy',
            'resident.household.purok',
            'household.purok',
            'household.headResident',
        ]);

        return view('secretary.update-requests.show', [
            'profileUpdateRequest' => $profileUpdateRequest,
            'currentSnapshot' => $this->snapshotRows($profileUpdateRequest->current_snapshot ?? []),
            'proposedChanges' => $this->snapshotRows($profileUpdateRequest->proposed_changes ?? []),
        ]);
    }

    public function edit(ProfileUpdateRequest $profileUpdateRequest): View
    {
        $this->ensureProfileUpdateRequestBelongsToBarangay($profileUpdateRequest);

        if ($profileUpdateRequest->request_status !== ProfileUpdateRequest::STATUS_PENDING) {
            return redirect()
                ->route('secretary.update-requests.show', $profileUpdateRequest)
                ->with('error', 'Only pending correction requests can still be applied or rejected.');
        }

        $profileUpdateRequest->load([
            'submittedBy.assignedPurok',
            'resident.household.purok',
            'household.purok',
            'household.headResident',
            'household.residents',
        ]);

        return view('secretary.update-requests.edit', [
            'profileUpdateRequest' => $profileUpdateRequest,
            'households' => $this->secretaryHouseholdsQuery()
                ->with('purok')
                ->active()
                ->orderBy('household_no')
                ->get(),
            'puroks' => $this->secretaryPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function approve(
        ApplyProfileUpdateRequestRequest $request,
        ProfileUpdateRequest $profileUpdateRequest,
        SecretaryPipelineProcessor $processor
    ): RedirectResponse {
        $this->ensureProfileUpdateRequestBelongsToBarangay($profileUpdateRequest);

        if ($profileUpdateRequest->request_status !== ProfileUpdateRequest::STATUS_PENDING) {
            return back()->with('error', 'This correction request has already been reviewed.');
        }

        $subject = $processor->applyProfileUpdateRequest($profileUpdateRequest, $request->validated(), Auth::user());

        return redirect()
            ->to($this->approvedSubjectRoute($profileUpdateRequest, $subject))
            ->with('success', "{$profileUpdateRequest->subject_label} correction request has been approved and applied.");
    }

    public function reject(ReviewDecisionRequest $request, ProfileUpdateRequest $profileUpdateRequest): RedirectResponse
    {
        $this->ensureProfileUpdateRequestBelongsToBarangay($profileUpdateRequest);

        if ($profileUpdateRequest->request_status !== ProfileUpdateRequest::STATUS_PENDING) {
            return back()->with('error', 'This correction request has already been reviewed.');
        }

        $oldValues = $profileUpdateRequest->toArray();

        $profileUpdateRequest->forceFill([
            'request_status' => ProfileUpdateRequest::STATUS_REJECTED,
            'reviewed_by_user_id' => Auth::id(),
            'reviewed_at' => now(),
            'review_notes' => $request->validated('review_notes'),
        ])->save();

        AuditLog::logMutation('updated', Auth::user(), $profileUpdateRequest, $oldValues, $profileUpdateRequest->fresh()->toArray());

        return back()->with('success', "{$profileUpdateRequest->subject_label} correction request has been rejected.");
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->secretaryProfileUpdateRequestsQuery();

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('status')) {
            $query->where('request_status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('request_reason', 'like', "%{$search}%")
                    ->orWhereHas('submittedBy', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhere(function (Builder $nested) use ($search): void {
                        $nested->where('subject_type', ProfileUpdateRequest::SUBJECT_RESIDENT)
                            ->whereHas('resident', function (Builder $residentQuery) use ($search): void {
                                $residentQuery->where('first_name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%");
                            });
                    })
                    ->orWhere(function (Builder $nested) use ($search): void {
                        $nested->where('subject_type', ProfileUpdateRequest::SUBJECT_HOUSEHOLD)
                            ->whereHas('household', function (Builder $householdQuery) use ($search): void {
                                $householdQuery->where('household_no', 'like', "%{$search}%")
                                    ->orWhere('household_address', 'like', "%{$search}%");
                            });
                    });
            });
        }

        return $query;
    }

    private function snapshotRows(array $snapshot): array
    {
        return collect($snapshot)
            ->map(function (mixed $value, string $key): array {
                return [
                    'label' => str($key)->replace('_', ' ')->title()->toString(),
                    'value' => is_bool($value) ? ($value ? 'Yes' : 'No') : ($value === null || $value === '' ? 'N/A' : (string) $value),
                ];
            })
            ->values()
            ->all();
    }

    private function approvedSubjectRoute(ProfileUpdateRequest $profileUpdateRequest, mixed $subject): string
    {
        return match ($profileUpdateRequest->subject_type) {
            ProfileUpdateRequest::SUBJECT_RESIDENT => route('secretary.residents.show', $subject),
            ProfileUpdateRequest::SUBJECT_HOUSEHOLD => route('secretary.households.show', $subject),
            default => route('secretary.update-requests.show', $profileUpdateRequest),
        };
    }
}
