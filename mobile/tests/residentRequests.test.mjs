import assert from 'node:assert/strict';
import { test } from 'node:test';
import { storageHarness } from './storageHarness.mjs';

const uuid = n => `10000000-0000-4000-8000-${String(n).padStart(12, '0')}`;
const household = (id, extra = {}) => ({ id, household_no: String(id), purok_id: 1,
  household_address: 'Pilot street', is_active: true, is_social_aid_beneficiary: false, ...extra });
const resident = (extra = {}) => ({ id: 1, household_id: 1, mobile_uuid: uuid(1),
  first_name: 'Ana', last_name: 'Santos', middle_name: 'Cosare', suffix: 'II',
  birth_date: '1990-01-01', birth_place: 'Tubigon', sex: 'Female', civil_status: 'Single',
  citizenship: 'Filipino', religion: 'Old religion', contact_number: '09123456789', email_address: 'old@example.test',
  relationship_to_head: 'Daughter', philsys_card_no: 'UNSEEN-123', is_active: false, resident_status: 'active', ...extra });
const bootstrap = (extra = {}) => ({ resident_contract_version: 1, user: { id: 1 },
  assignment: { barangay: { id: 1 }, purok: { id: 1 } }, server_time: '2026-10-06',
  resident_relationship_choices: ['Daughter', 'Son', 'Spouse / Partner'],
  households: [household(1)], residents: [resident()], field_visits: [], risk_assessments: [], ...extra });
async function fixture(t, payload = bootstrap()) {
  const h = await storageHarness();
  t.after(h.close);
  await h.storage.prepareDatasetForUser(1);
  await h.storage.replaceBootstrapData(payload);
  return h;
}
const emptyResolved = () => ({ households: [], residents: [], field_visits: [], risk_assessments: [] });

test('official correction serializes only changed fields with explicit nulls and preserves unseen values', async t => {
  const { storage, db } = await fixture(t);
  const original = await storage.getResidentByLocalId(1);
  const input = { ...original, middle_name: null, suffix: null, religion: null, contact_number: null, email_address: null };
  delete input.philsys_card_no;
  await storage.saveResident(input, 1);
  const next = await storage.getPendingSyncPayload(1);
  const sent = JSON.parse(JSON.stringify(next.payload)).residents[0];
  assert.deepEqual(sent.proposed_changes, { middle_name: null, suffix: null, religion: null, contact_number: null, email_address: null });
  assert.equal(sent.base_snapshot.philsys_card_no, 'UNSEEN-123');
  assert.equal(sent.request_contract_version, 2);
  assert.equal('philsys_card_no' in sent, false);
  const stored = await db.getFirstAsync('SELECT * FROM residents');
  assert.equal(stored.philsys_card_no, 'UNSEEN-123');
  assert.equal(stored.is_active, 0);
  assert.equal(stored.resident_status, 'active');
});

test('same unsent correction is revised in place against its original base and C3 retains newer work', async t => {
  const { storage } = await fixture(t);
  const original = await storage.getResidentByLocalId(1);
  await storage.saveResident({ ...original, first_name: 'First' }, 1);
  const upload = await storage.getPendingSyncPayload(1);
  await storage.saveResident({ ...original, first_name: 'Second' }, 1);
  await storage.applyResolvedRecords({ ...emptyResolved(), residents: [{ id: 1, mobile_uuid: uuid(1), verification_status: 'submitted' }] }, upload.snapshot);
  const next = await storage.getPendingSyncPayload(1);
  assert.equal(next.payload.residents.length, 1);
  assert.deepEqual(next.payload.residents[0].proposed_changes, { first_name: 'Second' });
  assert.equal(next.payload.residents[0].base_snapshot.first_name, 'Ana');
  assert.ok(next.payload.residents[0].local_revision > upload.payload.residents[0].local_revision);
  assert.equal((await storage.getPendingChangeSummary()).residents, 1);
});

test('submitted correction blocks a second edit but leaves the official resident readable', async t => {
  const { storage } = await fixture(t, bootstrap({ residents: [resident({ verification_status: 'submitted' })] }));
  const original = await storage.getResidentByLocalId(1);
  assert.ok(original);
  await assert.rejects(storage.saveResident({ ...original, first_name: 'Second' }, 1), /under review/);
  assert.equal((await storage.getPendingChangeSummary()).residents, 0);
});

test('rejected request remains nonofficial/read-only with reason and does not affect current count', async t => {
  const { storage } = await fixture(t, bootstrap({ residents: [resident({ id: null, verification_status: 'rejected', verification_notes: 'Confirm spelling.' })] }));
  assert.equal(await storage.getCurrentOfficialResidentCount(), 0);
  const request = (await storage.getResidentRequests())[0];
  assert.equal(request.verification_notes, 'Confirm spelling.');
  await assert.rejects(storage.saveResident({ ...request, first_name: 'Changed' }, 1), /history is preserved/);
  assert.equal((await storage.getResidentRequests())[0].first_name, 'Ana');
});

test('household options are naturally sorted, mode-safe, scoped and searchable by head and address', async t => {
  const { storage, db } = await fixture(t, bootstrap({ households: [
    household(10), household(2, { current_head_name: 'Elena Santos', is_vacant: false }),
    household(3, { household_no: 'A10' }), household(4, { household_no: 'A2' }),
    household(5, { household_no: '2', is_vacant: true }), household(6, { purok_id: 2 }),
    household(null, { mobile_uuid: uuid(20), household_no: '20', verification_status: 'submitted' }),
    household(null, { mobile_uuid: uuid(21), household_no: '21', verification_status: 'rejected' }),
  ] }));
  const options = await storage.getResidentHouseholdOptions('new');
  assert.deepEqual(options.map(h => h.household_no), ['2', '2', '10', '20', 'A2', 'A10']);
  assert.ok(options[0].local_id < options[1].local_id);
  assert.equal((await storage.getResidentHouseholdOptions('correction')).length, 5);
  assert.deepEqual((await storage.getResidentHouseholdOptions('new', '  ELENA   SANTOS  ')).map(h => h.server_id), [2]);
  assert.equal((await storage.getResidentHouseholdOptions('new', 'pilot street')).length, 6);
  assert.deepEqual(await storage.getResidentHouseholdOptions('new', 'no matches'), []);
  await db.runAsync('DELETE FROM households WHERE server_id = ?', [2]);
  assert.deepEqual(await storage.getResidentHouseholdOptions('new', 'Elena'), []);
});

test('pending household handoff returns a stable local ID usable only for new resident requests', async t => {
  const { storage } = await fixture(t, bootstrap({ residents: [] }));
  const id = await storage.saveHousehold({ household_no: '11', household_address: 'New home', purok_id: 1,
    is_active: true, is_social_aid_beneficiary: false }, 1);
  const created = (await storage.getResidentHouseholdOptions('new')).find(h => h.local_id === id);
  assert.ok(created.mobile_uuid);
  assert.equal((await storage.getResidentHouseholdOptions('correction')).some(h => h.local_id === id), false);
  const values = resident({ id: undefined, server_id: null, household_server_id: null,
    household_mobile_uuid: created.mobile_uuid, is_active: true, propose_household_head: true });
  await storage.saveResident(values, 1);
  const request = (await storage.getResidentRequests())[0];
  assert.equal(request.household_mobile_uuid, created.mobile_uuid);
  assert.equal(request.propose_household_head, true);
  assert.equal(request.verification_status, 'pending');
  await storage.saveResident({ ...request, propose_household_head: false }, 1);
  const upload = await storage.getPendingSyncPayload(1);
  assert.equal(upload.payload.residents.length, 1);
  assert.equal(upload.payload.residents[0].propose_household_head, false);
  assert.equal(await storage.getCurrentOfficialResidentCount(), 0);
});

test('correction cannot use a pending household; new request cannot use rejected or foreign household', async t => {
  const { storage } = await fixture(t, bootstrap({ households: [household(1),
    household(null, { mobile_uuid: uuid(20), verification_status: 'submitted' }),
    household(null, { mobile_uuid: uuid(21), verification_status: 'rejected' }), household(2, { purok_id: 2 })] }));
  const original = await storage.getResidentByLocalId(1);
  await assert.rejects(storage.saveResident({ ...original, household_server_id: null, household_mobile_uuid: uuid(20) }, 1), /eligible household/);
  await assert.rejects(storage.saveResident({ ...original, household_server_id: 2 }, 1));
  const values = resident({ server_id: null, household_server_id: null, household_mobile_uuid: uuid(21), is_active: true });
  await assert.rejects(storage.saveResident(values, 1), /eligible household/);
  await assert.rejects(storage.saveResident({ ...values, household_server_id: 1, propose_household_head: true }, 1), /only be proposed/);
});

test('storage failure rolls back the edit completely and permits a safe retry', async t => {
  const h = await fixture(t);
  const original = await h.storage.getResidentByLocalId(1);
  h.intercept(async sql => { if (sql.startsWith('UPDATE residents SET changed_fields_json')) throw new Error('Simulated write failure'); });
  await assert.rejects(h.storage.saveResident({ ...original, first_name: 'Changed' }, 1), /Simulated/);
  assert.equal((await h.storage.getResidentByLocalId(1)).first_name, 'Ana');
  assert.equal((await h.storage.getPendingChangeSummary()).residents, 0);
  h.intercept(async () => {});
  await h.storage.saveResident({ ...original, first_name: 'Changed' }, 1);
  assert.equal((await h.storage.getPendingSyncPayload(1)).payload.residents.length, 1);
});

test('new request upload failure preserves identity/revision/data until an exact acknowledgment', async t => {
  const { storage } = await fixture(t, bootstrap({ residents: [] }));
  await storage.saveResident(resident({ server_id: null, household_server_id: 1, is_active: true }), 1);
  const before = await storage.getPendingSyncPayload(1);
  await storage.applyResolvedRecords(emptyResolved(), before.snapshot);
  const after = await storage.getPendingSyncPayload(1);
  assert.deepEqual(after.payload, before.payload);
  await storage.applyResolvedRecords({ ...emptyResolved(), residents: [{ id: null, mobile_uuid: uuid(1), verification_status: 'submitted' }] }, after.snapshot);
  assert.equal((await storage.getResidentRequests())[0].verification_status, 'submitted');
  assert.equal((await storage.getResidentRequests())[0].server_id, null);
});

test('approved refresh reconciles a local request to exactly one official resident and keeps identity', async t => {
  const { storage } = await fixture(t, bootstrap({ residents: [] }));
  const localId = await storage.saveResident(resident({ server_id: null, household_server_id: 1, is_active: true }), 1);
  const upload = await storage.getPendingSyncPayload(1);
  await storage.applyResolvedRecords({ ...emptyResolved(), residents: [{ id: 1, mobile_uuid: uuid(1), verification_status: 'approved' }] }, upload.snapshot);
  await storage.replaceBootstrapData(bootstrap({ residents: [resident({ verification_status: 'approved', first_name: 'Secretary final' })] }));
  assert.equal((await storage.getCurrentOfficialResidentsPage()).total, 1);
  assert.equal((await storage.getResidentByLocalId(localId)).first_name, 'Secretary final');
  assert.equal((await storage.getResidentRequests()).length, 0);
});

test('form modes, minimal status labels and save guard do not confuse upload with approval', async t => {
  const { workflow } = await fixture(t);
  assert.equal(workflow.residentFormMode(), 'new');
  assert.equal(workflow.residentFormMode({ server_id: 1 }), 'correction');
  assert.equal(workflow.residentFormMode({ server_id: null }), 'localRequest');
  assert.equal(workflow.residentRequestLabel({ sync_status: 'pending_create' }), 'Awaiting upload');
  assert.equal(workflow.residentRequestLabel({ sync_status: 'synced', verification_status: 'submitted' }), 'Submitted for verification');
  assert.equal(workflow.residentRequestLabel({ sync_status: 'synced', verification_status: 'rejected' }), 'Not approved');
  const guard = workflow.createSaveGuard();
  assert.equal(guard.acquire(), true); assert.equal(guard.acquire(), false);
  guard.release(); assert.equal(guard.acquire(), true); guard.release();
});

test('resident validation accepts today/leap dates, rejects future/invalid dates and unconfirmed required defaults', async t => {
  const { workflow, storage } = await fixture(t);
  const today = new Date(2026, 9, 6);
  const values = resident();
  assert.equal(workflow.validateResidentInput({ ...values, birth_date: '2026-10-06' }, today), null);
  assert.equal(workflow.validateResidentInput({ ...values, birth_date: '2024-02-29' }, today), null);
  for (const invalid of [{ birth_date: '2026-10-07' }, { birth_date: '2025-02-29' }, { birth_date: '2024-02-30' },
    { first_name: '  ' }, { last_name: '' }, { birth_place: '' }, { sex: '' }, { civil_status: '' },
    { citizenship: '' }, { relationship_to_head: '' }, { email_address: 'bad email' }, { contact_number: '1'.repeat(21) }]) {
    assert.ok(workflow.validateResidentInput({ ...values, ...invalid }, today));
  }
  assert.deepEqual(await storage.getResidentRelationshipChoices(), ['Daughter', 'Son', 'Spouse / Partner']);
});

test('editing an older never-uploaded local draft preserves its legacy contract and relationship without inferring head', async t => {
  const { storage, db } = await fixture(t, bootstrap({ residents: [] }));
  const localId = await storage.saveResident(resident({ server_id: null, household_server_id: 1, is_active: true,
    relationship_to_head: 'Head' }), 1);
  // Reproduce a pre-Part-2 queue row after additive initialization.
  await db.runAsync("UPDATE residents SET changed_fields_json = NULL, verification_status = 'approved' WHERE local_id = ?", [localId]);
  const original = await storage.getResidentRequestByLocalId(localId);
  await storage.saveResident({ ...original, first_name: 'Corrected Ana' }, 1);
  const queued = (await storage.getPendingSyncPayload(1)).payload.residents[0];
  assert.equal(queued.request_contract_version, undefined);
  assert.equal(queued.relationship_to_head, 'Head');
  assert.equal(queued.propose_household_head, false);
  assert.equal(queued.first_name, 'Corrected Ana');
  assert.equal((await storage.getResidentRequests()).length, 1);
  assert.equal(await storage.getCurrentOfficialResidentCount(), 0);
});
