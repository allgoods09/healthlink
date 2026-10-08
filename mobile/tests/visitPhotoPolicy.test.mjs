import assert from 'node:assert/strict';
import { test } from 'node:test';
import { householdDownload, householdUiHarness } from './householdUiHarness.mjs';
import { createHomeHarness } from './homePresentationHarness.mjs';

const uuid = '30000000-0000-4000-8000-000000000001';
const upload = i => ({ uri: `file:///photo-${i}.jpg`, base64: Buffer.from(`photo ${i}`).toString('base64'),
  file_name: `photo-${i}.jpg`, mime_type: 'image/jpeg' });
const retained = count => Array.from({ length: count }, (_, i) => ({ path: `visit-photos/historical-${i}.jpg` }));
function download(count) {
  const data = householdDownload();
  data.field_visits[0].mobile_uuid = uuid;
  data.field_visits[0].visited_at = '2026-10-07T00:00:00.000Z';
  data.field_visits[0].photos = retained(count);
  return data;
}
const photoCount = h => h.nodes().filter(n => n.type === 'Pressable' && n.props.accessibilityLabel?.startsWith('hhPhotoRemove:')).length;
const row = h => h.db.getFirstAsync('SELECT * FROM field_visits WHERE local_id = ?', [h.route.params.localId]);
async function add(h, source) {
  if (source === 'gallery') await h.button('uploadFromGallery').props.onPress();
  else {
    await h.button('takePhoto').props.onPress(); await h.settle();
    await h.nodes().filter(n => n.type === 'Pressable' && n.props.accessibilityLabel === 'takePhoto')[1].props.onPress();
  }
  await h.settle();
}
async function remove(h, index = 0) {
  await h.button(`hhPhotoRemove:${JSON.stringify({ number: index + 1 })}`).props.onPress();
  await h.settle();
}

for (const source of ['camera', 'gallery']) {
  test(`${source}: zero through five new photos save; sixth is blocked and removal permits capture`, async t => {
    let calls = 0;
    const h = await householdUiHarness(t, 'VisitForm', { params: { householdLocalId: 1 },
      gallery: async () => { calls++; return { canceled: false, assets: [upload(calls)] }; },
      capture: async () => { calls++; return upload(calls); } });
    for (let count = 1; count <= 5; count++) {
      await add(h, source); assert.equal(photoCount(h), count);
    }
    h.input('notes').props.onChangeText('Keep these notes'); h.render();
    await add(h, source); assert.equal(photoCount(h), 5); assert.equal(calls, 5);
    assert.match(h.texts(), /hhPhotoLimit/); assert.equal(h.input('notes').props.value, 'Keep these notes');
    assert.equal((await h.storage.getPendingSyncPayload(1)).payload.field_visits.length, 0);
    await remove(h); await add(h, source); assert.equal(photoCount(h), 5); assert.equal(calls, 6);
    await h.button('save').props.onPress(); await h.settle();
    const { payload } = await h.storage.getPendingSyncPayload(1);
    assert.equal(payload.field_visits[0].photos.length, 5);
    assert.deepEqual(payload.field_visits[0].existing_photos, []);
    assert.equal(payload.field_visits[0].notes, 'Keep these notes');
    assert.equal(h.calls.filter(c => c.key === 'goBack').length, 1);
  });

  test(`${source}: retained and new photos share the limit, including stale repeated callbacks`, async t => {
    let calls = 0;
    const h = await householdUiHarness(t, 'VisitForm', { payload: download(4), params: { localId: 1 },
      gallery: async () => { calls++; return { canceled: false, assets: [upload(calls)] }; },
      capture: async () => { calls++; return upload(calls); } });
    const before = await row(h);
    let callback;
    if (source === 'camera') {
      await h.button('takePhoto').props.onPress(); await h.settle();
      callback = h.nodes().filter(n => n.type === 'Pressable' && n.props.accessibilityLabel === 'takePhoto')[1].props.onPress;
    } else callback = h.button('uploadFromGallery').props.onPress;
    await Promise.all([callback(), callback()]);
    // Reuse the pre-render closure: authoritative count must already be five.
    await callback(); await h.settle();
    assert.equal(calls, 1); assert.equal(photoCount(h), 5); assert.match(h.texts(), /hhPhotoLimit/);
    assert.deepEqual(await row(h), before);
    await h.button('save').props.onPress(); await h.settle();
    const { payload } = await h.storage.getPendingSyncPayload(1);
    assert.equal(payload.field_visits[0].existing_photos.length, 4);
    assert.equal(payload.field_visits[0].photos.length, 1);
  });
}

for (const count of [0, 1, 2, 3, 4, 5]) {
  test(`normal existing Visit saves ${count} retained photos without changing attribution`, async t => {
    const h = await householdUiHarness(t, 'VisitForm', { payload: download(count), params: { localId: 1 } });
    h.input('notes').props.onChangeText('Updated only notes'); h.render();
    await h.button('save').props.onPress(); await h.settle();
    const saved = await h.storage.getVisitByLocalId(1);
    assert.deepEqual(saved.photos, retained(count)); assert.equal(saved.recorded_by_name, 'Original BHW');
    const { payload } = await h.storage.getPendingSyncPayload(1);
    assert.equal(payload.field_visits[0].existing_photos.length, count); assert.equal(payload.field_visits[0].photos.length, 0);
  });
}

for (const target of [10, 8, 5, 4]) {
  test(`historical ten-photo Visit can retain/reduce to ${target} and save; additions require a final count <= five`, async t => {
    let galleryCalls = 0;
    const h = await householdUiHarness(t, 'VisitForm', { payload: download(10), params: { localId: 1 },
      gallery: async () => { galleryCalls++; return { canceled: false, assets: [upload(11)] }; } });
    const before = await row(h);
    assert.equal(photoCount(h), 10); assert.doesNotMatch(h.texts(), /hhPhotoLimitResolve/);
    await add(h, 'gallery'); await add(h, 'camera');
    assert.equal(photoCount(h), 10); assert.equal(galleryCalls, 0); assert.deepEqual(await row(h), before);
    for (let count = 10; count > target; count--) await remove(h);
    if (target === 4) await add(h, 'gallery');
    else { await add(h, 'gallery'); assert.equal(galleryCalls, 0); }
    assert.deepEqual(await row(h), before, 'Removal only edits the form until explicit Save');
    h.input('notes').props.onChangeText('Historical notes edit'); h.render();
    await h.button('save').props.onPress(); await h.settle();
    const after = await row(h);
    assert.equal(after.local_revision, before.local_revision + 1);
    assert.equal(after.mobile_uuid, before.mobile_uuid); assert.equal(after.recorded_by_name, before.recorded_by_name);
    assert.equal(after.visited_at, before.visited_at); assert.equal(after.notes, 'Historical notes edit');
    const { payload } = await h.storage.getPendingSyncPayload(1);
    assert.equal(payload.field_visits[0].existing_photos.length, target);
    assert.equal(payload.field_visits[0].photos.length, target === 4 ? 1 : 0);
  });
}

for (const existing of [false, true]) {
  test(`legacy oversized ${existing ? 'pending update' : 'pending create'} preserves bytes/identity/revision until corrected, then uses exact sync acknowledgment`, async t => {
    const photos = existing ? [...retained(3), ...[1, 2, 3].map(upload)] : [1, 2, 3, 4, 5, 6].map(upload);
    const h = await householdUiHarness(t, 'VisitForm', { payload: download(0), params: { localId: existing ? 1 : 2 },
      setup: async ({ db }) => {
        if (existing) await db.runAsync("UPDATE field_visits SET photos_json = ?, local_revision = 7, sync_status = 'pending_update' WHERE local_id = 1", [JSON.stringify(photos)]);
        else await db.runAsync(`INSERT INTO field_visits (mobile_uuid, household_server_id, visited_at, notes, photos_json, sync_status, local_revision)
          VALUES (?, 1, '2026-10-07T00:00:00.000Z', 'Legacy notes', ?, 'pending_create', 7)`, [uuid.replace(/1$/, '2'), JSON.stringify(photos)]);
      } });
    const before = await row(h); const old = await h.storage.getPendingSyncPayload(1);
    assert.equal(photoCount(h), 6); assert.match(h.texts(), /hhPhotoLimitResolve/);
    assert.deepEqual(await row(h), before);
    h.input('notes').props.onChangeText('Keep corrected notes'); h.render();
    await h.button('save').props.onPress(); await h.settle();
    assert.deepEqual(await row(h), before); assert.equal(h.confirmations.length, 0); assert.equal(h.calls.length, 0);
    assert.equal(h.input('notes').props.value, 'Keep corrected notes'); assert.equal(photoCount(h), 6);
    await remove(h, 5); assert.equal(photoCount(h), 5); assert.doesNotMatch(h.texts(), /hhPhotoLimitResolve/);
    await h.button('save').props.onPress(); await h.settle();
    const after = await row(h);
    assert.equal(after.local_id, before.local_id); assert.equal(after.mobile_uuid, before.mobile_uuid);
    assert.equal(after.local_revision, 8); assert.equal(after.notes, 'Keep corrected notes'); assert.equal(after.visited_at, before.visited_at);
    assert.deepEqual(JSON.parse(after.photos_json), photos.slice(0, 5));
    const resolved = { households: [], residents: [], field_visits: [{ id: existing ? 1 : 20, mobile_uuid: after.mobile_uuid }], risk_assessments: [] };
    await h.storage.applyResolvedRecords(resolved, old.snapshot);
    const stillPending = await row(h);
    assert.equal(stillPending.sync_status, 'pending_update', 'Stale create acknowledgment maps identity but cannot clear corrected work');
    assert.equal(stillPending.local_revision, 8); assert.equal(stillPending.photos_json, after.photos_json);
    const next = await h.storage.getPendingSyncPayload(1);
    assert.equal(next.payload.field_visits[0].existing_photos.length + next.payload.field_visits[0].photos.length, 5);
    await h.storage.applyResolvedRecords(resolved, next.snapshot);
    const synced = await row(h);
    assert.equal(synced.sync_status, 'synced'); assert.equal(synced.local_revision, 8);
    assert.equal(synced.photos_json, after.photos_json, 'Acknowledgment never deletes pending image bytes');
  });
}

for (const locale of ['en', 'ceb']) {
  test(`${locale}: production translations explain capture limit and oversized upload recovery`, async t => {
    const { i18n } = createHomeHarness({ locale }).load('i18n.ts');
    const h = await householdUiHarness(t, 'VisitForm', { i18n, payload: download(5), params: { localId: 1 } });
    await h.button(i18n.t('uploadFromGallery')).props.onPress(); await h.settle();
    assert.ok(h.texts().includes(i18n.t('hhPhotoLimit'))); assert.match(i18n.t('hhPhotoLimit'), /5/);
    assert.doesNotMatch(i18n.t('hhPhotoLimit'), /missing|hhPhotoLimit/);
    const legacy = await householdUiHarness(t, 'VisitForm', { i18n, payload: download(0), params: { localId: 1 },
      setup: ({ db }) => db.runAsync("UPDATE field_visits SET photos_json = ?, sync_status = 'pending_update' WHERE local_id = 1", [JSON.stringify([1, 2, 3, 4, 5, 6].map(upload))]) });
    assert.ok(legacy.texts().includes(i18n.t('hhPhotoLimitResolve'))); assert.match(i18n.t('hhPhotoLimitResolve'), /5.*upload/);
    await legacy.button(i18n.t('save')).props.onPress(); await legacy.settle();
    assert.ok(legacy.texts().includes(i18n.t('hhPhotoLimitResolve'))); assert.equal(legacy.calls.length, 0);
  });
}

for (const source of ['gallery', 'camera']) {
  test(`${source}: in-flight callbacks cannot append after editor unmount or scope change`, async t => {
    for (const change of ['unmount', 'scope']) {
      let finish;
      const deferred = () => new Promise(resolve => { finish = resolve; });
      const h = await householdUiHarness(t, 'VisitForm', { payload: download(4), params: { localId: 1 },
        gallery: deferred, capture: deferred });
      const before = await row(h);
      let callback;
      if (source === 'camera') {
        await h.button('takePhoto').props.onPress(); await h.settle();
        callback = h.nodes().filter(n => n.type === 'Pressable' && n.props.accessibilityLabel === 'takePhoto')[1].props.onPress;
      } else callback = h.button('uploadFromGallery').props.onPress;
      const pending = callback();
      // Gallery has a permission/liveness await before launching its picker.
      await new Promise(setImmediate);
      if (change === 'unmount') h.unmount();
      else { h.context.assignment = { barangay: { id: 1 }, purok: { id: 2 } }; await h.settle(); }
      finish(source === 'camera' ? upload(9) : { canceled: false, assets: [upload(9)] });
      await pending;
      if (change === 'scope') { await h.settle(); assert.doesNotMatch(h.texts(), /Pilot home/); }
      assert.deepEqual(await row(h), before);
      assert.equal(h.nodes().some(n => n.type === 'Image' && n.props.source?.uri === 'file:///photo-9.jpg'), false);
    }
  });
}
