<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Http\Requests\Secretary\ApproveHouseholdDraftRequest;
use App\Http\Requests\Secretary\ReviewDecisionRequest;
use App\Models\AuditLog;
use App\Models\HouseholdDraft;
use App\Models\Purok;
use App\Support\ExportAudit;
use App\Support\SecretaryPipelineProcessor;
use App\Support\TabularExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class FieldDraftController extends Controller
{
    use InteractsWithSecretaryScope;

    public function index(Request $request): View
    {
        $drafts = $this->filteredQuery($request)
            ->with(['purok', 'submittedBy', 'reviewedBy', 'approvedHousehold'])
            ->withCount('residentDrafts')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('secretary.drafts.index', [
            'drafts' => $drafts,
            'puroks' => $this->secretaryPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function export(Request $request, string $format): Response
    {
        $drafts = $this->filteredQuery($request)
            ->with(['purok', 'submittedBy', 'reviewedBy', 'approvedHousehold'])
            ->withCount('residentDrafts')
            ->latest()
            ->get();

        $columns = [
            'Reference' => 'draft_reference_code',
            'Purok' => fn (HouseholdDraft $draft) => $draft->purok?->display_name ?? 'Unassigned',
            'Address' => 'household_address',
            'Residents' => 'resident_drafts_count',
            'Status' => fn (HouseholdDraft $draft) => $draft->draft_status_label,
            'Submitted By' => fn (HouseholdDraft $draft) => $draft->submittedBy?->name ?? 'Unknown',
            'Reviewed By' => fn (HouseholdDraft $draft) => $draft->reviewedBy?->name ?? 'N/A',
            'Submitted At' => fn (HouseholdDraft $draft) => $draft->created_at?->format('Y-m-d H:i:s'),
            'Reviewed At' => fn (HouseholdDraft $draft) => $draft->reviewed_at?->format('Y-m-d H:i:s') ?? 'N/A',
        ];

        $filters = [
            'Search' => $request->input('search'),
            'Purok' => Purok::query()->find($request->input('purok_id'))?->display_name,
            'Status' => $request->input('status'),
        ];

        ExportAudit::log('secretary field drafts', $format, [
            'model_type' => HouseholdDraft::class,
            'record_count' => $drafts->count(),
            'filters' => array_filter($filters),
        ]);

        $timestamp = now()->format('Y-m-d_His');

        return match ($format) {
            'csv' => TabularExport::csv("secretary_field_drafts_{$timestamp}.csv", $columns, $drafts),
            'xlsx' => TabularExport::xlsx("secretary_field_drafts_{$timestamp}.xlsx", 'Field Drafts', $columns, $drafts),
            'pdf' => TabularExport::pdf("secretary_field_drafts_{$timestamp}.pdf", 'Secretary Field Draft Queue', $columns, $drafts, $filters),
            default => abort(404),
        };
    }

    public function show(HouseholdDraft $householdDraft): View
    {
        $this->ensureHouseholdDraftBelongsToBarangay($householdDraft);

        $householdDraft->load([
            'purok',
            'submittedBy.assignedPurok',
            'reviewedBy',
            'residentDrafts',
            'approvedHousehold.purok',
            'approvedHousehold.headResident',
        ]);

        return view('secretary.drafts.show', [
            'householdDraft' => $householdDraft,
        ]);
    }

    public function edit(HouseholdDraft $householdDraft): View
    {
        $this->ensureHouseholdDraftBelongsToBarangay($householdDraft);

        if ($householdDraft->draft_status !== HouseholdDraft::STATUS_PENDING) {
            return redirect()
                ->route('secretary.drafts.show', $householdDraft)
                ->with('error', 'Only pending field drafts can still be reviewed and approved.');
        }

        $householdDraft->load(['purok', 'submittedBy.assignedPurok', 'residentDrafts']);

        return view('secretary.drafts.edit', [
            'householdDraft' => $householdDraft,
            'puroks' => $this->secretaryPuroksQuery()->active()->orderBy('purok_number')->get(),
        ]);
    }

    public function approve(
        ApproveHouseholdDraftRequest $request,
        HouseholdDraft $householdDraft,
        SecretaryPipelineProcessor $processor
    ): RedirectResponse {
        $this->ensureHouseholdDraftBelongsToBarangay($householdDraft);

        if ($householdDraft->draft_status !== HouseholdDraft::STATUS_PENDING) {
            return back()->with('error', 'This field draft has already been reviewed.');
        }

        $household = $processor->approveHouseholdDraft($householdDraft, $request->validated(), Auth::user());

        return redirect()
            ->route('secretary.households.show', $household)
            ->with('success', "Field draft {$householdDraft->draft_reference_code} has been approved into Household #{$household->household_no}.");
    }

    public function reject(ReviewDecisionRequest $request, HouseholdDraft $householdDraft): RedirectResponse
    {
        $this->ensureHouseholdDraftBelongsToBarangay($householdDraft);

        if ($householdDraft->draft_status !== HouseholdDraft::STATUS_PENDING) {
            return back()->with('error', 'This field draft has already been reviewed.');
        }

        $oldValues = $householdDraft->toArray();

        $householdDraft->forceFill([
            'draft_status' => HouseholdDraft::STATUS_REJECTED,
            'reviewed_by_user_id' => Auth::id(),
            'reviewed_at' => now(),
            'verification_notes' => $request->validated('review_notes'),
        ])->save();

        AuditLog::logMutation('updated', Auth::user(), $householdDraft, $oldValues, $householdDraft->fresh()->toArray());

        return back()->with('success', "Field draft {$householdDraft->draft_reference_code} has been rejected.");
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = $this->secretaryHouseholdDraftsQuery();

        if ($request->filled('purok_id')) {
            $query->where('purok_id', $request->integer('purok_id'));
        }

        if ($request->filled('status')) {
            $query->where('draft_status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('draft_reference_code', 'like', "%{$search}%")
                    ->orWhere('household_address', 'like', "%{$search}%")
                    ->orWhereHas('submittedBy', function (Builder $userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('residentDrafts', function (Builder $residentDraftQuery) use ($search): void {
                        $residentDraftQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query;
    }
}
