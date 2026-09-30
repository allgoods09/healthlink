# OPT+ Pilot Implementation

## Workflow and Safety Boundaries

The BNS uses OPT+ > Cycle History > Add New OPT+ Cycle > Eligible Child
Masterlist > Record Measurement. Titles are generated from year/round. Each
barangay has at most one January and one July round per year, enforced by a
database unique constraint as well as a friendly validation response.

`OptCycleRules` contains the current `pooc-pilot-v1` rules. A reference date must
belong to the selected January/July month and cannot be future-dated. Capture
uses the current verified/active registry, with eligibility evaluated on that
reference date: DOB <= reference < fifth birthday. The leap-day convention is
February 28 for a non-leap fifth birthday and needs field confirmation. Creating
a backdated cycle does NOT reconstruct the barangay's past population.

Cycle creation and all entries are committed atomically. Entries preserve
identity, DOB, sex, resident code, geography, location, household, caregiver and
IP information. Current-profile changes, birthdays, moves, registration and
deletion do not refresh the captured roster. No automatic roster synchronization
or general add/remove-roster workflow is provided. Caregiver/IP snapshot
corrections are explicitly selected, require a reason, and are audited.

## BNS Interface Simplification

Ordinary weighing is one form: Mother / Caregiver, Measurement Date, Weight,
Height / Length, Save Measurement. The caregiver field accepts either a typed
name or a selected scoped resident; the distinction is stored internally.
Saving returns to the child list without an extra confirmation dialog.

The combined transaction saves the current caregiver for future cycles without
changing any existing cycle caregiver value. On a child's first weighing only,
a missing cycle caregiver is initially recorded from the submitted name and
audited. Subsequent historical corrections require the separate Edit Historical
Information action and a reason. That action changes only the selected cycle,
not the current child profile or other cycles. Completed cycles remain locked.

The normal form does not ask for IP confirmation. New cycles reuse known IP
information from the nutrition/socioeconomic profile; missing information stays
unknown, and existing cycle records are not retroactively changed. Export
completeness is available under Export Records/More list options, not per-row
measurement status. The list shows only Measured or Unmeasured.

Actual measurement method cannot be inferred from age. The existing age-based
default is retained (now evaluated on measurement date), with a secondary
Measurement method and optional notes section to record lying down/standing
when different. Previously recorded methods and deliberate overrides are never
automatically changed. Reference calculations remain available under More
information rather than beside the primary weighing task. There are no new
schema migrations for this interface change.

Measurement values remain exclusively in `opt_measurements`; each entry has
at most one measurement, with corrections audited rather than duplicated.
Measured means valid date, weight, height/length and posture have been saved.
Demographic input readiness is separate and does not certify official-template
readiness. Reference assessments can fail without discarding valid raw inputs.
Completing a cycle is explicit, not triggered by 100% measurement coverage.
Unmeasured entries require confirmation and an explanation. Completed cycles
are read-only until reopened with a reason. Reference metadata stays immutable.

Caregivers may be a scoped registered resident or a confirmed display name.
No caregiver is inferred from the household head. The reusable profile retains
the confirmed display name until explicitly changed; it is not silently renamed
when a linked caregiver's resident record changes.

## Schema and Deployment

Additive migration: `database/migrations/2026_09_27_000001_add_opt_cycle_workflow.php`.

- New `opt_cycles`: fixed reference, lifecycle, capture time, actors, rules version and provenance.
- New `opt_cycle_entries`: historical roster snapshots, nullable live-resident link and stable resident key.
- New `child_nutrition_profiles`: reusable caregiver confirmation and nullable/confirmed IP membership.
- `opt_measurements`: nullable unique entry link and internal assessment source/version/error.
- Measurement resident/recorder foreign keys become nullable with SET NULL on hard deletion, retaining readings.
- `feeding_program_enrollments`: optional source measurement and copied baseline provenance.
- `child_nutrition_assessment_flags`: explicit resolution note.

Create and verify a full database backup before applying the migration. Apply
only this migration if other unreviewed migrations are pending. There is no
destructive `down()` implementation: rolling back historical nullable links
requires a reviewed backup restore, not dropping newly collected records.
The migration does not create historical cycles or alter existing measurements.

`php artisan opt:legacy-report` is a read-only inventory and works before/after
the migration. Local pre-migration inspection found 170 measurements across 34
`OPT+ 2026 Q3` campaigns, with no missing campaign links, child/campaign duplicate
readings or barangay mismatches. All January/July mappings are ambiguous. None
are converted. Even exact-name candidates require manual review; the command
never converts records, invents unmeasured children or reconstructs demographics.

## Compatibility and Decoupling

Legacy history/read/export routes remain available. Legacy measurement writes
return HTTP 410 by default, before old request validation. Old creation links
are removed or redirected to cycles. OPT campaign creation/editing is retired
from the normal BNS UI, while unrelated nutrition campaign types are retained.
`OPT_LEGACY_WRITES_ENABLED=true` is a temporary maintenance-only compatibility
switch, not a second user workflow; it should remain false in ordinary use.

Clinical `latestOptMeasurement()` consumers still use the same history table.
Legacy measurement IDs and campaign relationships are retained. Feeding's
separate age/eligibility policies are unchanged. Enrollment needs no OPT result;
manual/null baselines remain valid. Copying latest OPT into a baseline requires
an explicit checkbox, cannot be mixed with manual baseline values, and stores
source/date/copied values. Later measurement corrections do not mutate baselines.

Nutrition watchlist/flags are internal references, not mandatory feeding steps.
Recording OPT no longer closes flags. Resolution is explicit and affects only
the selected scoped flag; an optional linked reading must belong to that child.

BNS/Admin cycle coverage uses captured rosters. Municipality-wide summary sums
the latest cycle per barangay, not all rounds' overlapping rosters. Legacy
readings remain reference statistics rather than invented historical coverage.

## Canonical Dataset and Future Official Template

`OptCycleDataset::VERSION = opt-inputs-v1` separates stable inputs from workbook
implementation. Table and exports share one authorized query for search, purok,
measurement status, readiness and explicit sorting. Only pagination is removed.
CSV/XLSX columns are explicit; PDF selects eight readable masterlist columns on
A4 landscape. Shared export infrastructure adds report metadata and an XLSX
Export Information sheet; CSV remains a clean header and data rows.

Current exports are named **Internal OPT+ Dataset**, never official e-OPT output.
The input contract includes cycle/reference, component/full child names, code,
DOB, sex, reference age (internal only), barangay/purok/address/household,
municipality/province/region/PSGC, confirmed caregiver/relationship, IP membership
and confirmation, ethnicity, measurement date, weight, height/length and posture.
Internal model IDs, security fields and calculated nutrition classifications are
not exported. Unmeasured/incomplete rows remain included unless filtered out.

The actual Community-Level e-OPT Plus `.xlsx` and its exact version are still
required. No template population, formula reconstruction, official output or
500-child splitting is implemented. Future integration should register a
versioned mapping from this contract into a private immutable original template,
validate required inputs/capacity, copy the template, write ONLY approved input
cells, and preserve formulas, formatting, validations and summary sheets.
Verify preservation and actual Excel recalculation against the original before
release. Official workbook calculated outputs remain authoritative for that
workbook. A future validated field can extend the profile/snapshot contract
additively rather than rebuilding the cycle database.

Discuss the 500-child limit and approved purok/section/part grouping with BNS/RHU
before implementing splitting. Do not truncate results or silently invent groups.

## Implementation Checklist

- [x] January/July uniqueness and fixed-reference validation.
- [x] Atomic reference-date eligibility and historical roster capture.
- [x] Persistent caregiver/IP profile and explicit audited snapshot correction.
- [x] Cycle history/create/masterlist/measurement screens and BNS navigation.
- [x] Measured/readiness separation, completion locking and audited reopening.
- [x] Internal dataset/history exports with shared search/filter/sort queries.
- [x] Growth reference failure preservation and clinical latest-reading compatibility.
- [x] Feeding independence, explicit baseline copy and provenance.
- [x] Explicit scoped nutrition-flag resolution, no measurement-triggered closure.
- [x] BNS/Admin dashboard, nutrition oversight and municipal nutrition report updates.
- [x] Conservative legacy inventory and default retirement of old OPT writes.
- [x] Demo nutrition seeding uses cycles without overwriting existing baselines.
- [x] Simple single-save caregiver/measurement form, separate historical corrections and secondary export checks.
- [ ] Official workbook mapping, formula-preservation tests and approved splitting (awaiting template/policy validation).

## Changed-File Map

New files:

- `app/Console/Commands/ReportLegacyOpt.php`
- `app/Http/Controllers/Bns/OptCycleController.php`
- `app/Http/Middleware/EnsureLegacyOptWritesEnabled.php`
- `app/Http/Requests/Bns/{StoreOptCycleRequest,SaveOptCycleMeasurementRequest,ConfirmOptCaregiverRequest}.php`
- `app/Models/{OptCycle,OptCycleEntry,ChildNutritionProfile}.php`
- `app/Support/Nutrition/{OptCycleRules,OptCycleWorkflow,OptCycleDataset,OptCycleReporting,LegacyOptInventory}.php`
- `config/opt.php` and the additive migration listed above.
- `resources/views/bns/opt-cycles/{index,create,show,entry,errors}.blade.php`
- `resources/views/bns/opt-cycles/historical-information.blade.php`
- `resources/views/components/opt-caregiver-field.blade.php` and `resources/js/opt-entry.js`.
- `tests/js/opt-entry.test.mjs`.
- `tests/Feature/Bns/OptCycleWorkflowTest.php` and `tests/Unit/OptCycleEligibilityTest.php`.
- This implementation record.

Modified files:

- `app/Http/Controllers/Bns/{CampaignPeriodController,DashboardController,FeedingProgramController,OptMeasurementController,TargetClientListController}.php`
- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/Admin/Oversight/NutritionOversightController.php`
- `app/Http/Controllers/Admin/Reports/MunicipalReportController.php`
- `app/Http/Requests/Bns/{StoreCampaignPeriodRequest,UpdateCampaignPeriodRequest,StoreFeedingProgramEnrollmentRequest}.php`
- `app/Models/{Resident,OptMeasurement,FeedingProgramEnrollment,ChildNutritionAssessmentFlag}.php`
- `app/Support/Nutrition/GrowthAssessmentService.php`
- `database/seeders/MockOperationalDataSeeder.php` and `routes/web.php`.
- `resources/views/layouts/portal.blade.php`
- `resources/js/app.js`: registers the OPT-only field/form helpers without changing other record selectors.
- `resources/views/admin/dashboard.blade.php` and `resources/views/admin/oversight/nutrition.blade.php`.
- `resources/views/bns/dashboard.blade.php`, campaign-periods/index, feeding-programs/index/show, opt-measurements/index/show and watchlist/index.
- `tests/Feature/Bns/BnsNutritionWorkflowTest.php`: retained maintenance paths, feeding independence and no implicit flag closure.
- `tests/Feature/Admin/AdminWorkflowRegressionTest.php`: stale audit title expectation aligned with existing shared user-export title; user-export behavior unchanged.
- `documentation.txt` and `docs/export-checklist.md`.

## Field Validation Still Needed

Confirm reference-date rules (including delayed rounds), the leap-day convention,
measurement-date windows and plausibility limits, required posture/input fields,
caregiver/IP confirmation handling, permitted roster corrections, missing-data
and completion expectations, and official-template version/mapping/capacity.
Current raw limits (0.5-60 kg, 30-140 cm) are internal plausibility checks, not a
claim about official e-OPT requirements. Confirm whether labels/reference
assessments are useful to BNS and verify equivalence before treating them as
anything other than internal reference information.

## Verification Record

- Baseline focused suite: 29 tests, 299 assertions passed.
- OPT cycle/unit suite after interface simplification: 39 tests, 251 assertions passed.
- Full PHP suite after interface simplification: 150 tests, 963 assertions passed.
- `node --test tests/js/opt-entry.test.mjs`: 3 tests passed (typed/selected caregivers, prefilling and measurement-method defaults).
- `npm run build`: passed; production frontend assets generated.
- Touched PHP files formatted; `git diff --check` clean.
- Local additive migration applied after a checksum-verified full backup:
  `storage/app/backups/backup_full_pre_opt_cycles_2026-09-27_074848.sql`.
- All 170 legacy readings have the same SHA-256 fingerprint across every
  original database column before/after migration. Zero legacy readings were
  linked to invented cycles; no live cycles or demographic confirmations were seeded.
- Database uniqueness is tested at the constraint level as the concurrency
  safeguard; this is not a multi-process load/race benchmark.
- Feature tests render the BNS/Admin screens and exercise exports. A browser
  surface was unavailable, so interactive visual QA remains a manual follow-up.
- Official-template tests remain deferred until the real workbook is supplied.

The SQL backup contains sensitive database data and must stay in private storage.
Checksum verification is not a performed full restore rehearsal. No application
database reset or demo reseeding was used during deployment.
