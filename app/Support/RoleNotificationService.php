<?php

namespace App\Support;

use App\Models\ChildNutritionAssessmentFlag;
use App\Models\ClinicalEncounter;
use App\Models\Household;
use App\Models\HouseholdDraft;
use App\Models\MobileAppRelease;
use App\Models\PhilpenRiskAssessment;
use App\Models\ProfileUpdateRequest;
use App\Models\Resident;
use App\Models\TriageRecord;
use App\Models\User;
use App\Notifications\SystemDatabaseNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class RoleNotificationService
{
    public function notifySelfRegistrationPending(User $user): void
    {
        $targetRole = $user->requested_role ?: $user->role;

        if (in_array($targetRole, User::SECRETARY_APPROVAL_ROLES, true) && $user->requested_barangay_id) {
            $this->send(
                $this->secretariesForBarangay($user->requested_barangay_id),
                [
                    'title' => 'New frontline registration pending',
                    'body' => "{$user->name} requested {$user->role_label} access and is waiting for barangay approval.",
                    'level' => 'info',
                    'category' => 'registration',
                    'icon' => 'person-add-outline',
                    'action_url' => route('secretary.team.index', ['approval_status' => User::APPROVAL_PENDING]),
                    'action_label' => 'Review registrations',
                    'sender_name' => $user->name,
                    'metadata' => [
                        'user_id' => $user->id,
                        'requested_role' => $targetRole,
                        'requested_barangay_id' => $user->requested_barangay_id,
                    ],
                ]
            );

            return;
        }

        $this->send(
            $this->admins(),
            [
                'title' => 'New municipal registration pending',
                'body' => "{$user->name} requested {$user->role_label} access and needs municipal approval.",
                'level' => 'info',
                'category' => 'registration',
                'icon' => 'person-add-outline',
                'action_url' => route('admin.users.index', ['approval_status' => User::APPROVAL_PENDING]),
                'action_label' => 'Review registrations',
                'sender_name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id,
                    'requested_role' => $targetRole,
                ],
            ]
        );
    }

    public function notifyRegistrationReviewed(User $user, bool $approved, ?User $reviewer = null): void
    {
        $actionUrl = $approved ? route('login') : route('register');

        $this->send(
            $user,
            [
                'title' => $approved ? 'Registration approved' : 'Registration review completed',
                'body' => $approved
                    ? "Your {$user->role_label} account is now active. You can sign in to HealthLink."
                    : "Your {$user->role_label} registration was not approved. Please review the remarks from HealthLink staff.",
                'level' => $approved ? 'success' : 'warning',
                'category' => 'registration',
                'icon' => $approved ? 'checkmark-circle-outline' : 'alert-circle-outline',
                'action_url' => $actionUrl,
                'action_label' => $approved ? 'Open sign in' : 'Open registration',
                'sender_name' => $reviewer?->name,
                'metadata' => [
                    'approved' => $approved,
                    'reviewer_id' => $reviewer?->id,
                ],
            ]
        );
    }

    public function notifyFieldDraftSubmitted(HouseholdDraft $draft): void
    {
        $draft->loadMissing('submittedBy', 'purok');
        $submittedByName = $draft->submittedBy?->name ?? 'A BHW';
        $targetArea = $draft->purok?->display_name ?? 'the barangay';

        $this->send(
            $this->secretariesForBarangay($draft->barangay_id),
            [
                'title' => 'New field draft submitted',
                'body' => "{$submittedByName} submitted draft {$draft->draft_reference_code} for {$targetArea} review.",
                'level' => 'info',
                'category' => 'field_draft',
                'icon' => 'document-text-outline',
                'action_url' => route('secretary.drafts.show', $draft),
                'action_label' => 'Open field draft',
                'sender_name' => $draft->submittedBy?->name,
                'metadata' => [
                    'draft_id' => $draft->id,
                    'barangay_id' => $draft->barangay_id,
                ],
            ]
        );
    }

    public function notifyFieldDraftReviewed(HouseholdDraft $draft, bool $approved, ?User $reviewer = null): void
    {
        $draft->loadMissing('submittedBy', 'approvedHousehold');
        $approvedHouseholdNo = $draft->approvedHousehold?->household_no ?? 'N/A';

        $this->send(
            $draft->submittedBy,
            [
                'title' => $approved ? 'Field draft approved' : 'Field draft rejected',
                'body' => $approved
                    ? "Draft {$draft->draft_reference_code} is now live as Household #{$approvedHouseholdNo}."
                    : "Draft {$draft->draft_reference_code} was returned after review. Please check the remarks in HealthLink.",
                'level' => $approved ? 'success' : 'warning',
                'category' => 'field_draft',
                'icon' => $approved ? 'checkmark-done-outline' : 'close-circle-outline',
                'action_url' => route('bhw.drafts.show', $draft),
                'action_label' => 'Review draft result',
                'sender_name' => $reviewer?->name,
                'metadata' => [
                    'draft_id' => $draft->id,
                    'approved' => $approved,
                ],
            ]
        );
    }

    public function notifyProfileUpdateSubmitted(ProfileUpdateRequest $profileUpdateRequest): void
    {
        $profileUpdateRequest->loadMissing('submittedBy');

        $this->send(
            $this->secretariesForBarangay($profileUpdateRequest->barangay_id),
            [
                'title' => 'New correction request submitted',
                'body' => "{$profileUpdateRequest->submittedBy?->name} submitted a {$profileUpdateRequest->subject_label} correction request for {$profileUpdateRequest->subject_name}.",
                'level' => 'info',
                'category' => 'update_request',
                'icon' => 'create-outline',
                'action_url' => route('secretary.update-requests.show', $profileUpdateRequest),
                'action_label' => 'Open request',
                'sender_name' => $profileUpdateRequest->submittedBy?->name,
                'metadata' => [
                    'request_id' => $profileUpdateRequest->id,
                    'subject_type' => $profileUpdateRequest->subject_type,
                    'subject_id' => $profileUpdateRequest->subject_id,
                ],
            ]
        );
    }

    public function notifyProfileUpdateReviewed(ProfileUpdateRequest $profileUpdateRequest, bool $approved, ?User $reviewer = null): void
    {
        $profileUpdateRequest->loadMissing('submittedBy');

        $actionUrl = match ($profileUpdateRequest->submittedBy?->role) {
            'phn' => route('phn.update-requests.show', $profileUpdateRequest),
            'bhw' => route('bhw.update-requests.show', $profileUpdateRequest),
            default => null,
        };

        $this->send(
            $profileUpdateRequest->submittedBy,
            [
                'title' => $approved ? 'Correction request approved' : 'Correction request rejected',
                'body' => $approved
                    ? "{$profileUpdateRequest->subject_label} correction for {$profileUpdateRequest->subject_name} has been applied."
                    : "{$profileUpdateRequest->subject_label} correction for {$profileUpdateRequest->subject_name} was not approved.",
                'level' => $approved ? 'success' : 'warning',
                'category' => 'update_request',
                'icon' => $approved ? 'checkmark-circle-outline' : 'alert-circle-outline',
                'action_url' => $actionUrl,
                'action_label' => 'Review decision',
                'sender_name' => $reviewer?->name,
                'metadata' => [
                    'request_id' => $profileUpdateRequest->id,
                    'approved' => $approved,
                ],
            ]
        );
    }

    public function notifyNutritionFlagSubmitted(ChildNutritionAssessmentFlag $flag): void
    {
        $flag->loadMissing('resident', 'flaggedBy');
        $flaggedByName = $flag->flaggedBy?->name ?? 'A BHW';
        $residentName = $flag->resident?->formal_name ?? 'a child';

        $this->send(
            $this->bnsForBarangay($flag->barangay_id),
            [
                'title' => 'Child nutrition assessment requested',
                'body' => "{$flaggedByName} flagged {$residentName} for BNS nutrition follow-up.",
                'level' => 'warning',
                'category' => 'nutrition_flag',
                'icon' => 'nutrition-outline',
                'action_url' => route('bns.dashboard'),
                'action_label' => 'Open BNS dashboard',
                'sender_name' => $flag->flaggedBy?->name,
                'metadata' => [
                    'flag_id' => $flag->id,
                    'resident_id' => $flag->resident_id,
                ],
            ]
        );
    }

    public function notifyTriageSubmitted(TriageRecord $triageRecord): void
    {
        $triageRecord->loadMissing('resident', 'recordedBy', 'barangay');
        $residentName = $triageRecord->resident?->formal_name ?? 'A resident';
        $barangayName = $triageRecord->barangay?->name ?? 'the barangay';

        $this->send(
            $this->phnUsers(),
            [
                'title' => 'New triage waiting for PHN review',
                'body' => "{$residentName} now has a new BHW triage entry from {$barangayName} queue.",
                'level' => 'info',
                'category' => 'triage',
                'icon' => 'pulse-outline',
                'action_url' => route('phn.triage.index'),
                'action_label' => 'Open triage queue',
                'sender_name' => $triageRecord->recordedBy?->name,
                'metadata' => [
                    'triage_record_id' => $triageRecord->id,
                    'resident_id' => $triageRecord->resident_id,
                    'barangay_id' => $triageRecord->barangay_id,
                ],
            ]
        );
    }

    public function notifyEncounterEscalated(ClinicalEncounter $clinicalEncounter): void
    {
        if (! $clinicalEncounter->is_escalated_to_mho) {
            return;
        }

        $clinicalEncounter->loadMissing('resident', 'attendedBy', 'barangay');
        $residentName = $clinicalEncounter->resident?->formal_name ?? 'A resident';
        $attendedByName = $clinicalEncounter->attendedBy?->name ?? 'the PHN';

        $this->send(
            $this->mhoUsers(),
            [
                'title' => 'Clinical escalation requires MHO review',
                'body' => "{$residentName} was escalated by {$attendedByName} for municipal review.",
                'level' => 'warning',
                'category' => 'clinical_escalation',
                'icon' => 'medkit-outline',
                'action_url' => route('mho.escalations.index'),
                'action_label' => 'Open escalation queue',
                'sender_name' => $clinicalEncounter->attendedBy?->name,
                'metadata' => [
                    'clinical_encounter_id' => $clinicalEncounter->id,
                    'resident_id' => $clinicalEncounter->resident_id,
                ],
            ]
        );
    }

    public function notifyMhoReviewCompleted(ClinicalEncounter $clinicalEncounter, ?User $reviewer = null): void
    {
        $clinicalEncounter->loadMissing('resident', 'attendedBy');
        $residentName = $clinicalEncounter->resident?->formal_name ?? 'A resident';

        $this->send(
            $this->phnUsers(),
            [
                'title' => 'MHO review completed',
                'body' => "{$residentName} now has a completed municipal clinical review ready for PHN follow-up.",
                'level' => 'success',
                'category' => 'clinical_review',
                'icon' => 'checkmark-circle-outline',
                'action_url' => route('phn.encounters.show', $clinicalEncounter),
                'action_label' => 'Open encounter',
                'sender_name' => $reviewer?->name,
                'metadata' => [
                    'clinical_encounter_id' => $clinicalEncounter->id,
                ],
            ]
        );
    }

    public function notifyMobileReleasePublished(MobileAppRelease $release, ?User $publisher = null): void
    {
        $this->send(
            $this->bhwUsers(),
            [
                'title' => 'HealthLink BHW app update available',
                'body' => "Version {$release->version_name} is now available for download.",
                'level' => $release->update_mode === MobileAppRelease::UPDATE_REQUIRED ? 'warning' : 'info',
                'category' => 'mobile_release',
                'icon' => 'cloud-download-outline',
                'action_url' => route('mobile.bhw.update'),
                'action_label' => 'Open download page',
                'sender_name' => $publisher?->name,
                'metadata' => [
                    'release_id' => $release->id,
                    'version_name' => $release->version_name,
                    'version_code' => $release->version_code,
                    'update_mode' => $release->update_mode,
                ],
            ]
        );
    }

    public function notifyHouseholdAddedForSecretary(Household $household, User $actor, string $source = 'mobile'): void
    {
        $household->loadMissing('purok.barangay');
        $barangayId = $household->purok?->barangay_id;

        if (! $barangayId) {
            return;
        }

        $this->send(
            $this->secretariesForBarangay($barangayId),
            [
                'title' => 'New household added',
                'body' => "{$actor->name} added Household #{$household->household_no} through the {$source} workflow.",
                'level' => 'info',
                'category' => 'registry',
                'icon' => 'home-outline',
                'action_url' => route('secretary.households.show', $household),
                'action_label' => 'Open household',
                'sender_name' => $actor->name,
                'metadata' => [
                    'household_id' => $household->id,
                    'source' => $source,
                ],
            ]
        );
    }

    public function notifyResidentAddedForSecretary(Resident $resident, User $actor, string $source = 'mobile'): void
    {
        $resident->loadMissing('household.purok.barangay');
        $barangayId = $resident->household?->purok?->barangay_id;

        if (! $barangayId) {
            return;
        }

        $this->send(
            $this->secretariesForBarangay($barangayId),
            [
                'title' => 'New resident added',
                'body' => "{$actor->name} added {$resident->formal_name} through the {$source} workflow.",
                'level' => 'info',
                'category' => 'registry',
                'icon' => 'person-outline',
                'action_url' => route('secretary.residents.show', $resident),
                'action_label' => 'Open resident profile',
                'sender_name' => $actor->name,
                'metadata' => [
                    'resident_id' => $resident->id,
                    'source' => $source,
                ],
            ]
        );
    }

    public function notifyImmediateReferralRiskAssessment(PhilpenRiskAssessment $assessment, User $actor): void
    {
        if (! $assessment->requires_immediate_referral) {
            return;
        }

        $assessment->loadMissing('resident.household.purok.barangay');
        $residentName = $assessment->resident?->formal_name ?? 'A resident';

        $payload = [
            'title' => 'Immediate clinical referral flagged',
            'body' => "{$residentName} triggered urgent PhilPEN red flags and should be referred immediately.",
            'level' => 'warning',
            'category' => 'philpen',
            'icon' => 'warning-outline',
            'action_label' => 'Open resident profile',
            'sender_name' => $actor->name,
            'metadata' => [
                'risk_assessment_id' => $assessment->id,
                'resident_id' => $assessment->resident_id,
            ],
        ];

        $residentActionUrl = route('phn.residents.show', $assessment->resident_id);

        $this->send(
            $this->phnUsers(),
            array_merge($payload, [
                'action_url' => $residentActionUrl,
            ])
        );

        $this->send(
            $this->mhoUsers(),
            array_merge($payload, [
                'action_url' => route('mho.residents.show', $assessment->resident_id),
            ])
        );
    }

    /**
     * @param User|Collection<int, User>|EloquentCollection<int, User>|null $recipients
     * @param array<string, mixed> $payload
     */
    private function send(User|Collection|EloquentCollection|null $recipients, array $payload): void
    {
        if ($recipients instanceof User) {
            $recipients = collect([$recipients]);
        }

        if (! $recipients || $recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients->unique('id')->values(),
            new SystemDatabaseNotification($payload)
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function admins(): Collection
    {
        return $this->activeApprovedUsersQuery()
            ->where('role', 'admin')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function secretariesForBarangay(int $barangayId): Collection
    {
        return $this->activeApprovedUsersQuery()
            ->where('role', 'secretary')
            ->where('assigned_barangay_id', $barangayId)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function bnsForBarangay(int $barangayId): Collection
    {
        return $this->activeApprovedUsersQuery()
            ->where('role', 'bns')
            ->where('assigned_barangay_id', $barangayId)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function phnUsers(): Collection
    {
        return $this->activeApprovedUsersQuery()
            ->where('role', 'phn')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function mhoUsers(): Collection
    {
        return $this->activeApprovedUsersQuery()
            ->where('role', 'mho')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function bhwUsers(): Collection
    {
        return $this->activeApprovedUsersQuery()
            ->where('role', 'bhw')
            ->get();
    }

    private function activeApprovedUsersQuery()
    {
        return User::query()
            ->where('approval_status', User::APPROVAL_APPROVED)
            ->where('is_active', true);
    }
}
