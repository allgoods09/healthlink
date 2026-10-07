import assert from 'node:assert/strict';
import { test } from 'node:test';
import { appContextHarness } from './appContextHarness.mjs';
import { withHouseholdContract } from './householdFixture.mjs';

const ids = (purok = 1, barangay = 1) => ({ id: 1, assigned_barangay_id: barangay, assigned_purok_id: purok });
const assignment = (purok = 1, barangay = 1) => ({ barangay: { id: barangay }, purok: { id: purok } });
const data = (purok = 1, barangay = 1) => withHouseholdContract({ resident_contract_version: 2, user: { id: 1 },
  assignment: assignment(purok, barangay), server_time: '2026-10-06',
  households: [{ id: purok, mobile_uuid: null, purok_id: purok, household_no: '1', household_address: 'Synthetic',
    is_active: true, is_social_aid_beneficiary: false }],
  residents: [{ id: purok, mobile_uuid: null, household_id: purok, first_name: `Purok${purok}`, last_name: 'Synthetic',
    birth_date: '1990-01-01', birth_place: 'Tubigon', sex: 'Female', civil_status: 'Single', citizenship: 'Filipino',
    relationship_to_head: 'Child', is_active: true, resident_status: 'active', deleted_at: null }],
  field_visits: [], risk_assessments: [] });
const tick = () => new Promise(setImmediate);
async function settle() { for (let n = 0; n < 30; n++) await tick(); }
async function until(predicate) {
  for (let n = 0; n < 300; n++) { if (await predicate()) return; await tick(); }
  throw new Error('Session transition did not settle');
}
async function fixture(t, overrides = {}, setup = async () => {}) {
  const h = await appContextHarness({ mobileBootstrap: async () => data(), ...overrides }, async storage => {
    await storage.prepareDatasetForUser(1);
    await storage.replaceBootstrapData(data());
    await storage.setAppState('session_user', JSON.stringify(ids()));
    await storage.setAppState('session_assignment', JSON.stringify(assignment()));
    await setup(storage);
  });
  t.after(async () => { await settle(); h.close(); });
  return h;
}

test('same user/assignment reuses compatible cache including pending Resident work', async t => {
  const h = await fixture(t, {}, async storage => {
    await storage.saveResident({ ...(await storage.getResidents())[0], first_name: 'Correction' }, 1);
  });
  await h.render().signIn({ email: '1', password: 'test' });
  await settle();
  assert.equal(h.render().bootstrapCompleted, true);
  assert.equal(h.calls.filter(c => c.name === 'mobileBootstrap').length, 0);
  assert.equal(await h.storage.getCurrentOfficialResidentCount(), 1);
  assert.equal((await h.storage.getPendingChangeSummary()).total, 1);
});

for (const [label, nextPurok, nextBarangay] of [['Purok', 2, 1], ['Barangay', 2, 2]]) {
  test(`successful verification of changed ${label} blocks old reads before atomically committing new scope`, async t => {
    let finishDownload;
    const h = await fixture(t, {
      mobileVerify: async () => ({ valid: true, user: ids(nextPurok, nextBarangay) }),
      mobileBootstrap: () => new Promise(resolve => { finishDownload = () => resolve(data(nextPurok, nextBarangay)); }),
    }, async storage => { await storage.storeToken('token-1'); });
    const oldLocalId = (await h.db.getFirstAsync('SELECT local_id FROM residents')).local_id;
    await until(() => Boolean(finishDownload));
    assert.equal(await h.storage.getCurrentOfficialResidentCount(), 0);
    assert.equal(await h.storage.getResidentByLocalId(oldLocalId), null);
    assert.equal(JSON.parse(await h.storage.getDatasetAssignment()).purok.id, 1);
    assert.equal(h.render().bootstrapCompleted, false);
    finishDownload();
    await until(() => h.render().bootstrapCompleted);
    assert.deepEqual((await h.storage.getResidents()).map(r => r.server_id), [nextPurok]);
    assert.equal(JSON.parse(await h.storage.getDatasetAssignment()).barangay.id, nextBarangay);
    assert.equal(JSON.parse(await h.storage.getDatasetAssignment()).purok.id, nextPurok);
    assert.equal(await h.storage.getResidentByLocalId(oldLocalId), null);
  });
}

for (const kind of ['new request', 'official correction', 'visit/photo']) {
  test(`verified reassignment quarantines ${kind}, blocks upload/replacement and preserves original context`, async t => {
    const h = await fixture(t, { mobileVerify: async () => ({ valid: true, user: ids(2) }) }, async storage => {
      const official = (await storage.getResidents())[0];
      if (kind === 'new request') await storage.saveResident({ ...official, local_id: undefined, server_id: null, first_name: 'Request' }, 1);
      if (kind === 'official correction') await storage.saveResident({ ...official, first_name: 'Correction' }, 1);
      if (kind === 'visit/photo') await storage.saveVisit({ household_server_id: 1, visited_at: '2026-10-06',
        photos: [{ uri: 'file:///saved/photo.jpg', base64: 'cGhvdG8=', file_name: 'photo.jpg', mime_type: 'image/jpeg' }] }, 1);
      await storage.storeToken('token-1');
    });
    const before = await h.db.getAllAsync('SELECT * FROM residents');
    const photos = await h.db.getAllAsync('SELECT * FROM field_visits');
    await until(async () => await h.storage.getAppState('resident_workspace_blocked') === 'assignment');
    await settle();
    await h.render().syncNow();
    await h.render().retryInitialSync();
    assert.equal(h.render().bootstrapCompleted, false);
    assert.equal(h.render().statusMessage, 'assignmentChangedWorkProtected');
    assert.equal(await h.storage.getCurrentOfficialResidentCount(), 0);
    assert.equal(JSON.parse(await h.storage.getDatasetAssignment()).purok.id, 1);
    assert.equal((await h.storage.getPendingChangeSummary()).total, 1);
    assert.deepEqual(await h.db.getAllAsync('SELECT * FROM residents'), before);
    assert.deepEqual(await h.db.getAllAsync('SELECT * FROM field_visits'), photos);
    assert.equal(h.calls.some(c => ['mobileSync', 'mobileBootstrap'].includes(c.name)), false);
    await h.storage.initializeStorage();
    assert.equal(await h.storage.hasBootstrapData(), false, 'restart/migration cannot reopen quarantined scope');
  });
}

test('new login assignment is compared before any old cache reuse', async t => {
  const h = await fixture(t, { mobileLogin: async () => ({ token: 'token-1', user: ids(2) }),
    mobileVerify: async () => ({ valid: true, user: ids(2) }), mobileBootstrap: async () => data(2) });
  await h.render().signIn({ email: '1', password: 'test' });
  await settle();
  assert.equal(h.render().assignment.purok.id, 2);
  assert.deepEqual((await h.storage.getResidents()).map(r => r.first_name), ['Purok2']);
  assert.equal(h.calls.filter(c => c.name === 'mobileBootstrap').length, 1);
});

test('failed reassignment bootstrap leaves original rows but no authorized old workspace', async t => {
  const h = await fixture(t, { mobileVerify: async () => ({ valid: true, user: ids(2) }),
    mobileBootstrap: async () => { throw new Error('Download unavailable'); } }, async storage => { await storage.storeToken('token-1'); });
  await until(() => h.calls.some(c => c.name === 'mobileBootstrap'));
  await settle();
  assert.equal(h.render().bootstrapCompleted, false);
  assert.equal(await h.storage.getCurrentOfficialResidentCount(), 0);
  assert.equal(JSON.parse(await h.storage.getDatasetAssignment()).purok.id, 1);
  assert.equal((await h.db.getAllAsync('SELECT * FROM residents')).length, 1);
});

test('reassignment during an editor does not replace storage and stale editor save fails closed', async t => {
  let closeEditor;
  let original;
  const h = await fixture(t, { mobileVerify: async () => ({ valid: true, user: ids(2) }) }, async storage => {
    original = (await storage.getResidents())[0];
    await storage.storeToken('token-1');
  });
  // The verification effect is not awaited by render; register before it can replace data.
  closeEditor = h.guard.registerLocalEditor();
  await until(async () => await h.storage.getAppState('resident_workspace_blocked') === 'assignment');
  await settle();
  assert.equal(h.calls.some(c => c.name === 'mobileBootstrap'), false);
  assert.equal(h.render().bootstrapCompleted, false);
  await assert.rejects(h.storage.saveResident({ ...original, first_name: 'Stale' }, 1), { name: 'AssignmentChangedError' });
  closeEditor();
  assert.equal((await h.db.getFirstAsync('SELECT * FROM residents')).first_name, 'Purok1');
});

test('old broad cache requires authoritative refresh without in-place lifecycle guessing', async t => {
  const h = await fixture(t, {}, async storage => {
    await storage.setAppState('resident_contract_version', '');
    await storage.storeToken('token-1');
  });
  await until(() => h.calls.some(c => c.name === 'mobileBootstrap'));
  await until(() => h.render().bootstrapCompleted);
  assert.equal(await h.storage.getCurrentOfficialResidentCount(), 1);
  assert.equal(await h.storage.getAppState('resident_contract_version'), '2');
});

test('profile contract upgrade preserves queued work and the existing manual Retry uploads it before refresh', async t => {
  let sent;
  const h = await fixture(t, {
    mobileSync: async (_, __, payload) => {
      sent = payload.residents[0];
      return { status: 'success', synced_at: '2026-10-06', failed_records: [], resolved_records: {
        households: [], residents: [{ id: null, mobile_uuid: sent.mobile_uuid, verification_status: 'submitted' }],
        field_visits: [], risk_assessments: [],
      } };
    },
    mobileBootstrap: async () => {
      const fresh = data();
      if (sent) fresh.residents.push({ ...sent, id: null, household_id: 1, verification_status: 'submitted' });
      return fresh;
    },
  }, async storage => {
    const official = (await storage.getResidents())[0];
    await storage.saveResident({ ...official, local_id: undefined, server_id: null, first_name: 'Legacy queued' }, 1);
    await storage.setAppState('resident_contract_version', '1');
    await storage.storeToken('token-1');
  });
  h.render();
  await settle();
  assert.equal(h.render().bootstrapCompleted, false);
  assert.equal(h.calls.some(c => c.name === 'mobileSync'), false);
  assert.equal((await h.storage.getPendingChangeSummary()).total, 1);
  const before = await h.db.getFirstAsync("SELECT local_id, mobile_uuid, local_revision FROM residents WHERE server_id IS NULL");
  await h.render().retryInitialSync();
  await settle();
  assert.equal(h.calls.filter(c => c.name === 'mobileSync').length, 1);
  assert.equal(sent.first_name, 'Legacy queued');
  assert.equal(sent.mobile_uuid, before.mobile_uuid);
  assert.equal(sent.local_revision, before.local_revision);
  assert.equal(h.render().bootstrapCompleted, true);
  assert.equal(await h.storage.getAppState('resident_contract_version'), '2');
  assert.equal((await h.storage.getPendingChangeSummary()).total, 0);
  const request = (await h.storage.getResidentRequests())[0];
  assert.equal(request.local_id, before.local_id);
  assert.equal(request.mobile_uuid, before.mobile_uuid);
  assert.equal(request.verification_status, 'submitted');
  assert.equal(request.server_id, null);
});

test('restart with persisted verified mismatch stays blocked and does not claim old scope ready', async t => {
  const h = await fixture(t, { mobileVerify: async () => ({ valid: true, user: ids(2) }) }, async storage => {
    await storage.saveResident({ ...(await storage.getResidents())[0], first_name: 'Pending' }, 1);
    await storage.verifyDatasetAssignment(1, 1, 2);
    await storage.storeToken('token-1');
  });
  assert.equal(h.render().bootstrapCompleted, false);
  assert.equal(await h.storage.getCurrentOfficialResidentCount(), 0);
  assert.equal((await h.storage.getPendingChangeSummary()).total, 1);
});

test('late bootstrap for a superseded assignment cannot commit rows or assignment metadata', async t => {
  let finishOld;
  const h = await fixture(t, { mobileVerify: async () => ({ valid: true, user: ids(2) }),
    mobileBootstrap: () => new Promise(resolve => { finishOld = () => resolve(data(2)); }) }, async storage => { await storage.storeToken('token-1'); });
  await until(() => Boolean(finishOld));
  await h.storage.verifyDatasetAssignment(1, 1, 3);
  finishOld();
  await settle();
  assert.equal(h.render().bootstrapCompleted, false);
  assert.equal(await h.storage.getCurrentOfficialResidentCount(), 0);
  assert.equal(JSON.parse(await h.storage.getDatasetAssignment()).purok.id, 1);
  assert.deepEqual((await h.db.getAllAsync('SELECT server_id FROM residents')).map(r => r.server_id), [1]);
});

test('older successful verification cannot restore a superseded assignment', async t => {
  let finishOld;
  let verifications = 0;
  const h = await fixture(t, {
    mobileVerify: async () => ++verifications === 1 ? new Promise(resolve => {
      finishOld = () => resolve({ valid: true, user: ids() });
    }) : { valid: true, user: ids(2) },
    mobileBootstrap: async () => data(2),
  }, async storage => { await storage.storeToken('token-1'); });
  await until(() => Boolean(finishOld));
  await h.render().syncNow();
  finishOld();
  await settle();
  assert.equal(h.render().bootstrapCompleted, true);
  assert.equal(JSON.parse(await h.storage.getDatasetAssignment()).purok.id, 2);
  assert.equal(await h.storage.getAppState('verified_assignment_signature'), '1:1:2');
  assert.deepEqual((await h.storage.getResidents()).map(r => r.server_id), [2]);
});

test('incompatible same-assignment cache with pending correction can recover only by explicit manual retry', async t => {
  const h = await fixture(t, { mobileSync: async (_url, _token, upload) => ({ status: 'success',
    synced_at: '2026-10-06', failed_records: [], resolved_records: { households: [], residents: [{ id: 1,
      mobile_uuid: upload.residents[0].mobile_uuid, verification_status: 'submitted' }], field_visits: [], risk_assessments: [] } }) },
  async storage => {
    await storage.saveResident({ ...(await storage.getResidents())[0], first_name: 'Pending' }, 1);
    await storage.setAppState('resident_contract_version', '');
    await storage.storeToken('token-1');
  });
  await settle();
  assert.equal(h.render().bootstrapCompleted, false);
  assert.equal(h.calls.some(c => c.name === 'mobileSync'), false);
  assert.equal((await h.storage.getPendingChangeSummary()).total, 1);
  await h.render().retryInitialSync();
  assert.equal(h.render().bootstrapCompleted, true);
  assert.equal((await h.storage.getPendingChangeSummary()).total, 0);
  assert.equal(h.calls.filter(c => c.name === 'mobileSync').length, 1);
  assert.equal(await h.storage.getCurrentOfficialResidentCount(), 1);
});
