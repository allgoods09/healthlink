import assert from 'node:assert/strict';
import { test } from 'node:test';
import { storageHarness } from './storageHarness.mjs';

const choices = { employment_status: ['Employed', 'Unemployed', 'N/A'],
  highest_education_level: ['None', 'Elementary', 'High School', 'College', 'Post Grad', 'Vocational'], education_status: ['Graduate', 'Undergraduate', 'N/A'] };
const profile = { occupation: 'Farmer', employment_status: 'Employed', highest_education_level: 'College', education_status: 'Undergraduate',
  is_pwd: true, disability_type: 'Recorded disability', is_ofw: true, is_solo_parent: true, is_osy: true, is_osc: true, is_ip: true, ethnicity: 'Recorded ethnicity' };
const resident = { id: 1, household_id: 1, philsys_card_no: 'SYNTHETIC-123', first_name: 'Ana', last_name: 'Pilot',
  birth_date: '1990-01-01', birth_place: 'Tubigon', sex: 'Female', civil_status: 'Single', citizenship: 'Filipino',
  relationship_to_head: 'Daughter', resident_status: 'active', is_active: false, ...profile };
const payload = () => ({ resident_contract_version: 2, user: { id: 1 }, assignment: { barangay: { id: 1 }, purok: { id: 1 } },
  resident_profile_choices: choices, server_time: '2026-10-06', households: [{ id: 1, purok_id: 1, household_no: '2', household_address: 'Synthetic home', is_active: false,
    is_vacant: true, current_head_name: null }], residents: [resident], field_visits: [], risk_assessments: [] });
async function fixture(t, data = payload()) {
  const h = await storageHarness(); t.after(h.close); await h.storage.prepareDatasetForUser(1); await h.storage.replaceBootstrapData(data); return h;
}

test('complete profile bootstrap populates SQLite/readers and coherent official snapshot without inventing absent profile values', async t => {
  const h = await fixture(t); const row = await h.storage.getResidentByLocalId(1);
  for (const [field, value] of Object.entries(profile)) { assert.equal(row[field], value); assert.equal(row.official_snapshot[field], value); }
  assert.equal(row.philsys_card_no, 'SYNTHETIC-123'); assert.deepEqual(await h.storage.getResidentProfileChoices(), choices);
  const noProfile = payload(); noProfile.residents = [{ ...resident, ...Object.fromEntries(Object.keys(profile).map(f => [f, null])) }];
  await h.storage.replaceBootstrapData(noProfile);
  for (const field of Object.keys(profile)) assert.equal((await h.storage.getResidentByLocalId(1))[field], null);
});

test('new complete request retains exact optional PhilSys and every socio field through SQLite/upload and authoritative approval refresh', async t => {
  const h = await fixture(t); const values = { ...resident, server_id: null, household_server_id: 1, is_active: true };
  const id = await h.storage.saveResident(values, 1); const request = await h.storage.getResidentRequestByLocalId(id);
  const upload = await h.storage.getPendingSyncPayload(1); const sent = upload.payload.residents[0];
  assert.equal(sent.philsys_card_no, values.philsys_card_no);
  for (const [field, value] of Object.entries(profile)) { assert.equal(request[field], value); assert.equal(sent[field], value); }
  await h.storage.applyResolvedRecords({ households: [], field_visits: [], risk_assessments: [], residents: [{ id: null, mobile_uuid: sent.mobile_uuid, verification_status: 'submitted' }] }, upload.snapshot);
  const approved = payload(); approved.residents.push({ ...resident, id: 2, mobile_uuid: sent.mobile_uuid });
  await h.storage.replaceBootstrapData(approved);
  const official = await h.storage.getResidentByLocalId(id); assert.equal(official.server_id, 2);
  for (const [field, value] of Object.entries(profile)) assert.equal(official[field], value);
  assert.equal((await h.storage.getResidentRequests()).length, 0);
});

test('nullable PhilSys/text correction clears are explicit and untouched socioeconomic fields never enter changes', async t => {
  const h = await fixture(t); const row = await h.storage.getResidentByLocalId(1);
  await h.storage.saveResident({ ...row, philsys_card_no: null, occupation: null, ethnicity: null }, 1);
  const sent = (await h.storage.getPendingSyncPayload(1)).payload.residents[0];
  assert.deepEqual(sent.proposed_changes, { philsys_card_no: null, occupation: null, ethnicity: null });
  for (const [field, value] of Object.entries(profile)) assert.equal(sent.base_snapshot[field], value);
  const stored = await h.storage.getResidentByLocalId(1); assert.equal(stored.highest_education_level, 'College'); assert.equal(stored.is_pwd, true);
});

test('PhilSys add/change and education-only corrections are precise; PWD yes to no clears its type intentionally', async t => {
  const h = await fixture(t); const row = await h.storage.getResidentByLocalId(1);
  await h.storage.saveResident({ ...row, philsys_card_no: 'CHANGED', education_status: 'Graduate' }, 1);
  assert.deepEqual((await h.storage.getPendingSyncPayload(1)).payload.residents[0].proposed_changes,
    { philsys_card_no: 'CHANGED', education_status: 'Graduate' });
  await h.storage.saveResident({ ...(await h.storage.getResidentByLocalId(1)), is_pwd: false }, 1);
  const sent = (await h.storage.getPendingSyncPayload(1)).payload.residents[0];
  assert.equal(sent.proposed_changes.is_pwd, false); assert.equal(sent.proposed_changes.disability_type, null);
  assert.equal((await h.storage.getResidentByLocalId(1)).disability_type, null);
});

test('old contract/additive SQLite upgrade keeps queued IDs, revisions and legacy links but blocks old official profile edits', async t => {
  const h = await fixture(t);
  await h.storage.saveResident({ ...resident, server_id: null, household_server_id: 1, is_active: true, philsys_card_no: null }, 1);
  const before = await h.db.getAllAsync('SELECT local_id, server_id, mobile_uuid, local_revision, sync_status, household_server_id FROM residents');
  for (const field of Object.keys(profile)) await h.db.execAsync(`ALTER TABLE residents DROP COLUMN ${field}`);
  await h.storage.setAppState('resident_contract_version', '1');
  await h.storage.initializeStorage(); await h.storage.initializeStorage();
  assert.deepEqual(await h.db.getAllAsync('SELECT local_id, server_id, mobile_uuid, local_revision, sync_status, household_server_id FROM residents'), before);
  assert.equal(await h.storage.hasBootstrapData(), false); assert.equal(await h.storage.getResidentByLocalId(1), null);
  const queued = (await h.storage.getResidentRequests())[0]; assert.ok(queued);
  for (const field of Object.keys(profile)) assert.equal(queued[field], null);
  await h.storage.saveResident({ ...queued, first_name: 'Still editable' }, 1);
  assert.equal((await h.storage.getPendingSyncPayload(1)).payload.residents[0].first_name, 'Still editable');
  const snap = await h.storage.getPendingSyncPayload(1);
  await h.storage.applyResolvedRecords({ households: [], field_visits: [], risk_assessments: [], residents: [{ id: null, mobile_uuid: queued.mobile_uuid, verification_status: 'submitted' }] }, snap.snapshot);
  await h.storage.replaceBootstrapData(payload());
  assert.equal(await h.storage.hasBootstrapData(), true); assert.equal((await h.storage.getResidentByLocalId(1)).occupation, 'Farmer');
});

test('old explicit base cannot turn missing profile fields into clears; unsafe profile edits require refresh', async t => {
  const h = await fixture(t); const row = await h.storage.getResidentByLocalId(1);
  const oldBase = row.official_snapshot; for (const field of Object.keys(profile)) delete oldBase[field];
  await h.db.runAsync('UPDATE residents SET official_snapshot_json = ? WHERE local_id = 1', [JSON.stringify(oldBase)]);
  await assert.rejects(h.storage.saveResident({ ...row, occupation: 'Vendor' }, 1), /refresh/);
  await h.storage.saveResident({ ...row, first_name: 'Updated only' }, 1);
  assert.deepEqual((await h.storage.getPendingSyncPayload(1)).payload.residents[0].proposed_changes, { first_name: 'Updated only' });
});

test('new destinations are official and assigned only; headless/vacant official household is allowed and zero options are truthful', async t => {
  const h = await fixture(t); assert.equal((await h.storage.getResidentHouseholdOptions('new')).length, 1);
  await h.storage.saveResident({ ...resident, server_id: null, household_server_id: 1, philsys_card_no: null, is_active: true }, 1);
  const pending = await h.storage.saveHousehold({ purok_id: 1, household_no: '3', household_address: 'Pending', is_active: true, is_social_aid_beneficiary: false }, 1);
  const home = (await h.storage.getHouseholds()).find(x => x.local_id === pending);
  await assert.rejects(h.storage.saveResident({ ...resident, household_server_id: null, household_mobile_uuid: home.mobile_uuid, is_active: true }, 1), /eligible household/);
  await h.db.execAsync('DELETE FROM households WHERE server_id IS NOT NULL');
  assert.deepEqual(await h.storage.getResidentHouseholdOptions('new'), []);
});

test('step validation is local to its step and respects lengths, newborn-today DOB and canonical choices', async t => {
  const h = await fixture(t); const form = { ...resident, civil_status: '' };
  assert.equal(h.workflow.validateResidentStep(form, 0, choices), null);
  assert.ok(h.workflow.validateResidentStep(form, 1, choices));
  assert.equal(h.workflow.validateResidentStep({ ...resident, occupation: 'x'.repeat(151) }, 0, choices), null);
  assert.ok(h.workflow.validateResidentStep({ ...resident, occupation: 'x'.repeat(151) }, 2, choices));
  assert.ok(h.workflow.validateResidentStep({ ...resident, employment_status: 'Self-employed' }, 2, choices));
  const now = new Date(); const today = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
  assert.equal(h.workflow.validateResidentStep({ ...resident, birth_date: today }, 0, choices), null);
});
