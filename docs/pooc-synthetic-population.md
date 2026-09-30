# Pooc synthetic population, version 1

This is an entirely fictional population for capstone demonstrations, workflow testing and performance testing. It is not a resident masterlist or a demographic estimate for Pooc Oriental. No individual source records, voter lists, social-media profiles or household lists were used. Coincidental name matches do not identify actual people.

## Commands and isolation

The verified preview is in the **separate local MySQL database `healthlink_pooc_population_v1`**. The application `.env` and its existing `healthlink` database were not switched, reset or purged. Consequently the old application database still contains its older municipality-wide demos; the population-only assertions apply to the isolated preview.

Run the read-only audit of that preview:

```bash
DB_DATABASE=healthlink_pooc_population_v1 php artisan pooc:population-audit
```

To inspect the population in HealthLink without changing `.env`, start a separate development server and sign in with a pilot demo account. Use a separate browser profile/private window to avoid sharing authentication cookies with another running HealthLink instance:

```bash
APP_URL=http://127.0.0.1:8001 DB_DATABASE=healthlink_pooc_population_v1 php artisan serve --host=127.0.0.1 --port=8001
```

Database overrides require uncached configuration; if configuration has been cached, clear it deliberately before using these development commands. The verified environment was not configuration-cached.

For another clean local development database, create an empty database first, then use its name for each command. These are the exact commands used for the preview:

```bash
DB_DATABASE=healthlink_pooc_population_v1 php artisan migrate --force
DB_DATABASE=healthlink_pooc_population_v1 php artisan db:seed --class=PoocPilotConfigurationSeeder
DB_DATABASE=healthlink_pooc_population_v1 php artisan pooc:population
```

The existing preview is already seeded, so the final command now **refuses** to run. There is intentionally **no reset/delete command**. To rebuild, use another empty development database and the same versioned configuration. No records from an existing database are silently removed. Never run `migrate:fresh` against the application's current development database to obtain this population.

The equivalent population-only seeder is `php artisan db:seed --class=PoocSyntheticPopulationSeeder`. Both paths use the same transaction, validation and safeguards. They require local/testing environment, approved active Pooc puroks, empty population tables, no non-pilot puroks/frontline accounts and no operational records. They never configure missing puroks themselves.

`PoocPilotConfigurationSeeder` is a separate, explicit clean-database fixture. It restores all 34 existing Tubigon barangay definitions, the exact seven Pooc purok names already present in HealthLink, and six demo accounts. It does not assert that these configured purok names are field-verified geographic names. It preserves existing metadata and refuses conflicting account assignments rather than reassigning them. It creates only Pooc Secretary/BNS/BHW accounts; Admin/PHN/MHO remain municipality-wide. Demo accounts use `password`, for local demonstrations only. Blank official-role placeholders remain blank (340 in the clean preview).

`DatabaseSeeder` now registers barangays only. It no longer implicitly calls the broad operational mock or demo-account seeders. The legacy seeders remain available for their explicit existing tests/use cases; **do not invoke `MockOperationalDataSeeder` in this population phase**.

## Architecture and Assumptions

- `config/pooc_population.php`: version 1, PRNG seed 27092026, fixed generation/audit date 2026-09-27, target 3,000, household-size/family-type/age weights, occupations and caregiver assumptions. Application ages continue to advance naturally from DOB; audit reports use this fixed reference date for reproducibility.
- `database/data/tubigon-surnames.php`: all 201 user-supplied surnames with incidence weights (total weight 19,826). These are **Tubigon aggregate weights, not verified Pooc Oriental frequencies**. Probabilities are incidence divided by total incidence. No surnames were supplemented. Source characters and spelling are preserved.
- `database/data/pooc-given-names.php`: 191 distinct male and 216 distinct female vocabulary entries, including single and compound Filipino naming styles. Additional combinations are optional, not a uniqueness counter. A normalized complete-name registry enforces uniqueness before persistence.
- `app/Support/Population/PoocNames.php`: deterministic weighted sampling and normalized name uniqueness.
- `app/Support/Population/PoocFamilyGenerator.php`: varied households, married/unmarried couples, single parents, grandparents, extended/blended families, adult male/female heads, inherited family and middle surnames, appropriate suffixes, DOB-derived ages and age-consistent socioeconomic values.
- `app/Support/Population/PoocPopulation.php`: scoped transactional model creation, existing official-code conventions, deterministic UUIDs, batched socioeconomic/current-caregiver insertion, atomic post-generation validation and refusal of dirty databases/reruns. A pilot barangay row lock serializes competing seed attempts.
- `app/Support/Population/PoocPopulationAudit.php`: read-only report of demographics, household sizes, purok allocation, caregivers, surname counts, relationship consistency, non-pilot leakage and every non-foundation table in the selected database.
- `app/Console/Commands/SeedPoocPopulation.php` and `AuditPoocPopulation.php`: generation and audit commands.
- `database/seeders/PoocSyntheticPopulationSeeder.php` and `PoocPilotConfigurationSeeder.php`: explicit population and clean pilot configuration entry points.
- `database/seeders/BarangaySeeder.php` and `DatabaseSeeder.php`: preserve existing geographic metadata on reruns; disable automatic population/operational demos.
- `tests/Feature/Database/PoocSyntheticPopulationTest.php`: focused population and safeguard tests.

No schema migrations, production-facing demo tags, UI changes or business-rule changes were needed. Household numbering was already uniquely constrained by `(purok_id, household_no)`, so each purok starts again at household 1. Head and current caregiver relations use the existing schema. Parentage beyond head/caregiver references is coherent in the generator but is not invented as a new database relationship.

Caregivers are assigned deliberately to the generated mother/father/grandparent, not inferred from the head field. Under-fives have current nutrition profiles with either an actual adult household caregiver or an unconfirmed blank. The configured missing-caregiver probability is 8%; finite sampling yields the actual result below. No historical OPT entries or snapshots are created.

Sensitive socioeconomic booleans remain their existing non-null default `false`; these **do not assert measured local prevalence**. Nullable current child-profile IP values remain unknown. Optional contact details, PhilSys identifiers, religion, ethnicity, and housing characteristics are not fabricated. Required birthplace/address text is explicitly synthetic. Occupation/education/civil-status conventions match the existing schema.

The simulation intentionally contains many family households and children: 47.9% are under 18. This is a configurable, child-rich testing population, not an official barangay age structure. Purok allocation is equal-probability sampling, not verified geographic density. Household-size, age, family-type, occupation, educational and caregiver-confirmation assumptions should be reviewed at the next field visit. Changes to accepted generation rules/vocabulary/weights should increment the version and update its deterministic regression fingerprint.

## Verified Preview Results

Generation service runtime: **5.521 seconds** (including its initial audit, measured on this development machine).

| Purok (existing configuration) | Residents | Households |
| --- | ---: | ---: |
| 1 - Purok Centro | 399 | 97 |
| 2 - Purok Baybay | 434 | 100 |
| 3 - Purok Ilaya | 487 | 115 |
| 4 - Purok Luyo | 455 | 112 |
| 5 - Purok Riverside | 431 | 102 |
| 6 - Purok Crossing | 476 | 114 |
| 7 - Purok Proper | 318 | 81 |
| Total | 3,000 | 721 |

- Male: 1,430; female: 1,570.
- Ages 0-4: 398; 5-17: 1,040; 18-59: 1,280; 60+: 282.
- Infants: 74. Children aged 0-59 months at the reference date: 398.
- Real OPT eligibility query at 2026-07-01: 413 eligible children, with no cycle or measurements created. Eligibility is reference-date dependent.
- Under-five registered caregiver links: 354; intentionally unconfirmed: 44.
- Average household size: 4.161; minimum: 1; maximum: 10. The final household has a size supported by the same configured distribution; total is exactly 3,000.
- Household size/count: 1/46, 2/84, 3/121, 4/184, 5/142, 6/72, 7/44, 8/16, 9/11, 10/1.
- Youngest: DOB 2026-09-26 (age 0); oldest: DOB 1931-11-27 (age 94).
- Top 20 surnames: Jubahib 67; Cosare 53; Batausa 47; Boligao 41; Alampayan 41; Asombrado 40; Bagolor 40; Astillo 40; Silagan 38; Calunia 37; Bustalinio 37; Salutan 37; Abarquez 35; Lamanilao 35; Orfano 35; Mascariñas 33; Lumictin 33; Mula 30; Torrejas 28; Lanoy 28. Family inheritance means resident counts are clustered, not literal source incidence counts or independent per-person draws.

Every invalid-condition count is **zero**: duplicate normalized names, missing households, invalid/future DOB, impossible minor attributes, invalid household relationships, invalid scope relationships, duplicate household numbers within a purok, invalid caregiver references, non-pilot residents/households/puroks/scoped users, and operational records.

All 34 barangays remain present. All 33 non-pilot barangays have zero synthetic puroks, scoped users, households, residents, profiles and operational records. All 35 non-foundation tables in this schema are empty, including OPT, feeding, clinical, campaigns, notifications, audit and sync tables.

## Verification

```bash
php artisan test --compact --filter=PoocSyntheticPopulationTest
php artisan test --compact
```

Focused coverage includes deterministic version-1 fingerprint, exactly 3,000 residents, normalized name uniqueness, supplied surname-only sampling, family surname inheritance, DOB-derived ages, infants/children/adults/seniors, OPT-eligible children, current caregiver validity, complete municipality preservation, pilot-only configuration, empty registration/municipal/configuration/report screens, household-number composite uniqueness, safe configuration reruns, population rerun refusal, dirty-database refusal, production guard, atomic rollback, and deliberate audit failures.

Focused result: **12 tests passed, 10,034 assertions**. Full PHP regression result: **162 tests passed, 10,997 assertions** (59.609 seconds). PHP formatting and `git diff --check` passed. No frontend/UI files were changed in this phase.
