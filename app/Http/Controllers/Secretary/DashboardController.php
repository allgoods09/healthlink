<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Secretary\Concerns\InteractsWithSecretaryScope;
use App\Models\BarangayCertificate;
use App\Models\HouseholdDraft;
use App\Models\Purok;
use App\Models\ProfileUpdateRequest;
use App\Models\Resident;
use App\Models\TriageRecord;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithSecretaryScope;

    public function __invoke(): View
    {
        $activeResidents = $this->secretaryResidentsQuery()->where('resident_status', Resident::STATUS_ACTIVE)->count();
        $deceasedResidents = $this->secretaryResidentsQuery()->where('resident_status', Resident::STATUS_DECEASED)->count();
        $relocatedResidents = $this->secretaryResidentsQuery()->where('resident_status', Resident::STATUS_RELOCATED)->count();
        $households = $this->secretaryHouseholdsQuery()->count();
        $seniors = $this->secretaryResidentsQuery()->whereDate('birth_date', '<=', now()->subYears(60))->count();
        $minors = $this->secretaryResidentsQuery()->whereDate('birth_date', '>', now()->subYears(18))->count();
        $headlessHouseholds = $this->secretaryHouseholdsQuery()->whereNull('head_resident_id')->count();
        $pendingFrontlineApprovals = $this->secretaryFrontlineUsersQuery()
            ->where('approval_status', User::APPROVAL_PENDING)
            ->count();
        $pendingDraftPackages = $this->secretaryHouseholdDraftsQuery()
            ->where('draft_status', HouseholdDraft::STATUS_PENDING)
            ->count();
        $pendingUpdateRequests = $this->secretaryProfileUpdateRequestsQuery()
            ->where('request_status', ProfileUpdateRequest::STATUS_PENDING)
            ->count();
        $pendingTriageQueue = $this->secretaryTriageRecordsQuery()
            ->where('triage_status', TriageRecord::STATUS_PENDING)
            ->count();

        $purokDensity = $this->secretaryPuroksQuery()
            ->with(['households.residents'])
            ->active()
            ->orderBy('purok_number')
            ->get()
            ->map(function (Purok $purok): array {
                $residents = $purok->households->flatMap(fn ($household) => $household->residents);

                return [
                    'purok' => $purok,
                    'households' => $purok->households->count(),
                    'active_residents' => $residents->where('resident_status', Resident::STATUS_ACTIVE)->count(),
                    'deceased' => $residents->where('resident_status', Resident::STATUS_DECEASED)->count(),
                    'relocated' => $residents->where('resident_status', Resident::STATUS_RELOCATED)->count(),
                ];
            })
            ->sortByDesc('active_residents')
            ->values();

        return view('secretary.dashboard', [
            'activeResidents' => $activeResidents,
            'deceasedResidents' => $deceasedResidents,
            'relocatedResidents' => $relocatedResidents,
            'householdCount' => $households,
            'seniorCount' => $seniors,
            'minorCount' => $minors,
            'headlessHouseholdCount' => $headlessHouseholds,
            'certificateCount' => $this->secretaryCertificatesQuery()->count(),
            'monthlyCertificateCount' => $this->secretaryCertificatesQuery()->whereMonth('issued_at', now()->month)->whereYear('issued_at', now()->year)->count(),
            'pendingFrontlineApprovals' => $pendingFrontlineApprovals,
            'pendingDraftPackages' => $pendingDraftPackages,
            'pendingUpdateRequests' => $pendingUpdateRequests,
            'pendingTriageQueue' => $pendingTriageQueue,
            'purokDensity' => $purokDensity,
            'recentFrontlineRegistrations' => $this->secretaryFrontlineUsersQuery()
                ->with(['requestedBarangay', 'assignedPurok'])
                ->where('approval_status', User::APPROVAL_PENDING)
                ->latest()
                ->limit(5)
                ->get(),
            'recentCertificates' => $this->secretaryCertificatesQuery()
                ->with(['resident', 'household.headResident'])
                ->latest('issued_at')
                ->limit(5)
                ->get(),
            'recentActivity' => $this->secretaryActivityQuery()
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
