<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\BarangayCertificate;
use App\Models\ChildNutritionAssessmentFlag;
use App\Models\CommunityCampaign;
use App\Models\CommunityCampaignAssignment;
use App\Models\FeedingProgram;
use App\Models\FeedingProgramAttendance;
use App\Models\FeedingProgramEnrollment;
use App\Models\FeedingProgramProgressLog;
use App\Models\FieldVisit;
use App\Models\Household;
use App\Models\HouseholdDraft;
use App\Models\InfantFeedingLog;
use App\Models\MaternalNutritionHistory;
use App\Models\MaternalNutritionProfile;
use App\Models\MicronutrientSupplementationLog;
use App\Models\NutritionCampaignPeriod;
use App\Models\OptMeasurement;
use App\Models\ProfileUpdateRequest;
use App\Models\Purok;
use App\Models\Resident;
use App\Models\ResidentDraft;
use App\Models\ResidentSocioEconomicProfile;
use App\Models\TriageRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MockOperationalDataSeeder extends Seeder
{
    private string $defaultPasswordHash;

    private const WATER_SOURCES = [
        'Tubigon Water District Connection',
        'Barangay Deep Well',
        'Shared Jetmatic Pump',
        'Protected Spring',
        'Rainwater Drum Storage',
    ];

    private const TOILET_TYPES = [
        'Water-sealed',
        'Pour-flush',
        'Septic Tank',
    ];

    private const MALE_NAMES = [
        'Rodel', 'Junrey', 'Nestor', 'Vicente', 'Danilo', 'Joel', 'Ramil', 'Crisanto', 'Benjie', 'Alfredo', 'Nanding', 'Rex',
    ];

    private const FEMALE_NAMES = [
        'Rosalina', 'Nenita', 'Maricel', 'Analyn', 'Jocelyn', 'Merlita', 'Vilma', 'Gemma', 'Lilibeth', 'Rowena', 'Jovelyn', 'Nerissa',
    ];

    private const LAST_NAMES = [
        'Caballes', 'Lepiten', 'Bantilan', 'Labrador', 'Polinar', 'Maboloc', 'Asoy', 'Lumain', 'Pabe', 'Badiang', 'Villareal', 'Magallanes',
    ];

    private const MIDDLE_NAMES = [
        'Bantilan', 'Caballes', 'Lepiten', 'Polinar', 'Labrador', 'Asoy', 'Pabe', 'Badiang',
    ];

    private const ADULT_OCCUPATIONS = [
        'Copra Farmer', 'Fisherfolk', 'Tricycle Driver', 'Market Vendor', 'Construction Laborer', 'Sari-sari Store Owner', 'Barangay Utility Worker', 'Seaweed Gatherer',
    ];

    private const RELIGIONS = [
        'Roman Catholic', 'Roman Catholic', 'Roman Catholic', 'Seventh-day Adventist', 'Iglesia Ni Cristo', 'UCCP',
    ];

    private const BOHOL_BIRTH_PLACES = [
        'Tubigon, Bohol',
        'Clarin, Bohol',
        'Calape, Bohol',
        'Inabanga, Bohol',
        'Sagbayan, Bohol',
        'Catigbian, Bohol',
        'Tagbilaran City, Bohol',
    ];

    private const PUROK_NAMES = [
        1 => 'Purok Centro',
        2 => 'Purok Baybay',
        3 => 'Purok Ilaya',
        4 => 'Purok Luyo',
        5 => 'Purok Riverside',
        6 => 'Purok Crossing',
        7 => 'Purok Proper',
    ];

    private const HOUSEHOLD_LOCATION_MARKERS = [
        'Near the barangay chapel',
        'Along the barangay road',
        'Beside the covered court',
        'Interior sitio cluster',
        'Near the shoreline access',
    ];

    /**
     * Seed operational mock data for demo and testing flows.
     */
    public function run(): void
    {
        $this->defaultPasswordHash = Hash::make('password');

        $admin = $this->upsertUser([
            'name' => 'Tubigon HealthLink Admin',
            'email' => 'admin@healthlink.com',
            'role' => 'admin',
            'assigned_barangay_id' => null,
            'assigned_purok_id' => null,
        ]);

        $phn = $this->upsertUser([
            'name' => 'Tubigon Municipal PHN',
            'email' => 'phn@healthlink.com',
            'role' => 'phn',
            'assigned_barangay_id' => null,
            'assigned_purok_id' => null,
        ], $admin);

        $mho = $this->upsertUser([
            'name' => 'Tubigon Municipal MHO',
            'email' => 'mho@healthlink.com',
            'role' => 'mho',
            'assigned_barangay_id' => null,
            'assigned_purok_id' => null,
        ], $admin);

        $barangays = Barangay::query()->orderBy('name')->get();

        $firstSecretary = null;
        $firstBns = null;
        $firstBhw = null;
        $firstPurok = null;

        foreach ($barangays as $barangayIndex => $barangay) {
            $slug = Str::slug($barangay->name);

            $secretary = $this->upsertUser([
                'name' => $this->staffDisplayName('secretary', $barangayIndex),
                'email' => "secretary.{$slug}@healthlink.test",
                'role' => 'secretary',
                'assigned_barangay_id' => $barangay->id,
                'assigned_purok_id' => null,
            ], $admin);

            $bns = $this->upsertUser([
                'name' => $this->staffDisplayName('bns', $barangayIndex + 17),
                'email' => "bns.{$slug}@healthlink.test",
                'role' => 'bns',
                'assigned_barangay_id' => $barangay->id,
                'assigned_purok_id' => null,
            ], $admin);

            $purokContexts = collect();

            for ($purokNumber = 1; $purokNumber <= 7; $purokNumber++) {
                $purok = Purok::query()->updateOrCreate(
                    [
                        'barangay_id' => $barangay->id,
                        'purok_number' => $purokNumber,
                    ],
                    [
                        'purok_name' => $this->purokNameFor($purokNumber),
                        'is_active' => true,
                    ]
                );

                $bhw = $this->upsertUser([
                    'name' => $this->staffDisplayName('bhw', ($barangayIndex * 7) + $purokNumber + 31),
                    'email' => "bhw.{$slug}.p{$purokNumber}@healthlink.test",
                    'role' => 'bhw',
                    'assigned_barangay_id' => $barangay->id,
                    'assigned_purok_id' => $purok->id,
                ], $secretary);

                [$households, $residentsByHousehold] = $this->seedPurokHouseholds(
                    $barangay,
                    $purok,
                    $barangayIndex,
                    $purokNumber
                );

                $purokContexts->push([
                    'purok' => $purok,
                    'bhw' => $bhw,
                    'households' => $households,
                    'residents_by_household' => $residentsByHousehold,
                ]);

                if ($firstBhw === null) {
                    $firstBhw = $bhw;
                    $firstPurok = $purok;
                }
            }

            $this->seedBarangayArtifacts(
                $barangay,
                $secretary,
                $bns,
                $phn,
                $mho,
                $purokContexts,
                $barangayIndex
            );

            $firstSecretary ??= $secretary;
            $firstBns ??= $bns;
        }

        if ($barangays->isNotEmpty()) {
            $firstBarangay = $barangays->first();

            if ($firstSecretary) {
                $this->upsertUser([
                    'name' => 'Tubigon Demo Secretary',
                    'email' => 'secretary@healthlink.com',
                    'role' => 'secretary',
                    'assigned_barangay_id' => $firstBarangay->id,
                    'assigned_purok_id' => null,
                ], $admin);
            }

            if ($firstBns) {
                $this->upsertUser([
                    'name' => 'Tubigon Demo BNS',
                    'email' => 'bns@healthlink.com',
                    'role' => 'bns',
                    'assigned_barangay_id' => $firstBarangay->id,
                    'assigned_purok_id' => null,
                ], $admin);
            }

            if ($firstBhw && $firstPurok) {
                $this->upsertUser([
                    'name' => 'Tubigon Demo BHW',
                    'email' => 'bhw@healthlink.com',
                    'role' => 'bhw',
                    'assigned_barangay_id' => $firstBarangay->id,
                    'assigned_purok_id' => $firstPurok->id,
                ], $admin);
            }
        }
    }

    private function seedPurokHouseholds(Barangay $barangay, Purok $purok, int $barangayIndex, int $purokNumber): array
    {
        $households = collect();
        $residentsByHousehold = collect();

        for ($householdIndex = 1; $householdIndex <= 5; $householdIndex++) {
            $barangaySequence = (($purokNumber - 1) * 5) + $householdIndex;
            $householdNo = str_pad((string) $barangaySequence, 3, '0', STR_PAD_LEFT);
            $waterSource = self::WATER_SOURCES[($barangayIndex + $purokNumber + $householdIndex) % count(self::WATER_SOURCES)];
            $hasToilet = (($householdIndex + $purokNumber) % 4) !== 0;
            $garbageMethodKeys = array_keys(Household::GARBAGE_DISPOSAL_METHODS);
            $housingMaterialKeys = array_keys(Household::HOUSING_MATERIAL_TYPES);

            $household = Household::query()->updateOrCreate(
                [
                    'purok_id' => $purok->id,
                    'household_no' => $householdNo,
                ],
                [
                    'official_household_code' => sprintf('HH-%04d-%05d', $barangay->id, $barangaySequence),
                    'mobile_uuid' => (string) Str::uuid(),
                    'household_address' => $this->householdAddress($barangay, $purok, $householdIndex),
                    'drinking_water_source' => $waterSource,
                    'has_sanitary_toilet' => $hasToilet,
                    'sanitary_toilet_type' => $hasToilet ? self::TOILET_TYPES[($householdIndex + $purokNumber) % count(self::TOILET_TYPES)] : null,
                    'garbage_disposal_method' => $garbageMethodKeys[($barangayIndex + $householdIndex) % count($garbageMethodKeys)],
                    'has_backyard_garden' => ($householdIndex % 2) === 0,
                    'housing_material_type' => $housingMaterialKeys[($purokNumber + $householdIndex) % count($housingMaterialKeys)],
                    'is_social_aid_beneficiary' => ($householdIndex % 3) === 0,
                    'is_active' => true,
                ]
            );

            $residents = collect();

            for ($residentIndex = 1; $residentIndex <= 4; $residentIndex++) {
                $residentSequence = (($purokNumber - 1) * 20) + (($householdIndex - 1) * 4) + $residentIndex;
                $data = $this->residentPayload(
                    $barangay,
                    $household,
                    $barangayIndex,
                    $purokNumber,
                    $householdIndex,
                    $residentIndex,
                    $residentSequence
                );
                $data['household_id'] = $household->id;

                $resident = Resident::query()->updateOrCreate(
                    ['official_resident_code' => $data['official_resident_code']],
                    $data
                );

                ResidentSocioEconomicProfile::query()->updateOrCreate(
                    ['resident_id' => $resident->id],
                    $this->residentSocioEconomicPayload($residentIndex, $householdIndex)
                );

                $residents->push($resident->fresh(['socioEconomicProfile']));
            }

            $headResident = $residents->firstWhere('relationship_to_head', 'Head of Household') ?? $residents->first();
            $household->forceFill(['head_resident_id' => $headResident?->id])->saveQuietly();

            $households->push($household->fresh(['headResident', 'purok']));
            $residentsByHousehold->push($residents);
        }

        return [$households, $residentsByHousehold];
    }

    private function seedBarangayArtifacts(
        Barangay $barangay,
        User $secretary,
        User $bns,
        User $phn,
        User $mho,
        Collection $purokContexts,
        int $barangayIndex
    ): void {
        $allHouseholds = $purokContexts->pluck('households')->flatten(1)->values();
        $allResidents = $purokContexts->pluck('residents_by_household')->flatten(2)->values();
        $underFiveResidents = $allResidents
            ->filter(fn (Resident $resident) => Carbon::parse($resident->birth_date)->diffInMonths(now()) <= 71)
            ->values();

        $campaign = CommunityCampaign::query()->updateOrCreate(
            [
                'barangay_id' => $barangay->id,
                'title' => "Mass Deworming {$barangay->name}",
            ],
            [
                'created_by_user_id' => $secretary->id,
                'assigned_purok_id' => null,
                'campaign_type' => CommunityCampaign::TYPE_DEWORMING,
                'scheduled_for' => now()->toDateString(),
                'description' => "Quarterly deworming roster prepared for purok coverage in {$barangay->name}, Tubigon.",
                'campaign_status' => CommunityCampaign::STATUS_ACTIVE,
            ]
        );

        foreach ($purokContexts as $index => $context) {
            /** @var User $bhw */
            $bhw = $context['bhw'];
            /** @var Household $leadHousehold */
            $leadHousehold = $context['households']->first();
            /** @var Resident $leadResident */
            $leadResident = $context['residents_by_household']->first()->first();
            $assignmentStatus = ($index % 3 === 0)
                ? CommunityCampaignAssignment::STATUS_COMPLETED
                : CommunityCampaignAssignment::STATUS_PENDING;

            CommunityCampaignAssignment::query()->updateOrCreate(
                [
                    'community_campaign_id' => $campaign->id,
                    'assigned_bhw_user_id' => $bhw->id,
                    'household_id' => $leadHousehold->id,
                ],
                [
                    'resident_id' => $leadResident->id,
                    'assignment_status' => $assignmentStatus,
                    'completed_at' => $assignmentStatus === CommunityCampaignAssignment::STATUS_COMPLETED ? now()->subDays(1) : null,
                    'field_notes' => $assignmentStatus === CommunityCampaignAssignment::STATUS_COMPLETED
                        ? 'Completed during routine Tubigon purok round.'
                        : 'For follow-up during the next barangay health station round.',
                ]
            );

            FieldVisit::query()->updateOrCreate(
                [
                    'household_id' => $leadHousehold->id,
                    'recorded_by_user_id' => $bhw->id,
                    'visited_at' => now()->subDays(3 + $index)->setTime(9, 15),
                ],
                [
                    'mobile_uuid' => (string) Str::uuid(),
                    'notes' => 'Routine household check-in with environmental survey validation for Tubigon field mapping.',
                    'photos' => [],
                    'source' => 'mobile',
                    'last_synced_at' => now()->subDays(2 + $index),
                ]
            );

            TriageRecord::query()->updateOrCreate(
                [
                    'resident_id' => $leadResident->id,
                    'recorded_by_user_id' => $bhw->id,
                    'measured_at' => now()->subDays(2 + $index)->setTime(10, 0),
                ],
                [
                    'household_id' => $leadHousehold->id,
                    'barangay_id' => $barangay->id,
                    'purok_id' => $context['purok']->id,
                    'consumed_by_user_id' => $index % 3 === 0 ? $phn->id : ($index % 3 === 1 ? null : $mho->id),
                    'triage_status' => $index % 3 === 0
                        ? TriageRecord::STATUS_REVIEWED
                        : ($index % 3 === 1 ? TriageRecord::STATUS_PENDING : TriageRecord::STATUS_CLOSED),
                    'bp_systolic' => 110 + ($index % 10),
                    'bp_diastolic' => 70 + ($index % 8),
                    'heart_rate' => 74 + ($index % 6),
                    'temperature_celsius' => 36.5 + (($index % 4) * 0.1),
                    'respiratory_rate' => 18 + ($index % 4),
                    'blood_glucose_mg_dl' => 94.5 + ($index % 9),
                    'triage_notes' => 'Walk-in triage captured at the barangay health station before PHN or MHO review.',
                    'consumed_at' => $index % 3 === 1 ? null : now()->subDays(1 + $index)->setTime(14, 0),
                ]
            );
        }

        $firstContext = $purokContexts->first();
        $firstBhw = $firstContext['bhw'];
        $firstHousehold = $firstContext['households']->first();
        $firstResidents = $firstContext['residents_by_household']->first();
        $firstHead = $firstResidents->first();
        $firstAdultFemale = $firstResidents->first(fn (Resident $resident) => $resident->sex === 'Female' && Carbon::parse($resident->birth_date)->age >= 18);
        $firstChild = $firstResidents->first(fn (Resident $resident) => Carbon::parse($resident->birth_date)->diffInMonths(now()) <= 71);

        $draft = HouseholdDraft::query()->updateOrCreate(
            ['draft_reference_code' => sprintf('SEED-HD-%04d', $barangay->id)],
            [
                'submitted_by_user_id' => $firstBhw->id,
                'barangay_id' => $barangay->id,
                'purok_id' => $firstContext['purok']->id,
                'household_address' => "Interior cluster near {$firstContext['purok']->purok_name}, {$barangay->name}, Tubigon, Bohol",
                'drinking_water_source' => 'Shared Jetmatic Pump',
                'has_sanitary_toilet' => true,
                'sanitary_toilet_type' => 'Water-sealed',
                'garbage_disposal_method' => 'collection',
                'has_backyard_garden' => true,
                'housing_material_type' => 'mixed',
                'is_social_aid_beneficiary' => false,
                'draft_status' => HouseholdDraft::STATUS_PENDING,
                'verification_notes' => null,
            ]
        );

        ResidentDraft::query()->updateOrCreate(
            [
                'household_draft_id' => $draft->id,
                'philsys_card_no' => sprintf('DRAFT-%04d-01', $barangay->id),
            ],
            [
                'household_draft_id' => $draft->id,
                'last_name' => 'Caballes',
                'first_name' => 'Junard',
                'middle_name' => 'Lepiten',
                'birth_date' => now()->subYears(42)->toDateString(),
                'birth_place' => 'Tubigon, Bohol',
                'sex' => 'Male',
                'civil_status' => 'Married',
                'citizenship' => 'Filipino',
                'religion' => 'Roman Catholic',
                'contact_number' => '09171234501',
                'email_address' => null,
                'relationship_to_head' => 'Head of Household',
                'is_household_head_candidate' => true,
                'draft_notes' => 'Submitted during BHW field mapping in Tubigon.',
            ]
        );

        ResidentDraft::query()->updateOrCreate(
            [
                'household_draft_id' => $draft->id,
                'philsys_card_no' => sprintf('DRAFT-%04d-02', $barangay->id),
            ],
            [
                'household_draft_id' => $draft->id,
                'last_name' => 'Caballes',
                'first_name' => 'Marilou',
                'middle_name' => 'Lepiten',
                'birth_date' => now()->subYears(38)->toDateString(),
                'birth_place' => 'Tubigon, Bohol',
                'sex' => 'Female',
                'civil_status' => 'Married',
                'citizenship' => 'Filipino',
                'religion' => 'Roman Catholic',
                'contact_number' => '09171234502',
                'email_address' => null,
                'relationship_to_head' => 'Spouse',
                'is_household_head_candidate' => false,
                'draft_notes' => 'Included in the same Tubigon household draft package.',
            ]
        );

        ProfileUpdateRequest::query()->updateOrCreate(
            [
                'submitted_by_user_id' => $firstBhw->id,
                'barangay_id' => $barangay->id,
                'subject_type' => ProfileUpdateRequest::SUBJECT_RESIDENT,
                'subject_id' => $firstHead->id,
            ],
            [
                'current_snapshot' => [
                    'contact_number' => $firstHead->contact_number,
                    'religion' => $firstHead->religion,
                ],
                'proposed_changes' => [
                    'contact_number' => '09179990001',
                    'status_notes' => 'Requested during follow-up visit after purok validation.',
                ],
                'request_reason' => 'Updated resident contact number gathered during Tubigon field follow-up.',
                'request_status' => ProfileUpdateRequest::STATUS_PENDING,
            ]
        );

        ProfileUpdateRequest::query()->updateOrCreate(
            [
                'submitted_by_user_id' => $firstBhw->id,
                'barangay_id' => $barangay->id,
                'subject_type' => ProfileUpdateRequest::SUBJECT_HOUSEHOLD,
                'subject_id' => $firstHousehold->id,
            ],
            [
                'current_snapshot' => [
                    'household_address' => $firstHousehold->household_address,
                    'drinking_water_source' => $firstHousehold->drinking_water_source,
                ],
                'proposed_changes' => [
                    'household_address' => $firstHousehold->household_address.' (rear access updated)',
                    'drinking_water_source' => 'Protected Spring',
                ],
                'request_reason' => 'Revalidated household address and water source during Tubigon purok survey.',
                'request_status' => ProfileUpdateRequest::STATUS_PENDING,
            ]
        );

        BarangayCertificate::query()->updateOrCreate(
            ['certificate_no' => sprintf('CERT-%04d-R-001', $barangay->id)],
            [
                'barangay_id' => $barangay->id,
                'certificate_type' => BarangayCertificate::TYPE_CLEARANCE,
                'recipient_type' => BarangayCertificate::RECIPIENT_RESIDENT,
                'resident_id' => $firstHead->id,
                'household_id' => null,
                'issued_to_name' => $firstHead->formal_name,
                'purpose' => 'Requirement for Tubigon municipal medical assistance',
                'remarks' => 'Resident clearance sample for Tubigon health and social support filing.',
                'issued_at' => now()->subDays(4),
                'issued_by_user_id' => $secretary->id,
            ]
        );

        BarangayCertificate::query()->updateOrCreate(
            ['certificate_no' => sprintf('CERT-%04d-H-001', $barangay->id)],
            [
                'barangay_id' => $barangay->id,
                'certificate_type' => BarangayCertificate::TYPE_INDIGENCY,
                'recipient_type' => BarangayCertificate::RECIPIENT_HOUSEHOLD,
                'resident_id' => null,
                'household_id' => $firstHousehold->id,
                'issued_to_name' => 'Household #'.$firstHousehold->household_no,
                'purpose' => 'Indigency support for Tubigon medical assistance processing',
                'remarks' => 'Household indigency certificate sample for barangay records.',
                'issued_at' => now()->subDays(2),
                'issued_by_user_id' => $secretary->id,
            ]
        );

        $this->seedNutritionArtifacts($barangay, $bns, $firstBhw, $underFiveResidents, $firstAdultFemale, $firstChild, $barangayIndex);
    }

    private function seedNutritionArtifacts(
        Barangay $barangay,
        User $bns,
        User $firstBhw,
        Collection $underFiveResidents,
        ?Resident $maternalResident,
        ?Resident $firstChild,
        int $barangayIndex
    ): void {
        if ($underFiveResidents->isEmpty()) {
            return;
        }

        $year = now()->year;

        $optCampaign = NutritionCampaignPeriod::query()->updateOrCreate(
            [
                'barangay_id' => $barangay->id,
                'name' => "OPT+ {$year} Q3",
            ],
            [
                'created_by_user_id' => $bns->id,
                'campaign_type' => NutritionCampaignPeriod::TYPE_OPT_PLUS,
                'starts_on' => now()->startOfQuarter()->toDateString(),
                'ends_on' => now()->endOfQuarter()->toDateString(),
                'is_active' => true,
                'notes' => "Tubigon OPT+ campaign period for {$barangay->name}.",
            ]
        );

        $feedingCycle = NutritionCampaignPeriod::query()->updateOrCreate(
            [
                'barangay_id' => $barangay->id,
                'name' => "Feeding Cycle {$year} Q3",
            ],
            [
                'created_by_user_id' => $bns->id,
                'campaign_type' => NutritionCampaignPeriod::TYPE_FEEDING_PROGRAM,
                'starts_on' => now()->startOfQuarter()->toDateString(),
                'ends_on' => now()->endOfQuarter()->toDateString(),
                'is_active' => true,
                'notes' => "Tubigon supplementary feeding cycle for {$barangay->name}.",
            ]
        );

        $measurementProfiles = [
            ['z1' => -0.400, 's1' => 'Normal', 'z2' => -0.250, 's2' => 'Normal', 'z3' => -0.300, 's3' => 'Normal'],
            ['z1' => -2.200, 's1' => 'Underweight', 'z2' => -2.100, 's2' => 'Stunted', 'z3' => -2.300, 's3' => 'Wasted'],
            ['z1' => -3.100, 's1' => 'Severely Underweight', 'z2' => -3.000, 's2' => 'Severely Stunted', 'z3' => -3.200, 's3' => 'Severely Wasted'],
            ['z1' => 0.150, 's1' => 'Normal', 'z2' => 0.100, 's2' => 'Normal', 'z3' => 0.200, 's3' => 'Normal'],
            ['z1' => 1.800, 's1' => 'Overweight', 'z2' => 2.100, 's2' => 'Tall', 'z3' => 2.300, 's3' => 'Overweight'],
        ];

        $measuredChildren = $underFiveResidents->take(5)->values();

        foreach ($measuredChildren as $index => $child) {
            $measurementDate = now()->subDays(20 - $index);
            $ageInMonths = Carbon::parse($child->birth_date)->diffInMonths($measurementDate);
            $profile = $measurementProfiles[$index % count($measurementProfiles)];

            OptMeasurement::query()->updateOrCreate(
                [
                    'resident_id' => $child->id,
                    'campaign_period_id' => $optCampaign->id,
                    'measurement_date' => $measurementDate->toDateString(),
                ],
                [
                    'barangay_id' => $barangay->id,
                    'measured_by_user_id' => $bns->id,
                    'age_in_months' => $ageInMonths,
                    'sex_snapshot' => $child->sex,
                    'weight_kg' => 8.50 + $index,
                    'height_cm' => 72.00 + ($index * 4),
                    'measurement_posture' => $ageInMonths < 24 ? OptMeasurement::POSTURE_RECUMBENT : OptMeasurement::POSTURE_STANDING,
                    'weight_for_age_z_score' => $profile['z1'],
                    'weight_for_age_status' => $profile['s1'],
                    'height_for_age_z_score' => $profile['z2'],
                    'height_for_age_status' => $profile['s2'],
                    'weight_for_length_height_z_score' => $profile['z3'],
                    'weight_for_length_height_status' => $profile['s3'],
                    'remarks' => 'Recorded during barangay OPT+ measurement day in Tubigon.',
                ]
            );
        }

        $flaggedChild = $underFiveResidents->skip(5)->first() ?? $underFiveResidents->last();

        ChildNutritionAssessmentFlag::query()->updateOrCreate(
            [
                'resident_id' => $flaggedChild->id,
                'flag_status' => ChildNutritionAssessmentFlag::STATUS_OPEN,
            ],
            [
                'barangay_id' => $barangay->id,
                'purok_id' => $flaggedChild->household->purok_id,
                'flagged_by_user_id' => $firstBhw->id,
                'closed_by_user_id' => null,
                'resolved_measurement_id' => null,
                'flag_reason' => 'BHW observed possible undernutrition during routine Tubigon household visit.',
                'flagged_at' => now()->subDays(3),
            ]
        );

        $feedingProgram = FeedingProgram::query()->updateOrCreate(
            [
                'barangay_id' => $barangay->id,
                'name' => "Supplementary Feeding {$barangay->name}",
            ],
            [
                'campaign_period_id' => $feedingCycle->id,
                'created_by_user_id' => $bns->id,
                'starts_on' => now()->subWeek()->toDateString(),
                'ends_on' => now()->addWeeks(6)->toDateString(),
                'program_status' => FeedingProgram::STATUS_ACTIVE,
                'description' => 'Supplementary feeding roster for Tubigon nutrition recovery monitoring.',
            ]
        );

        $targetChild = $measuredChildren->get(1) ?? $measuredChildren->first();
        $targetMeasurement = OptMeasurement::query()
            ->where('resident_id', $targetChild->id)
            ->latest('measurement_date')
            ->first();

        $enrollment = FeedingProgramEnrollment::query()->updateOrCreate(
            [
                'feeding_program_id' => $feedingProgram->id,
                'resident_id' => $targetChild->id,
            ],
            [
                'enrolled_by_user_id' => $bns->id,
                'enrolled_on' => now()->subDays(5)->toDateString(),
                'baseline_weight_kg' => $targetMeasurement?->weight_kg,
                'baseline_nutritional_status' => $targetMeasurement?->weight_for_age_status,
                'is_active' => true,
                'completion_notes' => null,
            ]
        );

        FeedingProgramAttendance::query()->updateOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'attendance_date' => now()->subDays(4)->toDateString(),
            ],
            [
                'attendance_status' => 'present',
                'notes' => 'Child attended the scheduled Tubigon feeding session.',
            ]
        );

        FeedingProgramProgressLog::query()->updateOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'logged_on' => now()->subDays(2)->toDateString(),
            ],
            [
                'logged_by_user_id' => $bns->id,
                'week_number' => 1,
                'weight_kg' => ($targetMeasurement?->weight_kg ?? 8.5) + 0.2,
                'remarks' => 'Slight gain recorded after first week of barangay feeding.',
            ]
        );

        if ($maternalResident) {
            MaternalNutritionProfile::query()->updateOrCreate(
                ['resident_id' => $maternalResident->id],
                [
                    'barangay_id' => $barangay->id,
                    'updated_by_user_id' => $bns->id,
                    'is_currently_pregnant' => ($barangayIndex % 2) === 0,
                    'is_currently_lactating' => ($barangayIndex % 2) === 1,
                    'expected_delivery_date' => ($barangayIndex % 2) === 0 ? now()->addMonths(4)->toDateString() : null,
                    'current_risk_notes' => 'Maternal nutrition profile monitored during Tubigon barangay rounds.',
                    'last_status_updated_at' => now()->subDays(1),
                ]
            );

            MaternalNutritionHistory::query()->updateOrCreate(
                [
                    'resident_id' => $maternalResident->id,
                    'event_type' => MaternalNutritionHistory::EVENT_PREGNANCY_STATUS_CHANGE,
                    'event_date' => now()->subWeeks(6)->toDateString(),
                ],
                [
                    'recorded_by_user_id' => $bns->id,
                    'gestational_age_weeks' => 18,
                    'weight_kg' => 54.5,
                    'notes' => 'Pregnancy status confirmed during barangay nutrition visit.',
                ]
            );

            MaternalNutritionHistory::query()->updateOrCreate(
                [
                    'resident_id' => $maternalResident->id,
                    'event_type' => MaternalNutritionHistory::EVENT_PRENATAL_WEIGHT_CHECK,
                    'event_date' => now()->subWeeks(2)->toDateString(),
                ],
                [
                    'recorded_by_user_id' => $bns->id,
                    'gestational_age_weeks' => 24,
                    'weight_kg' => 56.2,
                    'notes' => 'Prenatal follow-up weight check recorded during Tubigon field monitoring.',
                ]
            );
        }

        if ($firstChild && $maternalResident) {
            InfantFeedingLog::query()->updateOrCreate(
                [
                    'resident_id' => $firstChild->id,
                    'observed_on' => now()->subDays(7)->toDateString(),
                ],
                [
                    'mother_resident_id' => $maternalResident->id,
                    'recorded_by_user_id' => $bns->id,
                    'feeding_method' => InfantFeedingLog::METHOD_MIXED_FEEDING,
                    'notes' => 'Observed complementary feeding transition during home visitation.',
                ]
            );
        }

        if ($firstChild) {
            MicronutrientSupplementationLog::query()->updateOrCreate(
                [
                    'resident_id' => $firstChild->id,
                    'supplement_type' => MicronutrientSupplementationLog::TYPE_VITAMIN_A,
                    'administered_on' => now()->subDays(6)->toDateString(),
                ],
                [
                    'barangay_id' => $barangay->id,
                    'distributed_by_user_id' => $bns->id,
                    'recipient_category' => MicronutrientSupplementationLog::RECIPIENT_TODDLER,
                    'dose_description' => '100,000 IU',
                    'remarks' => 'Vitamin A distributed during Tubigon child nutrition round.',
                ]
            );
        }

        if ($maternalResident) {
            MicronutrientSupplementationLog::query()->updateOrCreate(
                [
                    'resident_id' => $maternalResident->id,
                    'supplement_type' => MicronutrientSupplementationLog::TYPE_MNP,
                    'administered_on' => now()->subDays(5)->toDateString(),
                ],
                [
                    'barangay_id' => $barangay->id,
                    'distributed_by_user_id' => $bns->id,
                    'recipient_category' => MicronutrientSupplementationLog::RECIPIENT_PREGNANT_WOMAN,
                    'dose_description' => '1 sachet daily starter pack',
                    'remarks' => 'Starter micronutrient pack released during maternal follow-up.',
                ]
            );
        }
    }

    private function residentPayload(
        Barangay $barangay,
        Household $household,
        int $barangayIndex,
        int $purokNumber,
        int $householdIndex,
        int $residentIndex,
        int $residentSequence
    ): array {
        $familyName = self::LAST_NAMES[($barangayIndex + $purokNumber + $householdIndex) % count(self::LAST_NAMES)];
        $femaleHead = (($barangayIndex + $purokNumber + $householdIndex) % 4) === 0;

        if ($residentIndex === 1) {
            $sex = $femaleHead ? 'Female' : 'Male';
            $firstName = $this->personName($sex, $residentSequence);
            $birthDate = now()->subYears(34 + (($barangayIndex + $purokNumber + $householdIndex) % 18))->subMonths($purokNumber % 6);
            $religion = $this->religionFor($residentSequence + $barangayIndex);

            return [
                'official_resident_code' => sprintf('RS-%04d-%05d', $barangay->id, $residentSequence),
                'mobile_uuid' => (string) Str::uuid(),
                'philsys_card_no' => sprintf('PS-%04d-%02d-%02d-%02d', $barangay->id, $purokNumber, $householdIndex, $residentIndex),
                'last_name' => $familyName,
                'first_name' => $firstName,
                'middle_name' => $this->middleName($residentSequence),
                'suffix' => null,
                'birth_date' => $birthDate->toDateString(),
                'birth_place' => $this->birthPlaceFor($residentSequence),
                'sex' => $sex,
                'civil_status' => 'Married',
                'citizenship' => 'Filipino',
                'religion' => $religion,
                'contact_number' => sprintf('0917%07d', ($barangay->id * 100) + $residentSequence),
                'email_address' => sprintf('resident.%04d.%05d@healthlink.test', $barangay->id, $residentSequence),
                'relationship_to_head' => 'Head of Household',
                'resident_status' => Resident::STATUS_ACTIVE,
                'moved_in_at' => now()->subYears(10)->toDateString(),
                'moved_out_at' => null,
                'date_of_death' => null,
                'status_notes' => null,
                'is_active' => true,
            ];
        }

        if ($residentIndex === 2) {
            $sex = $femaleHead ? 'Male' : 'Female';
            $firstName = $this->personName($sex, $residentSequence + 3);
            $birthDate = now()->subYears(28 + (($barangayIndex + $householdIndex) % 15))->subMonths($householdIndex % 5);
            $religion = $this->religionFor($residentSequence + $householdIndex);

            return [
                'official_resident_code' => sprintf('RS-%04d-%05d', $barangay->id, $residentSequence),
                'mobile_uuid' => (string) Str::uuid(),
                'philsys_card_no' => sprintf('PS-%04d-%02d-%02d-%02d', $barangay->id, $purokNumber, $householdIndex, $residentIndex),
                'last_name' => $familyName,
                'first_name' => $firstName,
                'middle_name' => $this->middleName($residentSequence + 3),
                'suffix' => null,
                'birth_date' => $birthDate->toDateString(),
                'birth_place' => $this->birthPlaceFor($residentSequence + 3),
                'sex' => $sex,
                'civil_status' => 'Married',
                'citizenship' => 'Filipino',
                'religion' => $religion,
                'contact_number' => sprintf('0918%07d', ($barangay->id * 100) + $residentSequence),
                'email_address' => null,
                'relationship_to_head' => 'Spouse',
                'resident_status' => Resident::STATUS_ACTIVE,
                'moved_in_at' => now()->subYears(7)->toDateString(),
                'moved_out_at' => null,
                'date_of_death' => null,
                'status_notes' => null,
                'is_active' => true,
            ];
        }

        if ($residentIndex === 3) {
            $sex = (($householdIndex + $purokNumber) % 2) === 0 ? 'Male' : 'Female';
            $monthsByHousehold = [8, 19, 31, 46, 58];
            $birthDate = now()->subMonths($monthsByHousehold[$householdIndex - 1])->subDays(($barangayIndex + $purokNumber) % 21);

            return [
                'official_resident_code' => sprintf('RS-%04d-%05d', $barangay->id, $residentSequence),
                'mobile_uuid' => (string) Str::uuid(),
                'philsys_card_no' => sprintf('PS-%04d-%02d-%02d-%02d', $barangay->id, $purokNumber, $householdIndex, $residentIndex),
                'last_name' => $familyName,
                'first_name' => $this->personName($sex, $residentSequence + 7),
                'middle_name' => $this->middleName($residentSequence + 7),
                'suffix' => null,
                'birth_date' => $birthDate->toDateString(),
                'birth_place' => $this->birthPlaceFor($residentSequence + 7),
                'sex' => $sex,
                'civil_status' => 'Single',
                'citizenship' => 'Filipino',
                'religion' => $this->religionFor($residentSequence + 7),
                'contact_number' => null,
                'email_address' => null,
                'relationship_to_head' => 'Child',
                'resident_status' => Resident::STATUS_ACTIVE,
                'moved_in_at' => $birthDate->toDateString(),
                'moved_out_at' => null,
                'date_of_death' => null,
                'status_notes' => null,
                'is_active' => true,
            ];
        }

        $variant = ($householdIndex - 1) % 5;

        return match ($variant) {
            0 => [
                'official_resident_code' => sprintf('RS-%04d-%05d', $barangay->id, $residentSequence),
                'mobile_uuid' => (string) Str::uuid(),
                'philsys_card_no' => sprintf('PS-%04d-%02d-%02d-%02d', $barangay->id, $purokNumber, $householdIndex, $residentIndex),
                'last_name' => $familyName,
                'first_name' => $this->personName('Female', $residentSequence + 11),
                'middle_name' => $this->middleName($residentSequence + 11),
                'suffix' => null,
                'birth_date' => now()->subYears(67)->subMonths($barangayIndex % 9)->toDateString(),
                'birth_place' => $this->birthPlaceFor($residentSequence + 11),
                'sex' => 'Female',
                'civil_status' => 'Widowed',
                'citizenship' => 'Filipino',
                'religion' => $this->religionFor($residentSequence + 11),
                'contact_number' => null,
                'email_address' => null,
                'relationship_to_head' => 'Parent',
                'resident_status' => Resident::STATUS_ACTIVE,
                'moved_in_at' => now()->subYears(25)->toDateString(),
                'moved_out_at' => null,
                'date_of_death' => null,
                'status_notes' => null,
                'is_active' => true,
            ],
            1 => [
                'official_resident_code' => sprintf('RS-%04d-%05d', $barangay->id, $residentSequence),
                'mobile_uuid' => (string) Str::uuid(),
                'philsys_card_no' => sprintf('PS-%04d-%02d-%02d-%02d', $barangay->id, $purokNumber, $householdIndex, $residentIndex),
                'last_name' => $familyName,
                'first_name' => $this->personName('Male', $residentSequence + 11),
                'middle_name' => $this->middleName($residentSequence + 11),
                'suffix' => null,
                'birth_date' => now()->subYears(14)->subMonths($barangayIndex % 5)->toDateString(),
                'birth_place' => $this->birthPlaceFor($residentSequence + 11),
                'sex' => 'Male',
                'civil_status' => 'Single',
                'citizenship' => 'Filipino',
                'religion' => $this->religionFor($residentSequence + 11),
                'contact_number' => null,
                'email_address' => null,
                'relationship_to_head' => 'Child',
                'resident_status' => Resident::STATUS_ACTIVE,
                'moved_in_at' => now()->subYears(14)->toDateString(),
                'moved_out_at' => null,
                'date_of_death' => null,
                'status_notes' => null,
                'is_active' => true,
            ],
            2 => [
                'official_resident_code' => sprintf('RS-%04d-%05d', $barangay->id, $residentSequence),
                'mobile_uuid' => (string) Str::uuid(),
                'philsys_card_no' => sprintf('PS-%04d-%02d-%02d-%02d', $barangay->id, $purokNumber, $householdIndex, $residentIndex),
                'last_name' => $familyName,
                'first_name' => $this->personName('Male', $residentSequence + 11),
                'middle_name' => $this->middleName($residentSequence + 11),
                'suffix' => null,
                'birth_date' => now()->subYears(24)->subMonths($purokNumber % 4)->toDateString(),
                'birth_place' => $this->birthPlaceFor($residentSequence + 11),
                'sex' => 'Male',
                'civil_status' => 'Single',
                'citizenship' => 'Filipino',
                'religion' => $this->religionFor($residentSequence + 11),
                'contact_number' => sprintf('0919%07d', ($barangay->id * 100) + $residentSequence),
                'email_address' => null,
                'relationship_to_head' => 'Sibling',
                'resident_status' => Resident::STATUS_ACTIVE,
                'moved_in_at' => now()->subYears(3)->toDateString(),
                'moved_out_at' => null,
                'date_of_death' => null,
                'status_notes' => null,
                'is_active' => true,
            ],
            3 => [
                'official_resident_code' => sprintf('RS-%04d-%05d', $barangay->id, $residentSequence),
                'mobile_uuid' => (string) Str::uuid(),
                'philsys_card_no' => sprintf('PS-%04d-%02d-%02d-%02d', $barangay->id, $purokNumber, $householdIndex, $residentIndex),
                'last_name' => $familyName,
                'first_name' => $this->personName('Male', $residentSequence + 11),
                'middle_name' => $this->middleName($residentSequence + 11),
                'suffix' => null,
                'birth_date' => now()->subYears(72)->subMonths($barangayIndex % 7)->toDateString(),
                'birth_place' => $this->birthPlaceFor($residentSequence + 11),
                'sex' => 'Male',
                'civil_status' => 'Widowed',
                'citizenship' => 'Filipino',
                'religion' => $this->religionFor($residentSequence + 11),
                'contact_number' => null,
                'email_address' => null,
                'relationship_to_head' => 'Parent',
                'resident_status' => Resident::STATUS_ACTIVE,
                'moved_in_at' => now()->subYears(20)->toDateString(),
                'moved_out_at' => null,
                'date_of_death' => null,
                'status_notes' => null,
                'is_active' => true,
            ],
            default => [
                'official_resident_code' => sprintf('RS-%04d-%05d', $barangay->id, $residentSequence),
                'mobile_uuid' => (string) Str::uuid(),
                'philsys_card_no' => sprintf('PS-%04d-%02d-%02d-%02d', $barangay->id, $purokNumber, $householdIndex, $residentIndex),
                'last_name' => $familyName,
                'first_name' => $this->personName('Female', $residentSequence + 11),
                'middle_name' => $this->middleName($residentSequence + 11),
                'suffix' => null,
                'birth_date' => now()->subYears(9)->subMonths($householdIndex % 6)->toDateString(),
                'birth_place' => $this->birthPlaceFor($residentSequence + 11),
                'sex' => 'Female',
                'civil_status' => 'Single',
                'citizenship' => 'Filipino',
                'religion' => $this->religionFor($residentSequence + 11),
                'contact_number' => null,
                'email_address' => null,
                'relationship_to_head' => 'Child',
                'resident_status' => Resident::STATUS_ACTIVE,
                'moved_in_at' => now()->subYears(9)->toDateString(),
                'moved_out_at' => null,
                'date_of_death' => null,
                'status_notes' => null,
                'is_active' => true,
            ],
        };
    }

    private function residentSocioEconomicPayload(int $residentIndex, int $householdIndex): array
    {
        if ($residentIndex === 1) {
            return [
                'occupation' => self::ADULT_OCCUPATIONS[($householdIndex - 1) % count(self::ADULT_OCCUPATIONS)],
                'employment_status' => 'Employed',
                'highest_education_level' => 'High School',
                'education_status' => 'Graduate',
                'is_pwd' => false,
                'disability_type' => null,
                'is_ofw' => false,
                'is_solo_parent' => false,
                'is_osy' => false,
                'is_osc' => false,
                'is_ip' => false,
                'ethnicity' => null,
            ];
        }

        if ($residentIndex === 2) {
            return [
                'occupation' => $householdIndex % 2 === 0 ? 'Homemaker' : 'Market Vendor',
                'employment_status' => $householdIndex % 2 === 0 ? 'N/A' : 'Employed',
                'highest_education_level' => 'College',
                'education_status' => 'Graduate',
                'is_pwd' => false,
                'disability_type' => null,
                'is_ofw' => false,
                'is_solo_parent' => $householdIndex === 5,
                'is_osy' => false,
                'is_osc' => false,
                'is_ip' => false,
                'ethnicity' => null,
            ];
        }

        if ($residentIndex === 3) {
            return [
                'occupation' => 'None',
                'employment_status' => 'N/A',
                'highest_education_level' => 'None',
                'education_status' => 'N/A',
                'is_pwd' => false,
                'disability_type' => null,
                'is_ofw' => false,
                'is_solo_parent' => false,
                'is_osy' => false,
                'is_osc' => false,
                'is_ip' => false,
                'ethnicity' => null,
            ];
        }

        return [
            'occupation' => $householdIndex === 1 || $householdIndex === 4 ? 'Retired' : 'Student',
            'employment_status' => 'N/A',
            'highest_education_level' => $householdIndex === 1 || $householdIndex === 4 ? 'Elementary' : 'High School',
            'education_status' => $householdIndex === 1 || $householdIndex === 4 ? 'Graduate' : 'Undergraduate',
            'is_pwd' => $householdIndex === 1 || $householdIndex === 4,
            'disability_type' => $householdIndex === 1 || $householdIndex === 4 ? 'Mobility Impairment' : null,
            'is_ofw' => false,
            'is_solo_parent' => false,
            'is_osy' => false,
            'is_osc' => false,
            'is_ip' => false,
            'ethnicity' => null,
        ];
    }

    private function personName(string $sex, int $sequence): string
    {
        $pool = $sex === 'Female' ? self::FEMALE_NAMES : self::MALE_NAMES;

        return $pool[$sequence % count($pool)];
    }

    private function middleName(int $sequence): string
    {
        return self::MIDDLE_NAMES[$sequence % count(self::MIDDLE_NAMES)];
    }

    private function religionFor(int $sequence): string
    {
        return self::RELIGIONS[$sequence % count(self::RELIGIONS)];
    }

    private function birthPlaceFor(int $sequence): string
    {
        return self::BOHOL_BIRTH_PLACES[$sequence % count(self::BOHOL_BIRTH_PLACES)];
    }

    private function purokNameFor(int $purokNumber): string
    {
        return self::PUROK_NAMES[$purokNumber] ?? 'Purok '.$purokNumber;
    }

    private function householdAddress(Barangay $barangay, Purok $purok, int $householdIndex): string
    {
        $marker = self::HOUSEHOLD_LOCATION_MARKERS[($barangay->id + $purok->purok_number + $householdIndex) % count(self::HOUSEHOLD_LOCATION_MARKERS)];

        return "{$marker}, {$purok->purok_name}, {$barangay->name}, Tubigon, Bohol";
    }

    private function staffDisplayName(string $role, int $sequence): string
    {
        $sex = match ($role) {
            'secretary' => 'Female',
            'bns' => $sequence % 3 === 0 ? 'Male' : 'Female',
            default => $sequence % 2 === 0 ? 'Female' : 'Male',
        };

        return $this->personName($sex, $sequence).' '.self::LAST_NAMES[($sequence + 5) % count(self::LAST_NAMES)];
    }

    private function upsertUser(array $attributes, ?User $approvedBy = null): User
    {
        return User::query()->updateOrCreate(
            ['email' => $attributes['email']],
            [
                'name' => $attributes['name'],
                'password' => $this->defaultPasswordHash,
                'role' => $attributes['role'],
                'approval_status' => User::APPROVAL_APPROVED,
                'registered_via' => 'seed',
                'requested_role' => null,
                'assigned_barangay_id' => $attributes['assigned_barangay_id'],
                'assigned_purok_id' => $attributes['assigned_purok_id'],
                'requested_barangay_id' => null,
                'requested_purok_id' => null,
                'approval_notes' => 'Seeded Tubigon demo account',
                'approved_at' => now(),
                'approved_by' => $approvedBy?->id,
                'rejected_at' => null,
                'rejected_by' => null,
                'is_active' => true,
            ]
        );
    }
}
