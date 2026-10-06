import assert from 'node:assert/strict';
import { test } from 'node:test';
import { storageHarness } from './storageHarness.mjs';

export const home = (id = 1, purok = 1) => ({ id, mobile_uuid: null, purok_id: purok,
  household_no: String(id), household_address: 'Synthetic fixture', is_active: true, is_social_aid_beneficiary: false });
export const resident = (id = 1, overrides = {}) => ({ id, mobile_uuid: null, household_id: 1,
  first_name: 'Ana', last_name: 'Synthetic', middle_name: null, suffix: null,
  birth_date: '1990-01-01', birth_place: 'Tubigon', sex: 'Female', civil_status: 'Single',
  citizenship: 'Filipino', relationship_to_head: 'Child', is_active: true,
  resident_status: 'active', deleted_at: null, ...overrides });
export const payload = (purok = 1, barangay = 1) => ({ resident_contract_version: 1,
  user: { id: 1 }, assignment: { barangay: { id: barangay }, purok: { id: purok } },
  server_time: '2026-10-06', households: [home(1, purok)], residents: [resident()], field_visits: [], risk_assessments: [] });
async function fixture(t, data = payload()) {
  const h = await storageHarness();
  t.after(h.close);
  await h.storage.prepareDatasetForUser(1);
  await h.storage.replaceBootstrapData(data);
  return h;
}

test('current queries/count/direct IDs enforce officialness, lifecycle and authoritative household scope, not availability', async t => {
  const data = payload();
  data.households.push(home(2, 2), home(3, 99));
  data.residents = [resident(1), resident(2, { is_active: false, verification_status: 'submitted' }),
    ...['deceased', 'moved_out', 'relocated'].map((resident_status, i) => resident(i + 3, { resident_status })),
    resident(6, { deleted_at: '2026-10-01' }), resident(7, { household_id: 2 }), resident(8, { household_id: 3 }),
    resident(null, { mobile_uuid: '00000000-0000-4000-8000-000000000010', verification_status: 'submitted' }),
    resident(null, { mobile_uuid: '00000000-0000-4000-8000-000000000011', verification_status: 'rejected' })];
  const { storage, db } = await fixture(t, data);
  assert.deepEqual((await storage.getResidents()).map(r => r.server_id), [1, 2]);
  assert.equal(await storage.getCurrentOfficialResidentCount(), 2);
  assert.equal((await storage.getResidentRequests()).length, 2);
  const all = await db.getAllAsync('SELECT * FROM residents');
  for (const r of all) {
    const result = await storage.getResidentByLocalId(r.local_id);
    assert.equal(Boolean(result), [1, 2].includes(r.server_id));
  }
  assert.equal((await storage.getResidents('Synthetic')).length, 2);
  assert.equal((await storage.getResidentsForHousehold({ server_id: 2 })).length, 0);
  assert.equal((await storage.getResidentsForHousehold({ server_id: 1 })).length, 2);
  assert.equal(all.length, 10, 'ineligible and request rows are preserved, not deleted');
});

test('formal name order is total and 50-row continuation has no duplicates/skips with unchanged data', async t => {
  const data = payload();
  data.residents = Array.from({ length: 130 }, (_, i) => resident(i + 1, {
    last_name: i < 5 ? 'Alpha' : 'Synthetic', first_name: i < 5 ? 'Name' : 'Same',
    middle_name: i < 5 ? ['C', 'A', 'A', 'A', 'B'][i] : null,
    suffix: i === 2 ? 'Jr.' : null,
  }));
  const { storage } = await fixture(t, data);
  let page = await storage.getCurrentOfficialResidentsPage();
  assert.equal(page.rows.length, 50);
  assert.equal(page.total, 130);
  assert.deepEqual(page.rows.slice(0, 5).map(r => r.server_id), [2, 4, 3, 5, 1]);
  const ids = page.rows.map(r => r.local_id);
  while (page.next) {
    page = await storage.getCurrentOfficialResidentsPage({}, page.next);
    assert.equal(page.total, 130);
    ids.push(...page.rows.map(r => r.local_id));
  }
  assert.equal(page.rows.length, 30);
  assert.equal(ids.length, 130);
  assert.equal(new Set(ids).size, 130);
  const first = await storage.getCurrentOfficialResidentsPage();
  assert.equal((await storage.getCurrentOfficialResidentsPage({ search: 'Alpha' }, first.next)).invalidated, true);
  assert.equal((await storage.getCurrentOfficialResidentsPage({ search: 'Alpha' })).total, 5);
  await storage.replaceBootstrapData(data);
  assert.equal((await storage.getCurrentOfficialResidentsPage({}, first.next)).invalidated, true);
  assert.deepEqual((await storage.getCurrentOfficialResidentsPage()).rows.map(r => r.local_id), first.rows.map(r => r.local_id));
});

test('data mutation and verified reassignment invalidate continuation, counts and direct IDs immediately', async t => {
  const data = payload();
  data.residents = Array.from({ length: 60 }, (_, i) => resident(i + 1));
  const { storage } = await fixture(t, data);
  const first = await storage.getCurrentOfficialResidentsPage();
  await storage.saveResident({ ...first.rows[0], first_name: 'Proposal' }, 1);
  assert.equal((await storage.getCurrentOfficialResidentsPage({}, first.next)).invalidated, true);
  assert.equal(await storage.getCurrentOfficialResidentCount(), 60, 'pending official correction remains official');
  await storage.verifyDatasetAssignment(1, 1, 2);
  assert.equal(await storage.getCurrentOfficialResidentCount(), 0);
  assert.equal(await storage.getResidentByLocalId(first.rows[0].local_id), null);
  assert.equal((await storage.getCurrentOfficialResidentsPage({}, first.next)).invalidated, true);
  await assert.rejects(storage.getPendingSyncPayload(1), { name: 'AssignmentChangedError' });
  await assert.rejects(storage.saveResident({ ...first.rows[1], first_name: 'Stale editor' }, 1), { name: 'AssignmentChangedError' });
  assert.equal((await storage.getPendingChangeSummary()).total, 1);
});

test('null/invalid assignment and incompatible old contract fail closed without deleting records', async t => {
  const { storage, db } = await fixture(t);
  const before = await db.getAllAsync('SELECT * FROM residents');
  await storage.setAppState('resident_contract_version', '');
  assert.equal(await storage.hasBootstrapData(), false);
  assert.equal(await storage.getCurrentOfficialResidentCount(), 0);
  assert.equal(await storage.getResidentByLocalId(before[0].local_id), null);
  await storage.verifyDatasetAssignment(1, 1, null);
  assert.equal((await storage.getResidents()).length, 0);
  assert.deepEqual(await db.getAllAsync('SELECT * FROM residents'), before);
});

test('additive lifecycle migration preserves IDs, revisions, pending requests/corrections and pending photo rows', async t => {
  const { storage, db } = await fixture(t);
  const current = (await storage.getResidents())[0];
  await storage.saveResident({ ...current, first_name: 'Correction' }, 1);
  await storage.saveResident({ ...resident(null), household_server_id: 1, first_name: 'NewRequest' }, 1);
  await storage.saveVisit({ household_server_id: 1, visited_at: '2026-10-06',
    photos: [{ uri: 'file:///pending/photo.jpg', base64: 'cGhvdG8=', file_name: 'photo.jpg', mime_type: 'image/jpeg' }] }, 1);
  const before = await db.getAllAsync('SELECT local_id, server_id, mobile_uuid, local_revision, sync_status FROM residents');
  const photos = await db.getAllAsync('SELECT * FROM field_visits');
  await db.execAsync('DROP INDEX residents_current_name; ALTER TABLE residents DROP COLUMN resident_status; ALTER TABLE residents DROP COLUMN deleted_at;');
  await storage.setAppState('resident_contract_version', '');
  await storage.initializeStorage();
  await storage.initializeStorage();
  assert.deepEqual(await db.getAllAsync('SELECT local_id, server_id, mobile_uuid, local_revision, sync_status FROM residents'), before);
  assert.deepEqual(await db.getAllAsync('SELECT * FROM field_visits'), photos);
  assert.equal((await storage.getPendingChangeSummary()).total, 3);
  assert.equal(await storage.getCurrentOfficialResidentCount(), 0);
  assert.ok((await db.getAllAsync('SELECT resident_status FROM residents')).every(r => r.resident_status === null));
});

test('household identity join honors server ID priority even with conflicting UUIDs', async t => {
  const data = payload();
  data.households.push({ ...home(2, 2), mobile_uuid: '00000000-0000-4000-8000-000000000012' });
  data.residents[0].household_mobile_uuid = data.households[1].mobile_uuid;
  const { storage } = await fixture(t, data);
  assert.equal(await storage.getCurrentOfficialResidentCount(), 1);
  assert.equal((await storage.getResidents())[0].household_purok_id, 1);
});

test('existing screening choices are applied before pagination with scoped totals', async t => {
  const data = payload();
  data.residents = Array.from({ length: 100 }, (_, i) => resident(i + 1, { birth_date: i < 60 ? '2020-01-01' : '1990-01-01' }));
  const { storage } = await fixture(t, data);
  const page = await storage.getCurrentOfficialResidentsPage({ screening: 'due30', asOf: '2026-10-06' });
  assert.equal(page.rows.length, 40);
  assert.equal(page.total, 40);
  assert.equal(page.next, null);
});

test('late acknowledgment after reassignment cannot clear or relabel old pending work', async t => {
  const { storage, db } = await fixture(t);
  await storage.saveResident({ ...(await storage.getResidents())[0], first_name: 'Pending' }, 1);
  const { payload: upload, snapshot } = await storage.getPendingSyncPayload(1);
  const before = await db.getAllAsync('SELECT * FROM residents');
  await storage.verifyDatasetAssignment(1, 1, 2);
  await assert.rejects(storage.applyResolvedRecords({ households: [], residents: [{ id: 1,
    mobile_uuid: upload.residents[0].mobile_uuid, verification_status: 'submitted' }], field_visits: [], risk_assessments: [] }, snapshot),
  { name: 'AssignmentChangedError' });
  assert.deepEqual(await db.getAllAsync('SELECT * FROM residents'), before);
});

test('pending cache with unknown original assignment is never claimed by online verification', async t => {
  const { storage } = await fixture(t);
  await storage.saveResident({ ...(await storage.getResidents())[0], first_name: 'Pending' }, 1);
  await storage.setAppState('dataset_assignment', '');
  await storage.verifyDatasetAssignment(1, 1, 2);
  assert.equal(await storage.hasBootstrapData(), false);
  await assert.rejects(storage.getPendingSyncPayload(1), { name: 'AssignmentChangedError' });
  assert.equal((await storage.getPendingChangeSummary()).total, 1);
});

test('bootstrap cancellation during inserts rolls back records, assignment and contract together', async t => {
  const { storage, db, intercept } = await fixture(t);
  const before = await db.getAllAsync('SELECT * FROM residents');
  const oldAssignment = await storage.getDatasetAssignment();
  let applicable = true;
  intercept(async sql => { if (sql.includes('INSERT INTO residents')) applicable = false; });
  await assert.rejects(storage.replaceBootstrapData(payload(), () => applicable), { name: 'DatasetOwnershipError' });
  assert.deepEqual(await db.getAllAsync('SELECT * FROM residents'), before);
  assert.equal(await storage.getDatasetAssignment(), oldAssignment);
  assert.equal(await storage.getAppState('resident_contract_version'), '1');
});
