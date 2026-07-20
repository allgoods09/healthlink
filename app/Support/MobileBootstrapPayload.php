<?php

namespace App\Support;

use App\Models\FieldVisit;
use App\Models\Household;
use App\Models\Resident;
use App\Models\User;

class MobileBootstrapPayload
{
    /**
     * Build a full bootstrap payload for the authenticated BHW.
     */
    public function build(User $user): array
    {
        $user->loadMissing(['assignedBarangay', 'assignedPurok.barangay']);

        $barangayId = $user->assigned_barangay_id;

        $households = Household::query()
            ->when($barangayId, fn ($query) => $query->whereHas('purok', fn ($purokQuery) => $purokQuery->where('barangay_id', $barangayId)))
            ->with([
                'purok.barangay',
            ])
            ->withCount('residents')
            ->orderBy('household_no')
            ->get();

        $residents = Resident::query()
            ->when($barangayId, fn ($query) => $query->whereHas('household.purok', fn ($purokQuery) => $purokQuery->where('barangay_id', $barangayId)))
            ->with([
                'household:id,mobile_uuid',
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $fieldVisits = FieldVisit::query()
            ->when($barangayId, fn ($query) => $query->whereHas('household.purok', fn ($purokQuery) => $purokQuery->where('barangay_id', $barangayId)))
            ->with([
                'household:id,mobile_uuid',
                'recordedBy:id,name',
            ])
            ->latest('visited_at')
            ->get();

        return [
            'server_time' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'approval_status' => $user->approval_status,
                'assignment_label' => $user->assignment_label,
                'locale' => 'en',
            ],
            'assignment' => [
                'barangay' => $user->assignedBarangay ? [
                    'id' => $user->assignedBarangay->id,
                    'name' => $user->assignedBarangay->name,
                    'municipality' => $user->assignedBarangay->municipality,
                    'province' => $user->assignedBarangay->province,
                ] : null,
                'purok' => $user->assignedPurok ? [
                    'id' => $user->assignedPurok->id,
                    'purok_number' => $user->assignedPurok->purok_number,
                    'purok_name' => $user->assignedPurok->purok_name,
                    'display_name' => $user->assignedPurok->display_name,
                ] : null,
            ],
            'households' => $households
                ->map(fn (Household $household) => $this->householdPayload($household))
                ->values()
                ->all(),
            'residents' => $residents
                ->map(fn ($resident) => [
                    'id' => $resident->id,
                    'mobile_uuid' => $resident->mobile_uuid,
                    'household_id' => $resident->household_id,
                    'household_mobile_uuid' => $resident->household?->mobile_uuid,
                    'philsys_card_no' => $resident->philsys_card_no,
                    'last_name' => $resident->last_name,
                    'first_name' => $resident->first_name,
                    'middle_name' => $resident->middle_name,
                    'suffix' => $resident->suffix,
                    'birth_date' => optional($resident->birth_date)->toDateString(),
                    'birth_place' => $resident->birth_place,
                    'sex' => $resident->sex,
                    'civil_status' => $resident->civil_status,
                    'citizenship' => $resident->citizenship,
                    'religion' => $resident->religion,
                    'contact_number' => $resident->contact_number,
                    'email_address' => $resident->email_address,
                    'relationship_to_head' => $resident->relationship_to_head,
                    'is_active' => $resident->is_active,
                    'updated_at' => optional($resident->updated_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
            'field_visits' => $fieldVisits
                ->map(fn ($visit) => [
                    'id' => $visit->id,
                    'mobile_uuid' => $visit->mobile_uuid,
                    'household_id' => $visit->household_id,
                    'household_mobile_uuid' => $visit->household?->mobile_uuid,
                    'recorded_by_user_id' => $visit->recorded_by_user_id,
                    'recorded_by_name' => $visit->recordedBy?->name,
                    'visited_at' => optional($visit->visited_at)->toIso8601String(),
                    'notes' => $visit->notes,
                    'photo_count' => $visit->photo_count,
                    'photos' => collect($visit->photos ?? [])->map(fn (array $photo) => [
                        'path' => $photo['path'] ?? null,
                        'file_name' => $photo['file_name'] ?? null,
                        'mime_type' => $photo['mime_type'] ?? null,
                        'file_size_bytes' => $photo['file_size_bytes'] ?? null,
                        'captured_at' => $photo['captured_at'] ?? null,
                    ])->values()->all(),
                    'updated_at' => optional($visit->updated_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
            'sync' => [
                'mode' => 'full-bootstrap',
                'requires_initial_download' => true,
                'supports_manual_upload' => true,
                'supports_auto_upload_when_online' => false,
                'supported_locales' => ['en', 'ceb'],
            ],
        ];
    }

    /**
     * Build a mobile-friendly household payload.
     */
    private function householdPayload(Household $household): array
    {
        return [
            'id' => $household->id,
            'mobile_uuid' => $household->mobile_uuid,
            'purok_id' => $household->purok_id,
            'purok_display_name' => $household->purok?->display_name,
            'household_no' => $household->household_no,
            'household_address' => $household->household_address,
            'is_social_aid_beneficiary' => $household->is_social_aid_beneficiary,
            'is_active' => $household->is_active,
            'resident_count' => $household->residents_count ?? $household->residents()->count(),
            'updated_at' => optional($household->updated_at)->toIso8601String(),
        ];
    }
}
