import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { householdUiHarness, householdDownload } from './householdUiHarness.mjs';
const request = { purok_id: 1, household_no: 'NEW', household_address: 'Request address', is_active: true, is_social_aid_beneficiary: false };

test('Directory defaults to official assigned households, View only, separate request and sanitized lookup destinations', async t => {
  const h = await householdUiHarness(t, 'HouseholdDirectory', { setup: async h => { await h.storage.saveHousehold(request, 1); } });
  assert.equal(h.cards().length, 1); assert.match(h.texts(h.cards()[0]), /Ana Ybañez/);
  assert.equal(h.nodes(h.cards()[0]).filter(n => n.type === 'Pressable').length, 1);
  h.button('hhRequests').props.onPress(); await h.settle();
  assert.equal(h.cards().length, 1); assert.match(h.texts(h.cards()[0]), /hhStatus_unsent/);
  assert.doesNotMatch(h.texts(h.cards()[0]), /hhMembersCount|Ana Ybañez|hhAvailability/);
  h.button('hhLookup').props.onPress(); await h.settle();
  assert.equal(h.cards().length, 1); assert.match(h.texts(h.cards()[0]), /Lookup address/);
  assert.doesNotMatch(JSON.stringify(h.cards()), /Hidden person|socialAid|hhMembersCount|hhRequestUpdate|createVisit/);
  assert.equal(await h.storage.getCurrentOfficialHouseholdCount(), 1);
});

test('Directory debounce retains search input, paging is bounded, wildcard characters are literal, ordering stable', async t => {
  const h = await householdUiHarness(t, 'HouseholdDirectory'); const p = h.load('lib/householdPresentation.ts');
  const rows = Array.from({ length: 50 }, (_, i) => ({ ...request, access_mode: 'operational', household_no: String(i + 1), local_id: i + 1, current_head_name: 'Authorized Head' }));
  const page = p.householdPage(rows.reverse(), 'operational', ''); assert.equal(page.rows.length, 30); assert.equal(page.total, 50);
  assert.deepEqual(page.rows.slice(0, 3).map(r => r.household_no), ['1', '2', '3']);
  const literal = [{ ...rows[0], household_no: '2_%', household_address: '  Pilot   HOME ' }];
  assert.equal(p.householdPage(literal, 'operational', ' _% ').total, 1);
  assert.equal(p.householdPage(literal, 'operational', 'pilot   home').total, 1);
  assert.equal(p.householdPage(rows, 'operational', '%').total, 0);
  const ties = [2, 1].map(id => ({ ...rows[0], household_no: '002-A', local_id: id }));
  assert.deepEqual(p.householdPage(ties, 'operational', '').rows.map(r => r.local_id), [1, 2]);
  assert.equal(p.householdPage([{ ...rows[0], access_mode: 'lookup', current_head_name: 'Secret' }], 'lookup', 'Secret').total, 0);
  h.input('hhSearch').props.onChangeText('absent'); await h.settle(); assert.equal(h.cards().length, 1);
  h.flushTimers(); await h.settle(); assert.equal(h.cards().length, 0); assert.equal(h.input('hhSearch').props.value, 'absent');
  assert.match(h.texts(), /hhNoMatches/);
});

for (const [label, data, expected] of [['head', { current_member_count: 4, current_head_name: 'Ana' }, 'hhHead'],
  ['headless', { current_member_count: 4, current_head_name: null }, 'hhOccupancy_headless'],
  ['vacant', { current_member_count: 0, current_head_name: 'Stale pointer' }, 'hhOccupancy_vacant'],
  ['unknown', { current_member_count: null, resident_count: 99 }, 'hhOccupancy_unknown'],
  ['unverified', { member_coverage: 'unverified', current_member_count: 0 }, 'hhOccupancy_unknown']]) {
  test(`Details shows canonical ${label} without count fallback or local head inference`, async t => {
    const payload = householdDownload(); Object.assign(payload.households[0], data);
    const h = await householdUiHarness(t, 'HouseholdDetails', { payload, params: { localId: 1 } });
    assert.match(h.texts(), new RegExp(expected));
    if (expected === 'hhOccupancy_unknown') assert.doesNotMatch(h.texts(), /hhMembersCount/);
    if (label === 'vacant') assert.doesNotMatch(h.texts(), /Stale pointer/);
    assert.ok(h.button('createVisit')); assert.ok(h.button('hhRequestUpdate'));
  });
}

test('Lookup Details suppresses population, head, social aid, Visit history and mutation; stale ID is not a create mode', async t => {
  const h = await householdUiHarness(t, 'HouseholdDetails', { params: { localId: 2 } });
  assert.match(h.texts(), /hhLookupHint/);
  assert.doesNotMatch(h.texts(), /Hidden person|hhMembersCount|hhCurrentMembers|socialAid|hhRecentVisits/);
  assert.equal(h.button('createVisit'), undefined); assert.equal(h.button('hhRequestUpdate'), undefined);
  h.route.params.localId = 999; await h.settle(); assert.match(h.texts(), /hhUnavailable/);
});

test('Details focus/dataVersion freshness ignores obsolete responses and errors allow safe retry', async t => {
  let finish; let fail = false; let delayed = true;
  const h = await householdUiHarness(t, 'HouseholdDetails', { params: { localId: 1 }, storage: {
    getHouseholdByLocalId: async id => { if (fail) throw Error('SQL secret'); if (delayed) return new Promise(resolve => { finish = resolve; }); return h.storage.getHouseholdByLocalId(id); },
  } });
  assert.match(h.texts(), /loading/); delayed = false; h.route.params.localId = 2; await h.settle();
  finish({ ...request, local_id: 1, access_mode: 'operational', current_head_name: 'Obsolete head' }); await h.settle();
  assert.match(h.texts(), /Lookup address/); assert.doesNotMatch(h.texts(), /Obsolete head/);
  fail = true; h.context.dataVersion++; await h.settle(); assert.match(h.texts(), /savedRecordsError/); assert.doesNotMatch(h.texts(), /SQL secret/);
  fail = false; h.button('retry').props.onPress(); await h.settle(); assert.match(h.texts(), /Lookup address/);
  h.focus(false); h.render(); assert.doesNotMatch(h.texts(), /Lookup address/); h.focus(true); await h.settle(); assert.match(h.texts(), /Lookup address/);
});

for (const [status, expected] of [['submitted', 'hhHelp_submitted'], ['rejected', 'hhHelp_rejected'], ['approved', 'hhHelp_approved'], ['protected', 'hhHelp_protected']]) {
  test(`${status} request is read-only in Details/Form with no Visit or resubmit action`, async t => {
    const setup = async h => { const id = await h.storage.saveHousehold(request, 1);
      await h.db.runAsync('UPDATE households SET verification_status = ?, sync_status = ?, verification_notes = ?, protection_reason = ? WHERE local_id = ?',
        [status === 'protected' ? 'pending' : status, status === 'protected' ? 'pending_create' : 'synced', 'Secretary note', status === 'protected' ? 'Older format' : null, id]); };
    const h = await householdUiHarness(t, 'HouseholdDetails', { setup, params: { localId: 3 } });
    assert.match(h.texts(), new RegExp(expected)); assert.match(h.texts(), /Secretary note/);
    assert.equal(h.button('createVisit'), undefined); assert.equal(h.button('hhRequestUpdate'), undefined); assert.equal(h.button('hhEditRequest'), undefined);
    const f = await householdUiHarness(t, 'HouseholdForm', { setup, params: { localId: 3 } });
    assert.equal(f.input('householdNo').props.editable, false); assert.equal(f.button('save'), undefined); assert.equal(f.removal().blocked, false);
    assert.match(f.texts(), new RegExp(expected));
  });
}

test('New/unsent/correction modes validate trimmed fields, preserve false, freeze Purok/availability and keep genuine deltas', async t => {
  const h = await householdUiHarness(t, 'HouseholdForm'); assert.ok(h.calls.some(c => c.key === 'setOptions' && c.args[0].title === 'hhNew'));
  assert.equal(h.nodes().filter(n => n.type === 'Switch').length, 1);
  await h.button('save').props.onPress(); await h.settle(); assert.match(h.texts(), /hhNumberInvalid/); assert.equal(h.confirmations.length, 0);
  h.input('householdNo').props.onChangeText('x'.repeat(51)); h.render(); await h.button('save').props.onPress(); await h.settle(); assert.match(h.texts(), /hhNumberInvalid/);
  h.input('householdNo').props.onChangeText(' 003-B '); h.render(); await h.button('save').props.onPress(); await h.settle(); assert.match(h.texts(), /hhAddressInvalid/);
  h.input('householdAddress').props.onChangeText(' New address '); h.render(); await h.button('save').props.onPress(); await h.settle();
  const row = (await h.storage.getHouseholdRequests())[0]; assert.equal(row.household_no, '003-B'); assert.equal(row.household_address, 'New address'); assert.equal(row.is_social_aid_beneficiary, false);
  const c = await householdUiHarness(t, 'HouseholdForm', { params: { localId: 1 } });
  assert.ok(c.calls.some(call => call.key === 'setOptions' && call.args[0].title === 'hhRequestUpdate')); assert.match(c.texts(), /hhAvailability/);
  await c.button('save').props.onPress(); await c.settle(); assert.deepEqual((await c.storage.getPendingSyncPayload(1)).payload.households, []);
  const u = await householdUiHarness(t, 'HouseholdForm', { params: { localId: 3 }, setup: async h => { await h.storage.saveHousehold(request, 1); } });
  assert.ok(u.calls.some(call => call.key === 'setOptions' && call.args[0].title === 'hhEditRequest'));
});

test('Household dirty Back, double Save, failure retention and successful exit use existing confirmation safely', async t => {
  let release; let fail = true;
  const h = await householdUiHarness(t, 'HouseholdForm', { context: { requestConfirmation: () => new Promise(resolve => { release = resolve; }) },
    storage: { saveHousehold: async values => { if (fail) throw Error('internal'); return h.storage.saveHousehold(values, 1); } } });
  h.input('householdNo').props.onChangeText('3'); h.input('householdAddress').props.onChangeText('Retained home'); h.render(); assert.equal(h.removal().blocked, true);
  const first = h.button('save').props.onPress(); const second = h.button('save').props.onPress(); release(true); await Promise.all([first, second]); await h.settle();
  assert.match(h.texts(), /hhSaveError/); assert.equal(h.input('householdAddress').props.value, 'Retained home'); assert.equal(h.calls.some(c => c.key === 'goBack'), false);
  const action = { type: 'GO_BACK' }; h.removal().callback({ data: { action } }); release(false); await h.settle(); assert.equal(h.calls.some(c => c.key === 'dispatch'), false);
  fail = false; const retry = h.button('save').props.onPress(); release(true); await retry; await h.settle();
  assert.equal((await h.storage.getHouseholdRequests()).length, 1); assert.equal(h.calls.filter(c => c.key === 'goBack').length, 1);
});

test('Visit chooser reuses safe bottom sheet, official unavailable households remain selectable, dirty state includes parent/date/photos', async t => {
  const h = await householdUiHarness(t, 'VisitForm'); assert.equal(h.button('save').props.disabled, true);
  h.button('chooseHousehold').props.onPress(); await h.settle();
  const list = h.nodes().find(n => n.type === 'FlatList'); assert.equal(list.props.data.length, 1); assert.equal(list.props.data[0].label, '2');
  list.props.renderItem({ item: list.props.data[0] }).props.onPress(); await h.settle();
  assert.match(h.texts(), /Pilot home|Purok 1/); assert.equal(h.removal().blocked, true); assert.equal(h.button('save').props.disabled, false);
  h.button('visitDate').props.onPress(); assert.ok(h.pickers.length); assert.equal(h.nodes().find(n => n.type === 'Modal').props.onRequestClose instanceof Function, true);
  h.button('takePhoto').props.onPress(); await h.settle();
  const modal = h.nodes().filter(n => n.type === 'Modal')[1]; assert.equal(modal.props.visible, true); modal.props.onRequestClose(); await h.settle();
  assert.equal(h.nodes().filter(n => n.type === 'Modal')[1].props.visible, false);
});

test('Visit repeat-save protection, failure retention, photo preservation and scoped direct IDs', async t => {
  let finish; let fail = true; let saves = 0;
  const h = await householdUiHarness(t, 'VisitForm', { params: { localId: 1 }, storage: { saveVisit: async values => { saves++; if (fail) throw Error('raw SQL'); return h.storage.saveVisit(values, 1); } },
    context: { requestConfirmation: () => new Promise(resolve => { finish = resolve; }) } });
  assert.match(h.texts(), /syncedPhotoLabel/); assert.doesNotMatch(h.texts(), /visit-photos/);
  h.input('notes').props.onChangeText('Keep notes'); h.render(); const before = h.nodes().filter(n => n.type === 'Image').length;
  const first = h.button('save').props.onPress(); const second = h.button('save').props.onPress(); finish(true); await Promise.all([first, second]); await h.settle();
  assert.equal(saves, 1); assert.equal(h.input('notes').props.value, 'Keep notes'); assert.equal(h.calls.some(c => c.key === 'goBack'), false); assert.match(h.texts(), /hhVisitSaveError/);
  assert.equal(h.nodes().filter(n => n.type === 'Image').length, before);
  fail = false; const next = h.button('save').props.onPress(); finish(true); await next; await h.settle(); assert.equal(h.calls.filter(c => c.key === 'goBack').length, 1);
  assert.equal((await h.storage.getVisits())[0].recorded_by_name, 'Original BHW');
  const foreign = await householdUiHarness(t, 'VisitForm', { params: { householdLocalId: 2 } }); assert.equal(foreign.button('save').props.disabled, true); assert.match(foreign.texts(), /hhVisitUnavailable/);
});

test('Retained legacy pending/rejected parent visits are scoped read-only presentation without acknowledgment/deletion', async t => {
  const setup = async h => { const id = await h.storage.saveHousehold(request, 1); const row = await h.storage.getHouseholdRequestByLocalId(id);
    await h.db.runAsync("INSERT INTO field_visits (household_mobile_uuid, visited_at, notes, photos_json, sync_status) VALUES (?, ?, ?, ?, 'pending_create')",
      [row.mobile_uuid, '2026-10-07', 'Legacy pending visit', JSON.stringify([{ uri: 'file:///pending.jpg', base64: 'photo' }])]); };
  const h = await householdUiHarness(t, 'Visits', { setup });
  assert.match(h.texts(), /hhVisitWaiting/); const original = await h.db.getAllAsync("SELECT * FROM field_visits WHERE sync_status != 'synced'");
  assert.equal((await h.storage.getWaitingHouseholdVisits()).length, 1); assert.equal((await h.storage.getVisits()).length, 1);
  await h.db.runAsync("UPDATE households SET protection_reason = 'Earlier reviewed revision' WHERE access_mode = 'request'");
  h.context.dataVersion++; await h.settle(); assert.match(h.texts(), /hhVisitProtected/);
  await h.db.runAsync("UPDATE households SET protection_reason = NULL WHERE access_mode = 'request'");
  await h.db.runAsync("UPDATE households SET verification_status = 'rejected', verification_notes = 'Duplicate' WHERE access_mode = 'request'");
  h.context.dataVersion++; await h.settle(); assert.match(h.texts(), /hhVisitRejected|Duplicate/);
  assert.deepEqual(await h.db.getAllAsync("SELECT * FROM field_visits WHERE sync_status != 'synced'"), original);
  assert.equal((await h.storage.getPendingSyncPayload(1)).payload.field_visits.length, 0);
  await h.storage.setAppState('resident_workspace_blocked', 'assignment'); assert.deepEqual(await h.storage.getWaitingHouseholdVisits(), []);
});

test('Visit history retains original bootstrap recorder and excludes lookup history', async t => {
  const h = await householdUiHarness(t, 'HouseholdDetails', { params: { localId: 1 } });
  assert.match(h.texts(), /Original BHW/); assert.match(h.texts(), /Recorded history/);
  assert.equal((await h.storage.getVisits())[0].recorded_by_name, 'Original BHW');
  h.route.params.localId = 2; await h.settle(); assert.doesNotMatch(h.texts(), /Original BHW|Recorded history/);
});

test('Directory and Forms distinguish failed reads, empty results, incompatible cache and assignment invalidation', async t => {
  let fail = true;
  const h = await householdUiHarness(t, 'HouseholdDirectory', { storage: { getHouseholds: async () => { if (fail) throw Error('private error'); return []; } } });
  assert.match(h.texts(), /savedRecordsError/); assert.doesNotMatch(h.texts(), /private error/);
  fail = false; h.button('retry').props.onPress(); await h.settle(); assert.match(h.texts(), /hhEmpty_operational/);
  await h.storage.setAppState('household_contract_version', '0'); h.context.dataVersion++; await h.settle(); assert.match(h.texts(), /hhRefresh/);
  assert.equal(h.button('hhNew'), undefined);
  const f = await householdUiHarness(t, 'HouseholdForm'); f.input('householdNo').props.onChangeText('Keep'); f.render();
  f.context.dataVersion++; await f.settle(); assert.equal(f.input('householdNo').props.value, 'Keep'); assert.equal(f.button('save').props.disabled, true);
  assert.equal(f.removal().blocked, true); assert.match(f.texts(), /hhRefresh/);
});

test('Official detail keeps baseline distinct from proposed corrections, including rejection', async t => {
  const h = await householdUiHarness(t, 'HouseholdDetails', { params: { localId: 1 }, setup: async h => {
    const row = (await h.storage.getHouseholds())[0]; await h.storage.saveHousehold({ ...row, household_address: 'Proposed home' }, 1);
  } });
  assert.match(h.texts(), /Pilot home/); assert.match(h.texts(), /Proposed home/); assert.match(h.texts(), /hhProposedValues/);
  await h.db.runAsync("UPDATE households SET verification_status = 'rejected', verification_notes = 'Not correct' WHERE server_id = 1");
  h.context.dataVersion++; await h.settle(); assert.match(h.texts(), /hhHelp_rejected|Not correct/); assert.equal(h.button('hhRequestUpdate'), undefined);
});

test('Household approved request becomes one operational record only after authoritative refresh', async t => {
  let row;
  const h = await householdUiHarness(t, 'HouseholdDirectory', { setup: async h => {
    await h.storage.saveHousehold(request, 1); row = (await h.storage.getHouseholdRequests())[0];
    await h.storage.applyResolvedRecords({ households: [{ mobile_uuid: row.mobile_uuid, id: null, verification_status: 'submitted' }], residents: [], field_visits: [], risk_assessments: [] },
      (await h.storage.getPendingSyncPayload(1)).snapshot);
  } });
  h.button('hhRequests').props.onPress(); await h.settle(); assert.equal(h.cards().length, 1);
  const payload = householdDownload(); payload.households.push({ id: 3, mobile_uuid: row.mobile_uuid, purok_id: 1,
    household_no: 'Verified NEW', household_address: 'Secretary corrected address', is_active: true, current_member_count: 0 });
  // The fixture helper adds capability metadata for an official new row.
  const wrapped = (await import('./householdFixture.mjs')).withHouseholdContract(payload);
  await h.storage.replaceBootstrapData(wrapped); h.context.dataVersion++; await h.settle(); assert.equal(h.cards().length, 0);
  h.button('hhOfficial').props.onPress(); await h.settle(); assert.equal(h.cards().length, 2);
  assert.equal((await h.storage.getHouseholds()).filter(home => home.mobile_uuid === row.mobile_uuid).length, 1);
});

test('Visit dirty Back dispatches only on confirmation and gallery cancellation/failure retain existing photos', async t => {
  let answer = false; let fail = false;
  const h = await householdUiHarness(t, 'VisitForm', { params: { localId: 1 }, context: { requestConfirmation: async () => answer },
    gallery: async () => { if (fail) throw Error('gallery failure'); return { canceled: true, assets: [] }; } });
  assert.equal(h.removal().blocked, false); h.input('notes').props.onChangeText('Unsaved notes'); h.render(); assert.equal(h.removal().blocked, true);
  const action = { type: 'GO_BACK' }; h.removal().callback({ data: { action } }); await h.settle(); assert.equal(h.calls.some(c => c.key === 'dispatch'), false);
  answer = true; h.removal().callback({ data: { action } }); await h.settle(); assert.deepEqual(h.calls.find(c => c.key === 'dispatch').args, [action]);
  await h.button('uploadFromGallery').props.onPress(); await h.settle(); assert.match(h.texts(), /syncedPhotoLabel/);
  fail = true; await h.button('uploadFromGallery').props.onPress(); await h.settle(); assert.match(h.texts(), /hhPhotoError/); assert.match(h.texts(), /syncedPhotoLabel/);
  assert.equal(h.input('notes').props.value, 'Unsaved notes'); assert.equal(h.calls.some(c => c.key === 'goBack'), false);
});

test('Camera close ignores an in-flight capture and safe-area/Back wiring are retained', async t => {
  let finish;
  const h = await householdUiHarness(t, 'VisitForm', { params: { localId: 1 }, capture: () => new Promise(resolve => { finish = resolve; }) });
  await h.button('takePhoto').props.onPress(); await h.settle();
  const take = h.nodes().filter(n => n.type === 'Pressable' && n.props.accessibilityLabel === 'takePhoto')[1];
  const capture = take.props.onPress(); h.render(); const camera = h.nodes().filter(n => n.type === 'Modal')[1]; camera.props.onRequestClose();
  finish({ uri: 'file:///late.jpg', base64: 'late' }); await capture; await h.settle();
  assert.equal(h.nodes().some(n => n.type === 'Image' && n.props.source?.uri === 'file:///late.jpg'), false);
  assert.equal(h.nodes().filter(n => n.type === 'Modal')[1].props.visible, false);
});

test('Retained Visit reader cannot reveal another owner or another purok, never matches empty parent UUIDs', async t => {
  const h = await householdUiHarness(t, 'Visits');
  await h.storage.saveHousehold(request, 1); const own = (await h.storage.getHouseholdRequests())[0];
  await h.db.runAsync("INSERT INTO field_visits (household_mobile_uuid, visited_at, photos_json, sync_status) VALUES (?, '2026-10-07', '[]', 'pending_create')", [own.mobile_uuid]);
  assert.equal((await h.storage.getWaitingHouseholdVisits()).length, 1);
  await h.db.runAsync('UPDATE households SET submitted_by_user_id = 999 WHERE local_id = ?', [own.local_id]);
  assert.deepEqual(await h.storage.getWaitingHouseholdVisits(), []);
  await h.db.runAsync('UPDATE households SET submitted_by_user_id = 1, purok_id = 2 WHERE local_id = ?', [own.local_id]);
  assert.deepEqual(await h.storage.getWaitingHouseholdVisits(), []);
  await h.db.runAsync("UPDATE households SET purok_id = 1, mobile_uuid = '' WHERE local_id = ?", [own.local_id]);
  await h.db.runAsync("UPDATE field_visits SET household_mobile_uuid = '' WHERE sync_status != 'synced'");
  assert.deepEqual(await h.storage.getWaitingHouseholdVisits(), []);
});

test('Assignment change during a pending save hides old context and ignores late navigation', async t => {
  let finish;
  const h = await householdUiHarness(t, 'VisitForm', { params: { localId: 1 }, storage: { saveVisit: () => new Promise(resolve => { finish = resolve; }) } });
  h.input('notes').props.onChangeText('Old assignment work'); h.render(); const saving = h.button('save').props.onPress(); await h.settle();
  h.context.assignment = { barangay: { id: 1 }, purok: { id: 2 } }; await h.settle();
  assert.doesNotMatch(h.texts(), /Pilot home|Old assignment work/); assert.match(h.texts(), /hhRefresh/);
  finish(1); await saving; await h.settle(); assert.equal(h.calls.some(c => c.key === 'goBack'), false);
});

test('Screen readiness requires the authenticated owner and exact assignment, not merely a compatible stored cache', async t => {
  for (const context of [{ user: { id: 2 } }, { assignment: { barangay: { id: 1 }, purok: { id: 2 } } }, { assignment: null }]) {
    const h = await householdUiHarness(t, 'HouseholdDirectory', { context }); assert.equal(h.cards().length, 0); assert.match(h.texts(), /hhRefresh/);
    assert.equal(h.button('hhNew'), undefined);
  }
});

test('Successful save fences stale callbacks before React commits navigation state', async t => {
  const h = await householdUiHarness(t, 'HouseholdForm'); h.input('householdNo').props.onChangeText('3');
  h.input('householdAddress').props.onChangeText('One request only'); h.render();
  const stale = h.button('save').props.onPress; await stale(); await stale();
  assert.equal((await h.storage.getHouseholdRequests()).length, 1);
  let writes = 0;
  const v = await householdUiHarness(t, 'VisitForm', { params: { localId: 1 }, storage: { saveVisit: async () => { writes++; return 1; } } });
  const staleVisit = v.button('save').props.onPress; await staleVisit(); await staleVisit(); assert.equal(writes, 1);
});

for (const dark of [false, true]) test(`Touched controls use ${dark ? 'dark' : 'light'} tokens and accessible roles`, async t => {
  const h = await householdUiHarness(t, 'HouseholdDirectory', { dark }); assert.equal(h.input('hhSearch').props.placeholderTextColor, h.theme.colors.placeholder);
  assert.equal(h.button('hhOfficial').props.accessibilityState.selected, true);
  const styles = h.load('components/HouseholdUi.tsx').householdUiStyles(h.theme);
  assert.equal(styles.action.minHeight, 48); assert.equal(styles.actions.flexWrap, 'wrap'); assert.equal(styles.input.color, h.theme.colors.text);
  const v = await householdUiHarness(t, 'VisitForm', { dark, params: { localId: 1 } });
  assert.equal(v.input('notes').props.accessibilityLabelledBy, 'visit-notes-label');
  assert.equal(v.button('chooseHousehold').props.accessibilityRole, 'button');
});

test('Every new translation exists in English/Cebuano, narrow controls wrap, camera Back and keyboard scaffolding remain', () => {
  const text = readFileSync(new URL('../src/i18n.ts', import.meta.url), 'utf8'); const [en, ceb] = text.split('  ceb: {');
  const keys = [...en.matchAll(/\b(hh\w+):/g)].map(m => m[1]); assert.ok(keys.length > 40);
  for (const key of keys) assert.match(ceb, new RegExp(`\\b${key}:`));
  const visit = readFileSync(new URL('../src/screens/VisitFormScreen.tsx', import.meta.url), 'utf8');
  assert.match(visit, /onRequestClose=/); assert.match(visit, /SelectionBottomSheet/); assert.match(visit, /flexWrap: 'wrap'/);
  assert.match(visit, /keyboardShouldPersistTaps="handled"/); assert.match(visit, /keyboardInset/);
});
