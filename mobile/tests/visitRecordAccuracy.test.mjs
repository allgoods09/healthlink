import assert from 'node:assert/strict';
import { test } from 'node:test';
import { householdDownload, householdUiHarness } from './householdUiHarness.mjs';

const uuid = n => `40000000-0000-4000-8000-${String(n).padStart(12, '0')}`;
const proposed = 'PROPOSED-10';
const proposedAddress = 'Unapproved address';
const creator = 'Jose Maria Ybañez';
const photo = { uri: 'file:///offline.jpg', base64: 'cGhvdG8=', file_name: 'offline.jpg', mime_type: 'image/jpeg' };
const resolved = (households = [], visits = []) => ({ households, residents: [], field_visits: visits, risk_assessments: [] });
function download(number = '0002-A') {
  const data = householdDownload();
  data.households[0].household_no = number;
  data.households[0].base_snapshot.household_no = number;
  data.households[0].mobile_uuid = uuid(1);
  data.households[0].current_member_count = 0;
  data.households[0].is_vacant = true;
  data.field_visits[0].mobile_uuid = uuid(11);
  data.field_visits[0].household_mobile_uuid = uuid(1);
  data.field_visits[0].visited_at = '2026-10-07T00:00:00.000Z';
  return data;
}
function correction(status) {
  return async ({ storage, db }) => {
    if (status === 'normal') return;
    const home = (await storage.getHouseholds())[0];
    await storage.saveHousehold({ ...home, household_no: proposed, household_address: proposedAddress }, 1);
    if (status !== 'pending') {
      const { snapshot } = await storage.getPendingSyncPayload(1);
      await storage.applyResolvedRecords(resolved([{ id: 1, mobile_uuid: uuid(1), verification_status: status }]), snapshot);
    }
    assert.equal((await db.getFirstAsync('SELECT household_no FROM households WHERE server_id = 1')).household_no, proposed);
  };
}
async function databaseRows(h) {
  return { homes: await h.db.getAllAsync('SELECT * FROM households'), visits: await h.db.getAllAsync('SELECT * FROM field_visits') };
}
async function search(h, label, value) {
  h.input(label).props.onChangeText(value); h.render(); h.flushTimers(); await h.settle();
}
async function historyFor(t, original) {
  return householdUiHarness(t, 'Visits', { storage: {
    getVisits: original.storage.getVisits,
    getWaitingHouseholdVisits: original.storage.getWaitingHouseholdVisits,
    hasHouseholdData: original.storage.hasHouseholdData,
  } });
}

for (const number of ['0002', '0002-A', 'B-02']) {
  for (const status of ['normal', 'pending', 'rejected']) {
    test(`V-04 ${status} ${number}: official history/search/chooser/reopened and direct form agree without mutating correction data`, async t => {
      const payload = download(number);
      const h = await householdUiHarness(t, 'Visits', { payload, setup: correction(status) });
      const before = await databaseRows(h);
      assert.equal(h.cards()[0].props.children[0].props.children[0], number);
      assert.doesNotMatch(h.texts(h.cards()), /PROPOSED-10|Unapproved address/);
      const visits = await h.storage.getVisits(number);
      assert.equal(visits.length, 1); assert.equal(visits[0].household_no, number);
      assert.equal(visits[0].household_server_id, 1); assert.equal(visits[0].household_mobile_uuid, uuid(1));
      assert.equal((await h.storage.getVisits(proposed)).length, 0);
      await search(h, 'hhVisitSearch', number); assert.equal(h.cards().length, 1);
      await search(h, 'hhVisitSearch', proposed); assert.equal(h.cards().length, 0);
      assert.equal(h.input('hhVisitSearch').props.value, proposed);
      await search(h, 'hhVisitSearch', 'Recorded history'); assert.equal(h.cards().length, 1);
      assert.deepEqual(await databaseRows(h), before);

      for (const params of [{ localId: 1 }, { householdLocalId: 1 }]) {
        const form = await householdUiHarness(t, 'VisitForm', { payload, setup: correction(status), params });
        const original = await databaseRows(form);
        assert.ok(form.texts().includes(number)); assert.match(form.texts(), /Pilot home/);
        assert.doesNotMatch(form.texts(), /PROPOSED-10|Unapproved address/);
        assert.equal(form.button('save').props.disabled, false, 'Unavailable/vacant official homes remain eligible');
        await form.button('chooseHousehold').props.onPress(); await form.settle();
        const list = form.nodes().find(n => n.type === 'FlatList');
        assert.equal(list.props.data.length, 1); assert.equal(list.props.data[0].label, number);
        assert.match(list.props.data[0].description, /Pilot home/);
        assert.doesNotMatch(list.props.data[0].description, /Unapproved/);
        const selected = list.props.data[0];
        list.props.renderItem({ item: selected }).props.onPress(); await form.settle();
        assert.ok(form.texts().includes(number));
        await form.button('chooseHousehold').props.onPress(); await form.settle();
        await search(form, 'hhSearch', proposed);
        assert.equal(form.nodes().find(n => n.type === 'FlatList').props.data.length, 0);
        await search(form, 'hhSearch', number);
        assert.equal(form.nodes().find(n => n.type === 'FlatList').props.data[0].label, number);
        assert.deepEqual(await databaseRows(form), original);
      }
    });
  }
}

test('V-04 submitted correction keeps the baseline; authoritative approval refresh updates all Visit context with stable identities', async t => {
  const h = await householdUiHarness(t, 'Visits', { payload: download(), setup: correction('submitted') });
  const before = await databaseRows(h);
  assert.equal((await h.storage.getVisits())[0].household_no, '0002-A');
  assert.equal((await h.storage.getVisitHouseholdOptions())[0].household_no, '0002-A');
  const approved = download('0010-B');
  approved.households[0].household_address = 'Secretary approved address';
  approved.households[0].base_snapshot.household_address = 'Secretary approved address';
  await h.storage.replaceBootstrapData(approved); h.context.dataVersion++; await h.settle();
  assert.equal(h.cards()[0].props.children[0].props.children[0], '0010-B');
  assert.equal((await h.storage.getVisits('0010-B')).length, 1);
  assert.equal((await h.storage.getVisits('0002-A')).length, 0);
  assert.equal((await h.storage.getVisits(proposed)).length, 0);
  const after = await databaseRows(h);
  for (const field of ['local_id', 'server_id', 'mobile_uuid']) assert.equal(after.homes[0][field], before.homes[0][field]);
  for (const field of ['local_id', 'mobile_uuid', 'household_server_id', 'household_mobile_uuid', 'recorded_by_name', 'photos_json']) {
    assert.equal(after.visits[0][field], before.visits[0][field], field);
  }
  const form = await householdUiHarness(t, 'VisitForm', { payload: approved, params: { localId: after.visits[0].local_id } });
  assert.match(form.texts(), /0010-B|Secretary approved address/); assert.doesNotMatch(form.texts(), /0002-A|PROPOSED-10/);
  form.button('chooseHousehold').props.onPress(); await form.settle();
  assert.equal(form.nodes().find(n => n.type === 'FlatList').props.data[0].label, '0010-B');
});

test('V-04 projection preserves other-Purok/Barangay denials and strict server-ID precedence', async t => {
  const payload = download();
  payload.households.push({ ...payload.households[0], id: 3, mobile_uuid: uuid(3), barangay_id: 2, household_no: 'Foreign barangay' });
  payload.field_visits.push({ ...payload.field_visits[0], id: 2, mobile_uuid: uuid(12), household_id: 2, household_mobile_uuid: uuid(2), notes: 'Other purok' },
    { ...payload.field_visits[0], id: 3, mobile_uuid: uuid(13), household_id: 3, household_mobile_uuid: uuid(3), notes: 'Other barangay' });
  const h = await householdUiHarness(t, 'Visits', { payload, setup: async ({ db }) => {
    await db.runAsync('UPDATE field_visits SET household_mobile_uuid = ? WHERE server_id = 1', [uuid(3)]);
  } });
  assert.equal(h.cards().length, 1); assert.equal((await h.storage.getVisits())[0].household_no, '0002-A');
  assert.equal((await h.storage.getVisits())[0].household_server_id, 1);
  assert.equal((await h.storage.getVisitHouseholdOptions()).length, 1);
  assert.equal(await h.storage.getVisitByLocalId(2), null); assert.equal(await h.storage.getVisitByLocalId(3), null);
  for (const householdLocalId of [2, 3]) {
    const form = await householdUiHarness(t, 'VisitForm', { payload, params: { householdLocalId } });
    assert.equal(form.button('save').props.disabled, true); assert.match(form.texts(), /hhVisitUnavailable/);
  }
  const before = await databaseRows(h);
  await assert.rejects(h.storage.saveVisit({ household_server_id: 1, household_mobile_uuid: uuid(3), visited_at: '2026-10-08', photos: [] }, 1), /verified household/);
  assert.deepEqual(await databaseRows(h), before);
});

test('V-05 new Save captures full authenticated creator, survives offline reopen/session/reinitialization/edit, then yields to bootstrap', async t => {
  const h = await householdUiHarness(t, 'VisitForm', { payload: download(), params: { householdLocalId: 1 },
    context: { user: { id: 1, name: `  ${creator}  ` } },
    gallery: async () => ({ canceled: false, assets: [photo] }) });
  h.input('notes').props.onChangeText('Offline field notes'); h.render();
  await h.button('uploadFromGallery').props.onPress(); await h.settle();
  await h.button('save').props.onPress(); await h.settle();
  let saved = (await h.storage.getVisits()).find(v => v.server_id == null);
  assert.ok(saved); assert.equal(saved.recorded_by_name, creator); assert.equal(saved.sync_status, 'pending_create');
  assert.equal(saved.household_server_id, 1); assert.equal(saved.household_mobile_uuid, uuid(1));
  assert.equal(saved.notes, 'Offline field notes'); assert.equal(saved.photos[0].base64, photo.base64);
  const original = await h.db.getFirstAsync('SELECT * FROM field_visits WHERE local_id = ?', [saved.local_id]);
  const history = await historyFor(t, h); assert.match(history.texts(history.cards()), /Jose Maria Ybañez/);
  await h.storage.storeToken('synthetic'); await h.storage.setAppState('session_user', JSON.stringify({ id: 1, name: creator }));
  await h.storage.clearLocalSession(); await h.storage.prepareDatasetForUser(1); await h.storage.initializeStorage();
  assert.deepEqual(await h.db.getFirstAsync('SELECT * FROM field_visits WHERE local_id = ?', [saved.local_id]), original);
  const edit = await householdUiHarness(t, 'VisitForm', { params: { localId: saved.local_id }, context: { user: { id: 1, name: 'Current viewer' } },
    storage: { ...h.storage } });
  assert.equal(edit.input('notes').props.value, 'Offline field notes');
  edit.input('notes').props.onChangeText('Edited notes'); edit.render();
  await edit.button('save').props.onPress(); await edit.settle();
  saved = await h.storage.getVisitByLocalId(saved.local_id);
  assert.equal(saved.recorded_by_name, creator); assert.equal(saved.mobile_uuid, original.mobile_uuid);
  assert.equal(saved.local_id, original.local_id); assert.equal(saved.local_revision, original.local_revision + 1);
  const upload = await h.storage.getPendingSyncPayload(1);
  const pending = upload.payload.field_visits[0];
  for (const field of ['recorded_by_name', 'recorded_by_user_id', 'bhw_name', 'user_id']) assert.equal(Object.hasOwn(pending, field), false);
  assert.equal(pending.notes, 'Edited notes'); assert.equal(pending.photos[0].data, photo.base64);
  await h.storage.applyResolvedRecords(resolved([], [{ id: 20, mobile_uuid: saved.mobile_uuid, household_id: 1 }]), upload.snapshot);
  const acknowledged = await h.storage.getVisitByLocalId(saved.local_id);
  assert.equal(acknowledged.sync_status, 'synced'); assert.equal(acknowledged.recorded_by_name, creator);
  assert.equal(acknowledged.local_revision, saved.local_revision);
  const refreshed = download();
  refreshed.field_visits.push({ id: 20, mobile_uuid: saved.mobile_uuid, household_id: 1, household_mobile_uuid: uuid(1),
    visited_at: saved.visited_at, notes: saved.notes, photos: [{ path: 'visit-photos/authoritative.jpg' }], recorded_by_name: 'Authoritative original BHW' });
  await h.storage.replaceBootstrapData(refreshed);
  const authoritative = await h.storage.getVisitByLocalId(saved.local_id);
  assert.equal(authoritative.recorded_by_name, 'Authoritative original BHW');
  assert.equal(authoritative.local_id, original.local_id); assert.equal(authoritative.mobile_uuid, original.mobile_uuid);
  await h.storage.saveVisit({ ...authoritative, notes: 'Colleague notes', recorded_by_name: 'Different authorized BHW' }, 1);
  assert.equal((await h.storage.getVisitByLocalId(saved.local_id)).recorded_by_name, 'Authoritative original BHW');
});

for (const name of [undefined, '', '   ']) {
  test(`V-05 missing/empty authenticated name ${JSON.stringify(name)} remains honestly unknown`, async t => {
    const h = await householdUiHarness(t, 'VisitForm', { payload: download(), params: { householdLocalId: 1 }, context: { user: { id: 1, name } } });
    await h.button('save').props.onPress(); await h.settle();
    const saved = (await h.storage.getVisits()).find(v => v.server_id == null);
    assert.equal(saved.recorded_by_name, null);
    const history = await historyFor(t, h);
    const pending = history.cards().find(card => history.texts(card).includes('hhVisitPending'));
    assert.match(history.texts(pending), /hhRecorderUnknown/); assert.doesNotMatch(history.texts(pending), /Original BHW/);
  });
}

for (const original of ['Original BHW', null]) {
  test(`V-05 colleague editing historical ${original ?? 'unknown'} recorder cannot overwrite/backfill attribution`, async t => {
    const payload = download(); payload.field_visits[0].recorded_by_name = original;
    const h = await householdUiHarness(t, 'VisitForm', { payload, params: { localId: 1 }, context: { user: { id: 1, name: 'Different authorized BHW' } } });
    const before = await h.storage.getVisitByLocalId(1);
    h.input('notes').props.onChangeText('Allowed colleague edit'); h.render();
    await h.button('save').props.onPress(); await h.settle();
    const saved = await h.storage.getVisitByLocalId(1);
    assert.equal(saved.notes, 'Allowed colleague edit'); assert.equal(saved.recorded_by_name, original);
    assert.equal(saved.local_revision, before.local_revision + 1); assert.equal(saved.mobile_uuid, before.mobile_uuid);
    assert.deepEqual(saved.photos, before.photos);
    const history = await historyFor(t, h);
    assert.match(history.texts(history.cards()), original ? /Original BHW/ : /hhRecorderUnknown/);
    assert.doesNotMatch(history.texts(history.cards()), /Different authorized BHW/);
    await h.storage.saveVisit({ ...saved, recorded_by_name: 'Attempted backfill' }, 1);
    assert.equal((await h.storage.getVisitByLocalId(1)).recorded_by_name, original);
  });
}

for (const change of ['account', 'assignment', 'unmount']) {
  test(`V-05 ${change} during new Save confirmation cannot write unrelated creator or Visit`, async t => {
    let finish;
    const h = await householdUiHarness(t, 'VisitForm', { payload: download(), params: { householdLocalId: 1 }, context: {
      user: { id: 1, name: creator }, requestConfirmation: () => new Promise(resolve => { finish = resolve; }),
    } });
    const before = await databaseRows(h);
    const saving = h.button('save').props.onPress(); await h.settle();
    if (change === 'account') h.context.user = { id: 2, name: 'Unrelated account' };
    if (change === 'assignment') h.context.assignment = { barangay: { id: 1 }, purok: { id: 2 } };
    if (change === 'unmount') h.unmount(); else await h.settle();
    finish(true); await saving;
    assert.deepEqual(await databaseRows(h), before); assert.equal(h.calls.some(c => c.key === 'goBack'), false);
  });
}

test('V-05 queued creation still rechecks dataset ownership/assignment before persisting creator', async t => {
  const h = await householdUiHarness(t, 'VisitForm', { payload: download(), params: { householdLocalId: 1 }, context: { user: { id: 1, name: creator } } });
  const before = await databaseRows(h);
  await h.storage.setAppState('dataset_owner_user_id', '2');
  await h.button('save').props.onPress(); await h.settle();
  assert.deepEqual(await databaseRows(h), before); assert.match(h.texts(), /hhVisitSaveError/);
  assert.equal(h.calls.some(c => c.key === 'goBack'), false);
});
