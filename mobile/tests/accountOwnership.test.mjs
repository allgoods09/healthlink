import assert from 'node:assert/strict';
import { test } from 'node:test';
import { storageHarness } from './storageHarness.mjs';
import { withHouseholdContract } from './householdFixture.mjs';

export const household = {
  purok_id: 1,
  mobile_uuid: '00000000-0000-4000-8000-000000000001', household_no: '1',
  household_address: 'Synthetic address', is_active: true, is_social_aid_beneficiary: false,
};
const tables = ['households', 'residents', 'field_visits', 'risk_assessments'];
export const bootstrap = (userId = 1) => withHouseholdContract({
  resident_contract_version: 2,
  user: { id: userId }, assignment: { barangay: { id: userId }, purok: { id: userId } },
  server_time: '2026-10-03T12:00:00Z', households: [], residents: [], field_visits: [], risk_assessments: [],
});
async function fixture(t) {
  const h = await storageHarness();
  t.after(h.close);
  await h.storage.prepareDatasetForUser(1);
  await h.storage.replaceBootstrapData(withHouseholdContract({ ...bootstrap(), households: [{ ...household, mobile_uuid: null, id: 1, purok_id: 1 }] }));
  return h;
}
async function snapshot(db) {
  const data = {};
  for (const table of [...tables, 'app_state']) data[table] = await db.getAllAsync(`SELECT * FROM ${table}`);
  return data;
}
const drafts = {
  household: s => s.saveHousehold(household, 1),
  resident: s => s.saveResident({
    household_server_id: 1,
    first_name: 'Ana', last_name: 'Synthetic', birth_date: '1990-01-01', birth_place: 'Tubigon',
    sex: 'Female', civil_status: 'Single', citizenship: 'Filipino', relationship_to_head: 'Head', is_active: true,
  }, 1),
  'visit with photo': s => s.saveVisit({
    household_server_id: 1, visited_at: '2026-10-03', notes: 'Pending visit',
    photos: [{ uri: 'file:///pending/photo.jpg', file_name: 'photo.jpg', mime_type: 'image/jpeg', base64: 'cGhvdG8=' }],
  }, 1),
  assessment: s => s.saveRiskAssessment({ resident_server_id: 1, assessment_date: '2026-10-03', red_flags: {} }, 1),
};

for (const [name, save] of Object.entries(drafts)) {
  test(`different account is blocked by pending ${name}; all data and ownership remain identical`, async t => {
    const { storage, db } = await fixture(t);
    await save(storage);
    await storage.clearLocalSession();
    const before = await snapshot(db);
    await assert.rejects(storage.prepareDatasetForUser(2), { name: 'AccountSwitchBlockedError' });
    assert.deepEqual(await snapshot(db), before);
    assert.equal((await storage.getPendingChangeSummary()).total, 1);
    await assert.rejects(storage.getPendingSyncPayload(2), { name: 'DatasetOwnershipError' });
    await assert.rejects(storage.replaceBootstrapData(bootstrap(2)), { name: 'DatasetOwnershipError' });
    assert.deepEqual(await snapshot(db), before);
  });
}

test('same account logs out and returns to pending records/photos without clearing them', async t => {
  const { storage, db } = await fixture(t);
  await drafts.household(storage);
  await drafts['visit with photo'](storage);
  await storage.storeToken('test-token-a');
  await storage.setAppState('session_user', JSON.stringify({ id: 1 }));
  const before = await db.getAllAsync('SELECT * FROM field_visits');
  await storage.clearLocalSession();
  assert.equal(await storage.loadToken(), null);
  assert.equal(await storage.getAppState('session_user'), '');
  assert.equal(await storage.getDatasetOwnerUserId(), '1');
  await storage.prepareDatasetForUser(1);
  assert.deepEqual(await db.getAllAsync('SELECT * FROM field_visits'), before);
  assert.equal((await storage.getPendingSyncPayload(1)).payload.field_visits[0].photos[0].data, 'cGhvdG8=');
  assert.equal((await storage.getPendingChangeSummary()).total, 2);
});

test('clean downloaded dataset can switch accounts and bootstrap the new scope', async t => {
  const { storage } = await fixture(t);
  await storage.replaceBootstrapData(withHouseholdContract({ ...bootstrap(), households: [{ ...household, id: 1 }] }));
  await storage.clearLocalSession();
  await storage.prepareDatasetForUser(2);
  assert.equal(await storage.getDatasetOwnerUserId(), '2');
  assert.equal((await storage.getHouseholds()).length, 0);
  assert.equal(await storage.hasBootstrapData(), false);
  await storage.replaceBootstrapData(bootstrap(2));
  assert.equal(await storage.hasBootstrapData(), true);
  assert.equal(JSON.parse(await storage.getDatasetAssignment()).barangay.id, 2);
});

test('successfully acknowledged old drafts permit switching; stale acknowledgments and saves do not', async t => {
  const { storage } = await fixture(t);
  await storage.saveHousehold(household, 1);
  const { snapshot: upload } = await storage.getPendingSyncPayload(1);
  const resolved = { households: [{ id: 2, mobile_uuid: household.mobile_uuid }], residents: [], field_visits: [], risk_assessments: [] };
  await storage.applyResolvedRecords(resolved, upload);
  await storage.prepareDatasetForUser(2);
  await assert.rejects(storage.applyResolvedRecords(resolved, upload), { name: 'DatasetOwnershipError' });
  await assert.rejects(storage.saveHousehold(household, 1), { name: 'DatasetOwnershipError' });
  await assert.rejects(storage.getPendingSyncPayload(1), { name: 'DatasetOwnershipError' });
  assert.equal(await storage.getDatasetOwnerUserId(), '2');
  assert.equal((await storage.getPendingChangeSummary()).total, 0);
});

test('a queued save cannot slip between the switch check and clearing', async t => {
  const { storage } = await fixture(t);
  const saving = storage.saveHousehold(household, 1);
  const switching = storage.prepareDatasetForUser(2);
  await saving;
  await assert.rejects(switching, { name: 'AccountSwitchBlockedError' });
  assert.equal((await storage.getPendingChangeSummary()).total, 1);
});

test('pending work without ownership metadata is not claimed by a new account', async t => {
  const { storage } = await fixture(t);
  await storage.saveHousehold(household, 1);
  await storage.setAppState('dataset_owner_user_id', '');
  await assert.rejects(storage.prepareDatasetForUser(2), { name: 'AccountSwitchBlockedError' });
  assert.equal(await storage.getDatasetOwnerUserId(), '');
  assert.equal((await storage.getPendingChangeSummary()).total, 1);
});

test('normal same-account logout/login without pending work reuses downloaded data', async t => {
  const { storage } = await fixture(t);
  await storage.replaceBootstrapData(withHouseholdContract({ ...bootstrap(), households: [{ ...household, id: 1 }] }));
  await storage.clearLocalSession();
  await storage.prepareDatasetForUser(1);
  assert.equal(await storage.hasBootstrapData(), true);
  assert.equal((await storage.getHouseholds()).length, 1);
});
