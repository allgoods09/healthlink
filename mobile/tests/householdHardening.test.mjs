import assert from 'node:assert/strict';
import { test } from 'node:test';
import { storageHarness } from './storageHarness.mjs';
import { appContextHarness } from './appContextHarness.mjs';
import { withHouseholdContract } from './householdFixture.mjs';

const uuid = n => `20000000-0000-4000-8000-${String(n).padStart(12, '0')}`;
const home = (id = 1, purok = 1) => ({ id, mobile_uuid: uuid(id), purok_id: purok, household_no: `00${id}-A`,
  household_address: 'Synthetic address', is_active: false, is_social_aid_beneficiary: true,
  current_member_count: 0, is_vacant: true, current_head_name: null });
const download = () => withHouseholdContract({ resident_contract_version: 2, user: { id: 1 },
  assignment: { barangay: { id: 1 }, purok: { id: 1 } }, server_time: '2026-10-07',
  households: [home(), { ...home(2, 2), current_head_name: 'Hidden person' }], residents: [],
  field_visits: [{ id: 1, mobile_uuid: uuid(11), household_id: 1, household_mobile_uuid: uuid(1),
    visited_at: '2026-10-06', notes: 'Historical visit', photos: [] },
    { id: 2, mobile_uuid: uuid(12), household_id: 2, household_mobile_uuid: uuid(2), visited_at: '2026-10-06', notes: 'Hidden visit', photos: [] }], risk_assessments: [] });
const request = () => ({ purok_id: 1, household_no: 'NEW-A', household_address: 'New home',
  is_active: true, is_social_aid_beneficiary: false });
const empty = () => ({ households: [], residents: [], field_visits: [], risk_assessments: [] });
async function fixture(t) {
  const h = await storageHarness(); t.after(h.close); await h.storage.prepareDatasetForUser(1);
  await h.storage.replaceBootstrapData(download()); return h;
}

test('operational/request/lookup APIs enforce separate scopes and lookup hides identities, social aid and coverage', async t => {
  const { storage } = await fixture(t);
  assert.deepEqual((await storage.getHouseholds()).map(h => h.server_id), [1]);
  const metadata = (await storage.getHouseholdLookup())[0];
  assert.equal(metadata.server_id, 2); assert.equal(metadata.member_coverage, 'undisclosed');
  for (const field of ['current_head_name', 'is_social_aid_beneficiary', 'base_snapshot', 'current_member_count', 'resident_count']) {
    assert.equal(Object.hasOwn(metadata, field), false, field);
  }
  assert.equal(await storage.getHouseholdByLocalId(metadata.local_id), null);
  assert.equal((await storage.getHouseholdLookupByLocalId(metadata.local_id)).server_id, 2);
  assert.deepEqual(await storage.getResidentsForHousehold(metadata), []);
  const id = await storage.saveHousehold(request(), 1);
  assert.equal(await storage.getHouseholdByLocalId(id), null);
  const own = await storage.getHouseholdRequestByLocalId(id);
  assert.equal(own.access_mode, 'request'); assert.equal(own.submitted_by_user_id, 1);
  assert.equal((await storage.getVisitHouseholdOptions()).length, 1);
  assert.equal((await storage.getVisits()).length, 1);
});

test('other-purok and stale direct IDs cannot mutate/relabel or silently create', async t => {
  const { storage, db } = await fixture(t);
  const foreign = (await storage.getHouseholdLookup())[0];
  const before = await db.getAllAsync('SELECT * FROM households');
  await assert.rejects(storage.saveHousehold({ ...foreign, purok_id: 1, is_social_aid_beneficiary: false }, 1), { name: 'AssignmentChangedError' });
  await assert.rejects(storage.saveHousehold({ ...request(), local_id: 9999 }, 1), /reopen the form/);
  await assert.rejects(storage.saveHousehold({ ...request(), server_id: 9999 }, 1), { name: 'AssignmentChangedError' });
  assert.deepEqual(await db.getAllAsync('SELECT * FROM households'), before);
  assert.equal(await storage.getHouseholdForCorrection(foreign.local_id), null);
});

for (const [label, state] of [['old contract', ['household_contract_version', '']], ['bad contract', ['household_contract_version', '99']],
  ['wrong signature', ['verified_assignment_signature', '1:1:999']], ['unknown assignment', ['dataset_assignment', '']],
  ['workspace quarantine', ['resident_workspace_blocked', 'assignment']]]) {
  test(`${label} closes Household/Visit reads and saves without destroying retained rows/photos`, async t => {
    const { storage, db } = await fixture(t);
    const before = await db.getAllAsync('SELECT * FROM households');
    await storage.setAppState(...state);
    for (const reader of ['getHouseholds', 'getHouseholdRequests', 'getHouseholdLookup', 'getVisits', 'getVisitHouseholdOptions']) assert.deepEqual(await storage[reader](), []);
    assert.equal(await storage.getHouseholdByLocalId(1), null);
    assert.equal(await storage.getVisitByLocalId(1), null);
    await assert.rejects(storage.saveHousehold(request(), 1), { name: 'AssignmentChangedError' });
    assert.deepEqual(await db.getAllAsync('SELECT * FROM households'), before);
  });
}

test('verified reassignment quarantines pending Household and Visit/photo work and same-assignment offline remains usable', async t => {
  const { storage, db } = await fixture(t);
  await storage.saveHousehold(request(), 1);
  await storage.saveVisit({ household_server_id: 1, visited_at: '2026-10-07', photos: [{ uri: 'file:///pending.jpg', base64: 'cGhvdG8=' }] }, 1);
  await storage.verifyDatasetAssignment(1, 1, 1);
  assert.equal((await storage.getHouseholds()).length, 1);
  const before = await db.getAllAsync('SELECT * FROM field_visits');
  await storage.verifyDatasetAssignment(1, 1, 2);
  assert.deepEqual(await storage.getVisits(), []); assert.deepEqual(await storage.getHouseholdRequests(), []);
  await assert.rejects(storage.getPendingSyncPayload(1), { name: 'AssignmentChangedError' });
  await assert.rejects(storage.saveHousehold(request(), 1), { name: 'AssignmentChangedError' });
  assert.deepEqual(await db.getAllAsync('SELECT * FROM field_visits'), before);
});

test('downloaded baseline is stable, sends only changed fields and preserves false and household-number formatting', async t => {
  const { storage } = await fixture(t);
  const home = (await storage.getHouseholds())[0];
  await storage.saveHousehold({ ...home, is_social_aid_beneficiary: false }, 1);
  let row = (await storage.getHouseholds())[0];
  assert.deepEqual(row.base_snapshot, home.base_snapshot);
  let { payload } = await storage.getPendingSyncPayload(1);
  assert.deepEqual(payload.households[0].proposed_changes, { is_social_aid_beneficiary: false });
  assert.equal(Object.hasOwn(payload.households[0], 'is_active'), false);
  assert.equal(Object.hasOwn(payload.households[0], 'household_no'), false);
  await storage.saveHousehold({ ...row, household_address: 'Changed', household_no: '0002-B' }, 1);
  ({ payload } = await storage.getPendingSyncPayload(1));
  assert.equal(payload.households[0].base_snapshot.household_no, '001-A');
  assert.equal(payload.households[0].proposed_changes.household_no, '0002-B');
  assert.equal(payload.households[0].base_snapshot.household_address, 'Synthetic address');
  await assert.rejects(storage.saveHousehold({ ...row, household_address: null }, 1), /address/);
});

test('unchanged official Household save creates no pending revision or correction', async t => {
  const { storage } = await fixture(t);
  const home = (await storage.getHouseholds())[0];
  await storage.saveHousehold(home, 1);
  assert.equal((await storage.getHouseholds())[0].local_revision, home.local_revision);
  assert.deepEqual((await storage.getPendingSyncPayload(1)).payload.households, []);
});

test('unavailable official Household correction keeps its genuine baseline and remains usable after restart', async t => {
  const { storage } = await fixture(t);
  const home = (await storage.getHouseholds())[0];
  await storage.saveHousehold({ ...home, household_address: 'Confirmed address correction' }, 1);
  await storage.initializeStorage();
  const saved = await storage.getHouseholdForCorrection(home.local_id);
  assert.ok(saved);
  assert.equal(saved.is_active, false);
  assert.equal(saved.protection_reason, null);
  assert.deepEqual(saved.base_snapshot, home.base_snapshot);
  assert.deepEqual((await storage.getPendingSyncPayload(1)).payload.households[0].proposed_changes,
    { household_address: 'Confirmed address correction' });
});

test('retained out-of-purok Visit is preserved but excluded from upload and acknowledgment', async t => {
  const { storage, db } = await fixture(t);
  await db.runAsync("UPDATE field_visits SET sync_status = 'pending_update' WHERE server_id = 2");
  const before = await db.getFirstAsync('SELECT * FROM field_visits WHERE server_id = 2');
  const { payload, snapshot } = await storage.getPendingSyncPayload(1);
  assert.deepEqual(payload.field_visits, []);
  assert.deepEqual(snapshot.rows.field_visits, []);
  assert.deepEqual(await db.getFirstAsync('SELECT * FROM field_visits WHERE server_id = 2'), before);
});

test('new availability is active only and official availability cannot become a correction', async t => {
  const { storage, db } = await fixture(t);
  await assert.rejects(storage.saveHousehold({ ...request(), is_active: false }, 1), /Secretary/);
  const home = (await storage.getHouseholds())[0];
  await assert.rejects(storage.saveHousehold({ ...home, is_active: true }, 1), /Secretary/);
  assert.equal(home.current_member_count, 0); assert.equal(home.is_vacant, true); assert.equal(home.is_active, false);
  await storage.saveVisit({ household_server_id: 1, visited_at: '2026-10-07', photos: [] }, 1);
  assert.equal((await db.getAllAsync("SELECT * FROM field_visits WHERE sync_status != 'synced'")).length, 1);
});

for (const status of ['submitted', 'rejected']) {
  test(`${status} new request is immutable and reason is retained`, async t => {
    const { storage, db } = await fixture(t);
    const id = await storage.saveHousehold(request(), 1);
    const row = await storage.getHouseholdRequestByLocalId(id);
    const { snapshot } = await storage.getPendingSyncPayload(1);
    await storage.applyResolvedRecords({ ...empty(), households: [{ id: null, mobile_uuid: row.mobile_uuid,
      verification_status: status, verification_notes: 'Secretary note' }] }, snapshot);
    const before = await db.getFirstAsync('SELECT * FROM households WHERE local_id = ?', [id]);
    await assert.rejects(storage.saveHousehold({ ...row, household_address: 'Unsafe resubmit' }, 1), /read-only/);
    assert.equal(await storage.getHouseholdForCorrection(id), null);
    assert.equal((await storage.getHouseholdRequestByLocalId(id)).verification_notes, 'Secretary note');
    assert.deepEqual(await db.getFirstAsync('SELECT * FROM households WHERE local_id = ?', [id]), before);
  });
}

test('legacy baseline/availability proposal survives additive migration and is not fabricated or acknowledged', async t => {
  const { storage, db } = await fixture(t);
  await db.runAsync("UPDATE households SET sync_status = 'pending_update', base_snapshot_json = NULL, is_active = 0, household_address = 'Legacy proposal' WHERE server_id = 1");
  const before = await db.getFirstAsync('SELECT * FROM households WHERE server_id = 1');
  await storage.initializeStorage(); await storage.initializeStorage();
  const after = await db.getFirstAsync('SELECT * FROM households WHERE server_id = 1');
  for (const field of ['local_id', 'local_revision', 'server_id', 'mobile_uuid', 'sync_status', 'household_address', 'is_active']) assert.equal(after[field], before[field]);
  assert.equal(after.base_snapshot_json, null); assert.ok(after.protection_reason);
  const { payload } = await storage.getPendingSyncPayload(1);
  assert.equal(payload.households[0].household_address, 'Legacy proposal');
  assert.equal(payload.households[0].is_active, false);
  assert.equal(payload.households[0].base_snapshot, undefined);
  await assert.rejects(storage.saveHousehold({ ...(await storage.getHouseholds())[0], household_address: 'New' }, 1), /read-only/);
});

test('new Visits require official scope and reject conflicting/missing IDs; local join is single-identity', async t => {
  const { storage, db } = await fixture(t);
  const id = await storage.saveHousehold(request(), 1); const own = await storage.getHouseholdRequestByLocalId(id);
  for (const ref of [{ household_server_id: 2 }, { household_mobile_uuid: own.mobile_uuid },
    { household_server_id: 1, household_mobile_uuid: uuid(2) }, { household_server_id: 9999, household_mobile_uuid: uuid(1) },
    { household_mobile_uuid: null }, { household_mobile_uuid: '' }]) {
    await assert.rejects(storage.saveVisit({ ...ref, visited_at: '2026-10-07', photos: [] }, 1), /verified household/);
  }
  await db.runAsync('UPDATE field_visits SET household_mobile_uuid = ? WHERE server_id = 1', [uuid(2)]);
  const visits = await storage.getVisits(); assert.equal(visits.length, 1); assert.equal(visits[0].household_no, '001-A');
  assert.equal(await storage.getVisitByLocalId(2), null);
});

async function pendingParent(storage, db) {
  const id = await storage.saveHousehold(request(), 1); const parent = await storage.getHouseholdRequestByLocalId(id);
  await db.runAsync(`INSERT INTO field_visits (mobile_uuid, household_mobile_uuid, visited_at, notes, photos_json, sync_status)
    VALUES (?, ?, '2026-10-07', 'Legacy queued visit', ?, 'pending_create')`,
    [uuid(21), parent.mobile_uuid, JSON.stringify([{ uri: 'file:///legacy.jpg', base64: 'cGhvdG8=' }])]);
  return parent;
}

for (const status of ['approved', 'rejected']) {
  test(`narrow ${status} parent outcome preserves pending Visit/photos without an acknowledgment`, async t => {
    const { storage, db } = await fixture(t); const parent = await pendingParent(storage, db);
    const before = await db.getFirstAsync('SELECT * FROM field_visits WHERE mobile_uuid = ?', [uuid(21)]);
    const { snapshot } = await storage.getPendingSyncPayload(1);
    await storage.applyResolvedRecords({ ...empty(), households: [{ mobile_uuid: parent.mobile_uuid, id: null, verification_status: 'submitted' }] }, snapshot);
    assert.equal(await storage.hasPendingHouseholdDependencies(), true);
    assert.equal((await storage.getVisits()).some(v => v.mobile_uuid === uuid(21)), false);
    const payload = { ...download(), household_request_outcomes: [{ mobile_uuid: parent.mobile_uuid,
      id: status === 'approved' ? 30 : null, purok_id: 1, local_revision: parent.local_revision,
      verification_status: status, verification_notes: 'Reviewed' }] };
    await storage.applyHouseholdReviewOutcomes(payload);
    const after = await db.getFirstAsync('SELECT * FROM field_visits WHERE mobile_uuid = ?', [uuid(21)]);
    assert.equal(after.photos_json, before.photos_json); assert.equal(after.sync_status, 'pending_create');
    assert.equal(after.local_revision, before.local_revision);
    assert.equal(after.household_server_id, status === 'approved' ? 30 : null);
    assert.equal((await db.getFirstAsync('SELECT * FROM households WHERE local_id = ?', [parent.local_id])).sync_status, 'synced');
    assert.equal((await storage.getPendingSyncPayload(1)).payload.field_visits.length, status === 'approved' ? 1 : 0);
    if (status === 'rejected') assert.match(await storage.getHouseholdWorkProtectionMessage(), /not approved/);
  });
}

test('higher post-review revision is protected separately and account/assignment/late-response guards reject review merges', async t => {
  const { storage, db } = await fixture(t); const parent = await pendingParent(storage, db);
  const { snapshot } = await storage.getPendingSyncPayload(1);
  await storage.saveHousehold({ ...parent, household_address: 'Newer unsent values' }, 1);
  await storage.applyResolvedRecords({ ...empty(), households: [{ mobile_uuid: parent.mobile_uuid, id: 30, verification_status: 'approved' }] }, snapshot);
  let row = await db.getFirstAsync('SELECT * FROM households WHERE local_id = ?', [parent.local_id]);
  assert.equal(row.server_id, null); assert.equal(row.sync_status, 'pending_create'); assert.ok(row.protection_reason);
  assert.equal(row.household_address, 'Newer unsent values');
  const outcome = { ...download(), household_request_outcomes: [{ mobile_uuid: parent.mobile_uuid, id: 30,
    purok_id: 1, local_revision: parent.local_revision, verification_status: 'approved' }] };
  await storage.applyHouseholdReviewOutcomes(outcome);
  row = await db.getFirstAsync('SELECT * FROM households WHERE local_id = ?', [parent.local_id]);
  assert.equal(row.server_id, null); assert.equal(row.sync_status, 'pending_create');
  await assert.rejects(storage.applyHouseholdReviewOutcomes({ ...outcome, user: { id: 2 } }), { name: 'DatasetOwnershipError' });
  await assert.rejects(storage.applyHouseholdReviewOutcomes(outcome, () => false), { name: 'AssignmentChangedError' });
  await storage.verifyDatasetAssignment(1, 1, 2);
  await assert.rejects(storage.applyHouseholdReviewOutcomes(outcome), { name: 'AssignmentChangedError' });
});

test('review refresh cannot convert a lost-response new request into an automatic correction', async t => {
  const { storage, db } = await fixture(t);
  const id = await storage.saveHousehold(request(), 1); const parent = await storage.getHouseholdRequestByLocalId(id);
  await storage.applyHouseholdReviewOutcomes({ ...download(), household_request_outcomes: [{ mobile_uuid: parent.mobile_uuid,
    id: 30, purok_id: 1, local_revision: parent.local_revision, verification_status: 'approved' }] });
  const { payload, snapshot } = await storage.getPendingSyncPayload(1);
  assert.equal(payload.households[0].id, undefined);
  assert.equal(payload.households[0].base_snapshot, undefined);
  await storage.applyResolvedRecords({ ...empty(), households: [{ mobile_uuid: parent.mobile_uuid, id: 30, verification_status: 'approved' }] }, snapshot);
  assert.equal((await db.getFirstAsync('SELECT * FROM households WHERE local_id = ?', [id])).server_id, 30);
  assert.equal(await storage.getHouseholdByLocalId(id), null, 'official operational access awaits its authoritative download');
});

test('AppContext partial legacy-parent sync consumes only safe outcome metadata and next manual sync resolves Visit', async t => {
  let parent; let approved = false;
  const h = await appContextHarness({
    mobileBootstrap: async () => ({ ...download(), household_request_outcomes: parent ? [{ mobile_uuid: parent.mobile_uuid,
      id: approved ? 30 : null, purok_id: 1, local_revision: parent.local_revision,
      verification_status: approved ? 'approved' : 'submitted' }] : [] }),
    mobileSync: async (_base, _token, payload) => ({ status: 'partial', synced_at: '2026-10-07', failed_records: [{ message: 'Household awaiting verification' }],
      resolved_records: { ...empty(), households: payload.households.map(row => ({ id: null, mobile_uuid: row.mobile_uuid, verification_status: 'submitted' })) } }),
  });
  t.after(h.close); await h.render().signIn({ email: '1', password: 'password' });
  parent = await pendingParent(h.storage, h.db); approved = true;
  await h.render().syncNow();
  const visit = await h.db.getFirstAsync('SELECT * FROM field_visits WHERE mobile_uuid = ?', [uuid(21)]);
  assert.equal(visit.household_server_id, 30); assert.equal(visit.sync_status, 'pending_create');
  assert.equal((await h.storage.getPendingSyncPayload(1)).payload.field_visits[0].household_id, 30);
  assert.equal((await h.db.getFirstAsync('SELECT * FROM households WHERE local_id = ?', [parent.local_id])).sync_status, 'synced');
});

test('failed Visit photo upload preserves pending identity/bytes, blocks refresh and permits manual retry', async t => {
  let accepted;
  let attempts = 0;
  const h = await appContextHarness({
    mobileBootstrap: async () => ({ ...download(), field_visits: accepted ? [{
      id: 700, mobile_uuid: accepted.mobile_uuid, household_id: 1, household_mobile_uuid: uuid(1),
      visited_at: accepted.visited_at, notes: accepted.notes,
      photos: [{ path: 'visit-photos/2026/10/synthetic.jpg', mime_type: 'image/jpeg' }],
    }] : [] }),
    mobileSync: async (_base, _token, payload) => {
      if (++attempts === 1) return { status: 'failed', synced_at: '2026-10-08',
        failed_records: [{ collection: 'field_visits', index: 0, message: 'Visit photo upload failed. Please retry.' }],
        resolved_records: empty() };
      accepted = payload.field_visits[0];
      return { status: 'success', synced_at: '2026-10-08', failed_records: [],
        resolved_records: { ...empty(), field_visits: [{ id: 700, mobile_uuid: accepted.mobile_uuid, household_id: 1 }] } };
    },
  });
  t.after(async () => {
    for (let n = 0; n < 30; n++) await new Promise(setImmediate);
    h.close();
  });
  await h.render().signIn({ email: '1', password: 'password' });
  await h.storage.saveVisit({ household_server_id: 1, visited_at: '2026-10-08', notes: 'Test visit',
    photos: [{ uri: 'file:///pending.jpg', file_name: 'pending.jpg', mime_type: 'image/jpeg', base64: 'cGhvdG8=' }] }, 1);
  const before = await h.db.getFirstAsync("SELECT * FROM field_visits WHERE sync_status = 'pending_create'");
  const localId = before.local_id;
  const downloads = h.calls.filter(call => call.name === 'mobileBootstrap').length;
  await h.render().syncNow();
  assert.deepEqual(await h.db.getFirstAsync('SELECT * FROM field_visits WHERE local_id = ?', [localId]), before);
  assert.equal(h.render().pendingSyncCount, 1);
  assert.equal(h.calls.filter(call => call.name === 'mobileBootstrap').length, downloads);
  await assert.rejects(h.storage.replaceBootstrapData(download()), { name: 'RefreshDeferredError' });
  assert.deepEqual(await h.db.getFirstAsync('SELECT * FROM field_visits WHERE local_id = ?', [localId]), before);
  await h.render().syncNow();
  const uploads = h.calls.filter(call => call.name === 'mobileSync').map(call => call.args[2].field_visits[0]);
  assert.equal(uploads.length, 2);
  assert.deepEqual(uploads[1], uploads[0]);
  assert.equal(uploads[1].mobile_uuid, before.mobile_uuid);
  assert.equal(uploads[1].photos[0].data, 'cGhvdG8=');
  const after = await h.storage.getVisitByLocalId(localId);
  assert.equal(after.local_id, before.local_id);
  assert.equal(after.mobile_uuid, before.mobile_uuid);
  assert.equal(after.server_id, 700);
  assert.equal(after.sync_status, 'synced');
  assert.equal(h.render().pendingSyncCount, 0);
});
