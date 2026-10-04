# Existing registry capture (Lifecycle-v2 L1D)

This is explicit infrastructure tooling, not registration or a lifecycle transition.
No migration, request, model boot, seeder or deployment automatically runs it.

## Review and apply

1. Run `php artisan residents:lifecycle-preflight` and review the existing warnings.
2. Run `php artisan residents:lifecycle-bootstrap` (DRY RUN; writes nothing).
3. Review the inclusive `through_id`, counts and context warnings. Missing ownership
   records produce truthful partial context, not fabricated geography or exclusion.
4. Run `php artisan residents:lifecycle-bootstrap --apply --through-id=N` using the
   reviewed cutoff. `--apply` without a cutoff freezes the current maximum Resident
   ID, or reuses the retained cutoff from the first committed bootstrap capture.
5. Re-run preflight and compare Resident and code-sequence fingerprints/counts.

Default batches contain at most 250 Residents; `--batch-size=1..1000` changes this
bound. Each batch commits independently through `LifecycleEventRecorder`. Failed
batches roll back in full; committed earlier batches remain. The command prints an
exact resume command before writing. After interruption, rerun with the same
`--through-id=N`. If no batch committed, the printed/reviewed cutoff is the only
durable resume reference; do not recalculate it from a larger population.

Every new baseline stores its cutoff in event metadata. Once a batch commits,
omitting `--through-id` reuses the earliest retained bootstrap cutoff instead of
silently including subsequently created Residents. An explicit different cutoff
is an operator-selected change of capture set, not automatic discovery. Do not
use it to capture genuinely new registrations; later lifecycle phases own those.
ID ordering assumes ordinary monotonically increasing primary-key creation, not
concurrent manual imports that insert older IDs. Coordinate registry imports and
avoid hard deletion during bootstrap. Existing restrictive history FKs prevent
hard deletion after capture, including indirect deletion through parent geography.

## Meaning and safety

Each baseline uses `event_type = event_key = provenance = registry_capture`,
`effective_date = NULL`, the recorder's actual UTC `recorded_at`, and NULL actor
fields. Its meaning is **Existing Registry Record Captured**, never original
registration date/place. Source context is explicitly `observed_at_capture`, with
available Household/Purok/Barangay references and frozen names/numbers. Deleted
context records are included when still physically present and labeled accordingly.

Metadata contains schema version, cutoff, observed status/active/deleted flags,
legacy timestamps/status dates and missing/deleted-context reasons. It excludes
names, DOB, contact, PhilSys, clinical and socioeconomic data. Contradictory,
inactive, legacy relocated and soft-deleted Residents are included without being
normalized. First successful capture wins; retries never refresh its snapshots.
Incompatible or duplicate baselines are blockers requiring investigation, not repair.

Bootstrap writes only lifecycle events. Residents, their timestamps/versions/codes,
code sequences and archive metadata remain unchanged. Ordinary archive/restore
continues; hard deletion/purge now fails for captured Residents as intended by L1B.
No automatic `resident_registered`, population scopes or lifecycle UI is enabled.
