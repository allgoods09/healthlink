import assert from 'node:assert/strict';
import { test } from 'node:test';
import { storageHarness } from './storageHarness.mjs';

const tables = ['households', 'residents', 'field_visits', 'risk_assessments'];
const uuid = n => `00000000-0000-4000-8000-${String(n).padStart(12, '0')}`;
const emptyResolved = () => Object.fromEntries(tables.map(table => [table, []]));
const bootstrap = (households = []) => ({
  resident_contract_version: 2,
  user: { id: 1 }, assignment: { barangay: { id: 1 }, purok: { id: 1 } }, server_time: '2026-10-03T10:00:00Z',
  households, residents: [], field_visits: [], risk_assessments: [],
});
const household = (n = 1) => ({
  mobile_uuid: uuid(n), household_no: String(n), household_address: 'Original address',
  purok_id: 1, purok_display_name: 'Purok 1', is_active: true, is_social_aid_beneficiary: false,
});
const serverHousehold = (n = 1) => ({ ...household(n), id: n, updated_at: '2026-10-03' });
async function fixture(t) {
  const harness = await storageHarness();
  t.after(harness.close);
  const raw = harness.storage;
  await raw.prepareDatasetForUser(1);
  await raw.replaceBootstrapData({ ...bootstrap(), server_time: '' });
  // C3 scenarios run as the established owner; C4 tests exercise different users.
  harness.storage = { ...raw,
    saveHousehold: values => raw.saveHousehold(values, 1),
    saveResident: values => raw.saveResident(values, 1),
    saveVisit: values => raw.saveVisit(values, 1),
    saveRiskAssessment: values => raw.saveRiskAssessment(values, 1),
    getPendingSyncPayload: () => raw.getPendingSyncPayload(1),
  };
  return harness;
}
async function seedLegacyResident(db) {
  // Existing pre-patch queued package, not a newly allowed UI destination.
  await db.runAsync(`INSERT INTO residents (mobile_uuid, household_mobile_uuid, first_name, last_name,
    birth_date, birth_place, sex, civil_status, citizenship, relationship_to_head, is_active, sync_status, verification_status)
    VALUES (?, ?, 'Ana', 'Test', '1990-01-01', 'Tubigon', 'Female', 'Single', 'Filipino', 'Head', 1, 'pending_create', 'pending')`, [uuid(2), uuid(1)]);
}
async function seedAll(storage, db) {
  await storage.saveHousehold(household());
  await seedLegacyResident(db);
  await storage.saveVisit({ mobile_uuid: uuid(3), household_mobile_uuid: uuid(1), visited_at: '2026-10-03', notes: 'Original', photos: [] });
  await storage.saveRiskAssessment({ mobile_uuid: uuid(4), resident_server_id: 2, assessment_date: '2026-10-03', red_flags: {}, remarks: 'Original' });
}
const resolvedAll = () => Object.fromEntries(tables.map((table, i) => [table, [{ id: i + 1, mobile_uuid: uuid(i + 1), updated_at: '2026-10-03' }]]));

test('unchanged uploaded revisions are acknowledged for all four datasets', async t => {
  const { storage, db } = await fixture(t);
  await seedAll(storage, db);
  const { payload, snapshot } = await storage.getPendingSyncPayload();
  assert.equal(payload.households[0].local_revision, snapshot.rows.households[0].local_revision);
  await storage.applyResolvedRecords(resolvedAll(), snapshot);
  assert.equal((await storage.getPendingChangeSummary()).total, 0);
  assert.equal((await db.getFirstAsync('SELECT * FROM residents')).household_server_id, 1);
});

for (const [table, save, read, change] of [
  ['households', 'saveHousehold', 'getHouseholds', { household_address: 'New address' }],
  ['residents', 'saveResident', 'getResidentRequests', { first_name: 'Updated Ana' }],
  ['field_visits', 'saveVisit', 'getVisits', { notes: 'New notes' }],
]) {
  test(`${table}: edit after upload remains pending, then next manual upload succeeds`, async t => {
    const { storage, db } = await fixture(t);
    await seedAll(storage, db);
    const { snapshot } = await storage.getPendingSyncPayload();
    const record = (await storage[read]())[0];
    await storage[save]({ ...record, ...change });
    await storage.applyResolvedRecords(resolvedAll(), snapshot);
    const current = await db.getFirstAsync(`SELECT * FROM ${table}`);
    assert.equal(current.sync_status, 'pending_update');
    for (const [key, value] of Object.entries(change)) assert.equal(current[key], value);
    await assert.rejects(storage.replaceBootstrapData(bootstrap()), { name: 'RefreshDeferredError' });
    const next = await storage.getPendingSyncPayload();
    await storage.applyResolvedRecords(resolvedAll(), next.snapshot);
    assert.equal((await storage.getPendingChangeSummary()).total, 0);
  });
}

test('assessment edited during upload keeps a new draft and the acknowledged immutable history', async t => {
  const { storage, db } = await fixture(t);
  await seedAll(storage, db);
  const { snapshot } = await storage.getPendingSyncPayload();
  const original = (await storage.getRiskAssessmentsForResident(2))[0];
  await storage.saveRiskAssessment({ ...original, remarks: 'New assessment notes' });
  await storage.applyResolvedRecords(resolvedAll(), snapshot);
  const rows = await db.getAllAsync('SELECT * FROM risk_assessments ORDER BY local_id');
  assert.equal(rows.length, 2);
  assert.equal(rows[0].local_id, original.local_id);
  assert.equal(rows[0].server_id, null);
  assert.equal(rows[0].sync_status, 'pending_create');
  assert.equal(rows[0].remarks, 'New assessment notes');
  assert.notEqual(rows[0].mobile_uuid, original.mobile_uuid);
  assert.equal(rows[1].sync_status, 'synced');
  assert.equal(rows[1].remarks, 'Original');
  await storage.saveRiskAssessment({ ...original, remarks: 'Still editing' });
  assert.equal((await db.getFirstAsync('SELECT * FROM risk_assessments WHERE local_id = ?', [original.local_id])).mobile_uuid, rows[0].mobile_uuid);
});

test('pending creation after the earlier empty check defers refresh without losing work', async t => {
  const { storage } = await fixture(t);
  assert.equal((await storage.getPendingChangeSummary()).total, 0);
  await storage.saveHousehold(household());
  await assert.rejects(storage.replaceBootstrapData(bootstrap()), { name: 'RefreshDeferredError' });
  assert.equal((await storage.getHouseholds())[0].household_address, 'Original address');
  assert.equal(await storage.getAppState('last_sync_at'), '');
});

test('successful upload and bootstrap preserve local identity regardless of payload order', async t => {
  const { storage } = await fixture(t);
  await storage.saveHousehold(household());
  const localId = (await storage.getHouseholds())[0].local_id;
  const { snapshot } = await storage.getPendingSyncPayload();
  await storage.applyResolvedRecords({ ...emptyResolved(), households: resolvedAll().households }, snapshot);
  await storage.replaceBootstrapData(bootstrap([serverHousehold(2), serverHousehold(1)]));
  assert.equal((await storage.getHouseholdByLocalId(localId)).server_id, 1);
  assert.equal((await storage.getPendingChangeSummary()).total, 0);
  assert.equal(await storage.getAppState('last_sync_at'), '2026-10-03T10:00:00Z');
});

test('registry submission stays nonofficial until approval and refresh reconciles by UUID', async t => {
  const { storage, db } = await fixture(t);
  await storage.saveHousehold(household());
  await seedLegacyResident(db);
  const originalHousehold = (await storage.getHouseholds())[0];
  const originalResident = (await storage.getResidentRequests())[0];
  const { payload, snapshot } = await storage.getPendingSyncPayload();
  assert.equal(payload.households[0].local_revision, originalHousehold.local_revision);
  assert.equal(payload.residents[0].local_revision, originalResident.local_revision);
  const submitted = { ...emptyResolved(),
    households: [{ id: null, mobile_uuid: uuid(1), verification_status: 'submitted' }],
    residents: [{ id: null, mobile_uuid: uuid(2), verification_status: 'submitted' }],
  };
  await storage.applyResolvedRecords(submitted, snapshot);
  let saved = await db.getFirstAsync('SELECT * FROM households');
  assert.equal(saved.server_id, null);
  assert.equal(saved.sync_status, 'synced');
  assert.equal(saved.verification_status, 'submitted');

  const submittedPayload = { ...bootstrap([{ ...household(), id: null, verification_status: 'submitted' }]),
    residents: [{ ...originalResident, id: null, household_id: null,
      verification_status: 'submitted', updated_at: '2026-10-03' }],
  };
  await storage.replaceBootstrapData(submittedPayload);
  assert.equal((await storage.getHouseholds())[0].local_id, originalHousehold.local_id);
  assert.equal((await storage.getResidentRequests())[0].local_id, originalResident.local_id);
  assert.equal((await storage.getHouseholds())[0].verification_status, 'submitted');

  const approvedPayload = { ...bootstrap([{ ...serverHousehold(), verification_status: 'approved' }]),
    residents: [{ ...submittedPayload.residents[0], id: 2, household_id: 1,
      resident_status: 'active', verification_status: 'approved' }],
  };
  await storage.replaceBootstrapData(approvedPayload);
  const households = await storage.getHouseholds();
  const residents = await storage.getResidents();
  assert.equal(households.length, 1);
  assert.equal(residents.length, 1);
  assert.equal(households[0].local_id, originalHousehold.local_id);
  assert.equal(households[0].server_id, 1);
  assert.equal(residents[0].local_id, originalResident.local_id);
  assert.equal(residents[0].server_id, 2);
  assert.equal(residents[0].household_server_id, 1);
  assert.equal(residents[0].verification_status, 'approved');
});

test('editing during draft upload keeps the newer local revision pending', async t => {
  const { storage } = await fixture(t);
  await storage.saveHousehold(household());
  const { snapshot } = await storage.getPendingSyncPayload();
  const original = (await storage.getHouseholds())[0];
  await storage.saveHousehold({ ...original, household_address: 'Corrected while uploading' });
  await storage.applyResolvedRecords({ ...emptyResolved(), households: [
    { id: null, mobile_uuid: uuid(1), verification_status: 'submitted' },
  ] }, snapshot);
  const current = (await storage.getHouseholds())[0];
  assert.equal(current.household_address, 'Corrected while uploading');
  assert.equal(current.sync_status, 'pending_create');
  assert.equal(current.server_id, null);
});

test('partial acknowledgments clear only uploaded successful records', async t => {
  const { storage } = await fixture(t);
  await storage.saveHousehold(household(1));
  await storage.saveHousehold(household(2));
  const { snapshot } = await storage.getPendingSyncPayload();
  // A network failure before acknowledgment changes nothing locally.
  assert.equal((await storage.getPendingChangeSummary()).total, 2);
  await storage.applyResolvedRecords({ ...emptyResolved(), households: resolvedAll().households }, snapshot);
  assert.equal((await storage.getPendingChangeSummary()).total, 1);
  await assert.rejects(storage.replaceBootstrapData(bootstrap()), { name: 'RefreshDeferredError' });
  assert.equal((await storage.getHouseholds()).length, 2);
});

test('failed bootstrap insertion rolls back records and refresh metadata', async t => {
  const { storage } = await fixture(t);
  await storage.replaceBootstrapData(bootstrap([serverHousehold()]));
  await assert.rejects(storage.replaceBootstrapData({ ...bootstrap([serverHousehold(2), serverHousehold(2)]), server_time: 'later' }));
  assert.equal((await storage.getHouseholds())[0].server_id, 1);
  assert.equal(await storage.getAppState('last_sync_at'), '2026-10-03T10:00:00Z');
});

test('save requested inside refresh waits until replacement finishes and remains pending', async t => {
  const { storage, intercept } = await fixture(t);
  await storage.replaceBootstrapData(bootstrap([serverHousehold()]));
  const original = (await storage.getHouseholds())[0];
  let save;
  intercept(async sql => {
    if (sql === 'DELETE FROM households' && !save) {
      save = storage.saveHousehold({ ...original, household_address: 'Saved during refresh' });
    }
  });
  await storage.replaceBootstrapData(bootstrap([serverHousehold()]));
  await save;
  const current = (await storage.getHouseholds())[0];
  assert.equal(current.household_address, 'Saved during refresh');
  assert.equal(current.sync_status, 'pending_update');
});

test('open unsaved form defers refresh, including a form mounted during replacement', async t => {
  const { storage, guard, intercept } = await fixture(t);
  let closeEditor = guard.registerLocalEditor();
  await assert.rejects(storage.replaceBootstrapData(bootstrap()), { name: 'RefreshDeferredError' });
  closeEditor();
  await storage.replaceBootstrapData(bootstrap([serverHousehold()]));
  intercept(async sql => {
    if (sql === 'DELETE FROM households') closeEditor = guard.registerLocalEditor();
  });
  await assert.rejects(storage.replaceBootstrapData(bootstrap()), { name: 'RefreshDeferredError' });
  closeEditor();
  assert.equal((await storage.getHouseholds()).length, 1);
});

test('all four datasets retain local IDs after repeated refreshes', async t => {
  const { storage, db } = await fixture(t);
  await seedAll(storage, db);
  const { snapshot } = await storage.getPendingSyncPayload();
  await storage.applyResolvedRecords(resolvedAll(), snapshot);
  const downloaded = bootstrap();
  const identities = {};
  for (const table of tables) {
    const row = await db.getFirstAsync(`SELECT * FROM ${table}`);
    identities[table] = row.local_id;
    downloaded[table] = [{ ...row, id: row.server_id, household_id: row.household_server_id,
      resident_id: row.resident_server_id, photos: [], red_flags: {} }];
  }
  await storage.replaceBootstrapData(downloaded);
  await storage.replaceBootstrapData(downloaded);
  for (const table of tables) {
    assert.equal((await db.getFirstAsync(`SELECT * FROM ${table}`)).local_id, identities[table]);
  }
});

test('additive revision migration preserves existing rows and is repeatable', async t => {
  const { storage, db } = await fixture(t);
  await seedAll(storage, db);
  for (const table of tables) await db.execAsync(`ALTER TABLE ${table} DROP COLUMN local_revision`);
  await storage.initializeStorage();
  await storage.initializeStorage();
  for (const table of tables) {
    const rows = await db.getAllAsync(`SELECT * FROM ${table}`);
    assert.equal(rows.length, 1);
    assert.equal(rows[0].local_revision, 0);
    assert.equal(rows[0].sync_status, 'pending_create');
  }
});

test('unsent acknowledgments cannot clear pending records', async t => {
  const { storage } = await fixture(t);
  const { snapshot } = await storage.getPendingSyncPayload();
  await storage.saveHousehold(household());
  await storage.applyResolvedRecords(resolvedAll(), snapshot);
  assert.equal((await storage.getPendingChangeSummary()).total, 1);
});

test('edit using pre-acknowledgment form values keeps the assigned server identity', async t => {
  const { storage } = await fixture(t);
  await storage.saveHousehold(household());
  const form = (await storage.getHouseholds())[0];
  const { snapshot } = await storage.getPendingSyncPayload();
  await storage.applyResolvedRecords(resolvedAll(), snapshot);
  await storage.saveHousehold({ ...form, household_address: 'Saved after acknowledgment' });
  const current = (await storage.getHouseholds())[0];
  assert.equal(current.server_id, 1);
  assert.equal(current.sync_status, 'pending_update');
  assert.equal(current.household_address, 'Saved after acknowledgment');
});

test('deleted server rows cannot redirect stale local IDs to a different household', async t => {
  const { storage } = await fixture(t);
  await storage.replaceBootstrapData(bootstrap([serverHousehold()]));
  const old = (await storage.getHouseholds())[0];
  await storage.replaceBootstrapData(bootstrap([serverHousehold(2)]));
  assert.equal(await storage.getHouseholdByLocalId(old.local_id), null);
  await assert.rejects(storage.saveHousehold({ ...old, household_address: 'Stale edit' }), /reopen the form/);
  assert.equal((await storage.getHouseholds())[0].server_id, 2);
});
