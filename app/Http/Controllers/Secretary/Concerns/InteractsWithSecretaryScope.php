<?php

namespace App\Http\Controllers\Secretary\Concerns;

use App\Models\AuditLog;
use App\Models\BarangayCertificate;
use App\Models\FieldVisit;
use App\Models\HouseholdDraft;
use App\Models\Household;
use App\Models\Purok;
use App\Models\ProfileUpdateRequest;
use App\Models\Resident;
use App\Models\SyncLog;
use App\Models\TriageRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithSecretaryScope
{
    protected function secretaryUser(): User
    {
        /** @var User $user */
        $user = request()->user();

        return $user;
    }

    protected function assignedBarangayId(): int
    {
        return (int) $this->secretaryUser()->assigned_barangay_id;
    }

    protected function secretaryPuroksQuery(): Builder
    {
        return Purok::query()->where('barangay_id', $this->assignedBarangayId());
    }

    protected function secretaryHouseholdsQuery(): Builder
    {
        return Household::query()->whereHas('purok', function (Builder $query): void {
            $query->where('barangay_id', $this->assignedBarangayId());
        });
    }

    protected function secretaryResidentsQuery(): Builder
    {
        return Resident::query()->whereHas('household.purok', function (Builder $query): void {
            $query->where('barangay_id', $this->assignedBarangayId());
        });
    }

    protected function secretaryCertificatesQuery(): Builder
    {
        return BarangayCertificate::query()->where('barangay_id', $this->assignedBarangayId());
    }

    protected function secretaryHouseholdDraftsQuery(): Builder
    {
        return HouseholdDraft::query()->where('barangay_id', $this->assignedBarangayId());
    }

    protected function secretaryProfileUpdateRequestsQuery(): Builder
    {
        return ProfileUpdateRequest::query()->where('barangay_id', $this->assignedBarangayId());
    }

    protected function secretaryTriageRecordsQuery(): Builder
    {
        return TriageRecord::query()->where('barangay_id', $this->assignedBarangayId());
    }

    protected function secretaryActivityQuery(): Builder
    {
        return AuditLog::query()
            ->with('user')
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $nested): void {
                        $nested->where('model_type', Purok::class)
                            ->whereIn('model_id', $this->secretaryPuroksQuery()->select('id'));
                    })
                    ->orWhere(function (Builder $nested): void {
                        $nested->where('model_type', Household::class)
                            ->whereIn('model_id', $this->secretaryHouseholdsQuery()->select('id'));
                    })
                    ->orWhere(function (Builder $nested): void {
                        $nested->where('model_type', Resident::class)
                            ->whereIn('model_id', $this->secretaryResidentsQuery()->select('id'));
                    })
                    ->orWhere(function (Builder $nested): void {
                        $nested->where('model_type', BarangayCertificate::class)
                            ->whereIn('model_id', $this->secretaryCertificatesQuery()->select('id'));
                    })
                    ->orWhere(function (Builder $nested): void {
                        $nested->where('model_type', User::class)
                            ->whereIn('model_id', $this->barangayUsersQuery()->select('id'));
                    })
                    ->orWhere(function (Builder $nested): void {
                        $nested->where('model_type', SyncLog::class)
                            ->whereIn('model_id', $this->secretarySyncLogsQuery()->select('id'));
                    })
                    ->orWhere(function (Builder $nested): void {
                        $nested->where('model_type', FieldVisit::class)
                            ->whereIn('model_id', $this->secretaryFieldVisitsQuery()->select('id'));
                    })
                    ->orWhere(function (Builder $nested): void {
                        $nested->where('model_type', HouseholdDraft::class)
                            ->whereIn('model_id', $this->secretaryHouseholdDraftsQuery()->select('id'));
                    })
                    ->orWhere(function (Builder $nested): void {
                        $nested->where('model_type', ProfileUpdateRequest::class)
                            ->whereIn('model_id', $this->secretaryProfileUpdateRequestsQuery()->select('id'));
                    })
                    ->orWhere(function (Builder $nested): void {
                        $nested->where('model_type', TriageRecord::class)
                            ->whereIn('model_id', $this->secretaryTriageRecordsQuery()->select('id'));
                    });
            });
    }

    protected function secretaryFrontlineUsersQuery(): Builder
    {
        return User::query()
            ->whereIn('role', ['bhw', 'bns'])
            ->where(function (Builder $query): void {
                $query->where('assigned_barangay_id', $this->assignedBarangayId())
                    ->orWhere('requested_barangay_id', $this->assignedBarangayId());
            });
    }

    protected function barangayUsersQuery(): Builder
    {
        return User::query()->where(function (Builder $query): void {
            $query->where('assigned_barangay_id', $this->assignedBarangayId())
                ->orWhere('requested_barangay_id', $this->assignedBarangayId());
        });
    }

    protected function secretarySyncLogsQuery(): Builder
    {
        return SyncLog::query()->whereHas('user', function (Builder $query): void {
            $query->where('assigned_barangay_id', $this->assignedBarangayId());
        });
    }

    protected function secretaryFieldVisitsQuery(): Builder
    {
        return FieldVisit::query()->whereHas('household.purok', function (Builder $query): void {
            $query->where('barangay_id', $this->assignedBarangayId());
        });
    }

    protected function ensurePurokBelongsToBarangay(Purok $purok): void
    {
        if ((int) $purok->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureHouseholdBelongsToBarangay(Household $household): void
    {
        $household->loadMissing('purok');

        if ((int) $household->purok?->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureResidentBelongsToBarangay(Resident $resident): void
    {
        $resident->loadMissing('household.purok');

        if ((int) $resident->household?->purok?->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureCertificateBelongsToBarangay(BarangayCertificate $certificate): void
    {
        if ((int) $certificate->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureHouseholdDraftBelongsToBarangay(HouseholdDraft $householdDraft): void
    {
        if ((int) $householdDraft->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureProfileUpdateRequestBelongsToBarangay(ProfileUpdateRequest $profileUpdateRequest): void
    {
        if ((int) $profileUpdateRequest->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureTriageRecordBelongsToBarangay(TriageRecord $triageRecord): void
    {
        if ((int) $triageRecord->barangay_id !== $this->assignedBarangayId()) {
            abort(404);
        }
    }

    protected function ensureAuditLogBelongsToBarangay(AuditLog $auditLog): void
    {
        if (! $this->secretaryActivityQuery()->whereKey($auditLog->id)->exists()) {
            abort(404);
        }
    }
}
