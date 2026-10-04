# Resident code allocation (Lifecycle-v2 L1C)

Codes retain `RS-%04d-%05d` formatting. The prefix is the issuance namespace,
never current location or an authorization scope. Existing codes are not rewritten.

## Deployment

1. Stop/drain all registry-writing requests, workers, seeders and long-lived processes.
2. Apply the sequence-table migration.
3. Run `php artisan residents:initialize-code-sequences` against the intended database.
4. Run `php artisan residents:lifecycle-preflight` and resolve sequence blockers.
5. Reload all application/worker processes onto the sequence allocator, then resume writes.

Old count-based and new sequence-based application versions must never write
concurrently. Do not downgrade to count-based allocation after new issuance.
Rollback refuses to drop any sequence rows, including zero-valued rows. Retain
technical sequence state when disabling the feature.

## Persistence contract

`Resident::save()` allocates/reserves before the initial insert, within a Laravel
transaction/savepoint. Updates never allocate and reject code changes. Explicit
non-RS codes remain unchanged; positive numeric RS namespace/suffix codes reserve
their namespace, including shorter explicit suffix padding. Invalid RS-like new
codes are rejected, not replaced. Historical malformed codes are reported, not
normalized. Raw SQL/bulk inserts bypass this model boundary: controlled imports
must reserve their codes or rerun initialization before registry writes resume.

Initialization considers all physical resident rows and resident archive snapshots,
including purged snapshot references still retained in the archive table. A sequence
namespace has no barangay FK: deleting a barangay cannot erase issuance history.
First-ever use also observes existing issuance under the namespace row lock.

Numbers in failed/rolled-back transactions were not committed issuances and may
be retried. No committed identity is reused after deletion or movement. The allocator
does not commit a caller's outer transaction. A caller rolling back a successful
save must discard/reload the model, as with ordinary Eloquent transaction semantics.
No lifecycle events or version increments are emitted.
