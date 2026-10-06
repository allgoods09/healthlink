import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import vm from 'node:vm';
import { storageHarness } from './storageHarness.mjs';

const require = createRequire(import.meta.url);
const ts = require('typescript');
const tick = () => new Promise(setImmediate);
const payload = { resident_contract_version: 2, user: { id: 1 }, assignment: { barangay: { id: 1 }, purok: { id: 1 } },
  server_time: '2026-10-06', resident_relationship_choices: ['Daughter', 'Son'],
  resident_profile_choices: { employment_status: ['Employed', 'Unemployed', 'N/A'],
    highest_education_level: ['None', 'Elementary', 'High School', 'College', 'Post Grad', 'Vocational'], education_status: ['Graduate', 'Undergraduate', 'N/A'] },
  households: [{ id: 1, purok_id: 1, household_no: '2', household_address: 'Pilot home', is_active: true }],
  residents: [], field_visits: [], risk_assessments: [] };

// Execute the production form callbacks with controlled hooks/navigation and real
// SQLite. This is not a claim of native rendering or device-level interaction QA.
async function formHarness(t, name = 'ResidentForm', overrides = {}) {
  const h = await storageHarness();
  t.after(h.close);
  await h.storage.prepareDatasetForUser(1);
  await h.storage.replaceBootstrapData(overrides.payload ?? payload);
  const hooks = []; let cursor = 0; let effects = []; let removal; const calls = [];
  const react = {
    createElement: (type, props, ...children) => ({ type, props: { ...props, children } }),
    useState(initial) { const i = cursor++; if (!(i in hooks)) hooks[i] = typeof initial === 'function' ? initial() : initial;
      return [hooks[i], value => { hooks[i] = typeof value === 'function' ? value(hooks[i]) : value; }]; },
    useRef(initial) { const i = cursor++; return hooks[i] ??= { current: initial }; },
    useEffect(callback, deps) { const i = cursor++; const old = hooks[i];
      if (!old || deps.some((d, n) => !Object.is(d, old.deps[n]))) effects.push(() => { old?.cleanup?.(); hooks[i].cleanup = callback(); });
      hooks[i] = { deps, cleanup: old?.cleanup }; },
  };
  const route = { params: overrides.params ?? {} };
  const navigation = Object.fromEntries(['navigate', 'goBack', 'popTo', 'dispatch', 'setOptions'].map(key =>
    [key, (...args) => calls.push({ key, args })]));
  navigation.setParams = params => { Object.assign(route.params, params); };
  const context = { user: { id: 1 }, assignment: payload.assignment, dataVersion: 1,
    requestConfirmation: async () => true, bumpDataVersion() {}, ...overrides };
  const theme = { colors: {}, spacing: { xl: 24 } };
  const deps = {
    react, 'react-native': Object.fromEntries(['Pressable', 'Text', 'TextInput', 'ScrollView', 'View', 'Modal', 'Switch', 'FlatList'].map(n => [n, n])),
    '@react-navigation/native': { usePreventRemove: (blocked, callback) => { removal = { blocked, callback }; } },
    '@react-native-community/datetimepicker': { DateTimePickerAndroid: { open() {} } },
    '../context/AppContext': { useAppContext: () => context, useAppTheme: () => theme, useThemedStyles: () => ({}) },
    '../components/KeyboardShiftView': { KeyboardShiftView: 'KeyboardShiftView' },
    '../hooks/useKeyboardAwareScroll': { useKeyboardAwareScroll: () => ({ scrollRef: { current: { scrollTo() {} } }, keyboardInset: 0 }) },
    '../i18n': { i18n: { t: key => key } },
    '../lib/storage': { ...h.storage, ...overrides.storage },
    '../lib/residentWorkflow': h.workflow,
    '../lib/useLocalEditor': { useLocalEditor() {} },
  };
  deps['react-native'].StyleSheet = { create: value => value };
  const formatCode = ts.transpileModule(readFileSync(new URL('../src/lib/format.ts', import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
  const formatModule = { exports: {} };
  vm.runInThisContext(`(function(module, exports){${formatCode}})`)(formatModule, formatModule.exports);
  deps['../lib/format'] = formatModule.exports;
  deps['../lib/householdIdentity'] = { findHouseholdByReference: (rows, record) => rows.find(row => record.household_server_id != null
    ? row.server_id === record.household_server_id : record.household_mobile_uuid && row.mobile_uuid === record.household_mobile_uuid) };
  const module = { exports: {} };
  const source = readFileSync(new URL(`../src/screens/${name}Screen.tsx`, import.meta.url), 'utf8');
  const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022,
    jsx: ts.JsxEmit.React, esModuleInterop: true } }).outputText;
  vm.runInThisContext(`(function(require, module, exports){${code}})`)(n => {
    if (!(n in deps)) throw new Error(n); return deps[n];
  }, module, module.exports);
  let tree;
  function render() { cursor = 0; effects = []; tree = module.exports[`${name}Screen`]({ route, navigation }); effects.forEach(f => f()); return tree; }
  async function settle() { for (let n = 0; n < 8; n++) { render(); await tick(); } render(); }
  function nodes(node) { if (arguments.length === 0) node = tree; if (!node || typeof node !== 'object') return []; if (Array.isArray(node)) return node.flatMap(n => nodes(n));
    return [node, ...nodes(node.props?.children)]; }
  function button(label) { return nodes().find(n => n.type === 'Pressable' && (n.props.accessibilityLabel === label ||
    n.props.children.some(child => child?.type === 'Text' && child.props.children.includes(label)))); }
  function field(label) { const all = nodes(); const index = all.findIndex(n => n.type === 'Text' && n.props.children[0] === label);
    return all.slice(index + 1).find(n => n.type === 'TextInput'); }
  await settle();
  return { ...h, render, settle, route, calls, button, field, nodes, removal: () => removal };
}

test('wizard starts with identity only, required fields blank, and no Household creation or head proposal', async t => {
  const h = await formHarness(t);
  assert.equal(h.field('firstName').props.value, '');
  assert.equal(h.nodes().some(n => n.type === 'TextInput' && n.props.accessibilityLabel === 'civilStatus'), false);
  const text = JSON.stringify(h.render());
  assert.ok(text.includes('residentStepIdentity'));
  assert.ok(!text.includes('Create Household Request'));
  assert.ok(!text.includes('Propose as household head'));
  assert.ok(!h.nodes().some(n => n.type === 'Switch'));
});

test('obsolete Household return parameters do not select a new pending household or expose a shortcut', async t => {
  const h = await formHarness(t);
  h.field('firstName').props.onChangeText('Unsaved Ana');
  h.render();
  assert.equal(h.button('Create Household Request'), undefined);
  const id = await h.storage.saveHousehold({ purok_id: 1, household_no: '3', household_address: 'Created home',
    is_active: true, is_social_aid_beneficiary: false }, 1);
  h.route.params.createdHouseholdLocalId = id;
  await h.settle();
  assert.equal(h.field('firstName').props.value, 'Unsaved Ana');
  assert.ok(!JSON.stringify(h.render()).includes('Propose as household head'));
  h.button('chooseHousehold').props.onPress(); await h.settle();
  const homes = h.nodes().find(n => n.type === 'FlatList' && n.props.data?.[0]?.household_no);
  assert.equal(homes.props.data.some(row => row.local_id === id), false);
  assert.equal(h.calls.some(c => c.key === 'navigate'), false);
});

test('unsaved Resident back navigation warns and only dispatches the original action after confirmation', async t => {
  let answer = false; const confirmations = [];
  const h = await formHarness(t, 'ResidentForm', { requestConfirmation: async options => { confirmations.push(options); return answer; } });
  assert.equal(h.removal().blocked, false);
  h.field('firstName').props.onChangeText('Unsaved'); await h.settle();
  assert.equal(h.removal().blocked, true);
  const action = { type: 'GO_BACK' };
  h.removal().callback({ data: { action } }); await tick();
  assert.equal(h.calls.some(c => c.key === 'dispatch'), false);
  answer = true; h.removal().callback({ data: { action } }); await tick();
  assert.deepEqual(h.calls.find(c => c.key === 'dispatch').args, [action]);
  assert.equal(confirmations[0].message, 'residentLeaveMessage');
});

test('standalone Household save retains double-tap protection and returns normally without Resident handoff', async t => {
  let finish; const confirmation = new Promise(resolve => { finish = resolve; });
  const h = await formHarness(t, 'HouseholdForm', { requestConfirmation: () => confirmation });
  h.field('householdNo').props.onChangeText('9'); h.field('householdAddress').props.onChangeText('Created home'); h.render();
  const save = h.button('save').props.onPress;
  const first = save(); const second = save(); finish(true); await Promise.all([first, second]);
  const rows = await h.storage.getHouseholds();
  assert.equal(rows.length, 2);
  assert.equal(h.calls.filter(c => c.key === 'goBack').length, 1);
  assert.equal(h.calls.some(c => c.key === 'popTo'), false);
});

test('standalone Household storage failure retains values and allows retry without Resident handoff', async t => {
  let fail = true;
  const h = await formHarness(t, 'HouseholdForm', { storage: { saveHousehold: async () => { if (fail) throw new Error('Unable to save'); return 7; } } });
  h.field('householdNo').props.onChangeText('9'); h.field('householdAddress').props.onChangeText('Retained address'); h.render();
  await h.button('save').props.onPress(); h.render();
  assert.equal(h.field('householdAddress').props.value, 'Retained address');
  assert.equal(h.calls.some(c => c.key === 'popTo'), false);
  assert.equal(h.button('save').props.disabled, false);
  fail = false; await h.button('save').props.onPress();
  assert.equal(h.calls.filter(c => c.key === 'goBack').length, 1);
  assert.equal(h.calls.some(c => c.key === 'popTo'), false);
});

async function fillResident(h) {
  for (const [label, value] of [['firstName', 'Ana'], ['lastName', 'Santos'], ['birthDate', '1990/01/01'],
    ['birthPlace', 'Tubigon']]) h.field(label).props.onChangeText(value);
  h.button('Female').props.onPress(); h.render();
  h.button('chooseHousehold').props.onPress(); await h.settle();
  const homes = h.nodes().find(n => n.type === 'FlatList' && n.props.data?.[0]?.household_no);
  homes.props.renderItem({ item: homes.props.data[0] }).props.onPress(); h.render();
  h.button('relationshipToHead').props.onPress(); await h.settle();
  const relations = h.nodes().find(n => n.type === 'FlatList' && n.props.data?.[0] === 'Daughter');
  relations.props.renderItem({ item: 'Daughter' }).props.onPress(); await h.settle();
  h.button('next').props.onPress(); await h.settle();
  h.field('civilStatus').props.onChangeText('Single'); await h.settle();
  h.button('next').props.onPress(); await h.settle();
  h.button('next').props.onPress(); await h.settle();
  h.button('residentReviewSave').props.onPress(); await h.settle();
}

test('Resident form double-save preserves one UUID/revision and exits only after successful local save', async t => {
  let finish; const pending = new Promise(resolve => { finish = resolve; });
  const h = await formHarness(t, 'ResidentForm', { storage: { saveResident: async (...args) => { await pending; return h.storage.saveResident(...args); } } });
  await fillResident(h);
  const save = h.button('residentSaveDevice').props.onPress;
  const first = save(); const second = save(); h.render();
  assert.equal(h.button('residentSaving').props.disabled, true);
  finish(true); await Promise.all([first, second]); await h.settle();
  const requests = await h.storage.getResidentRequests();
  assert.equal(requests.length, 1); assert.ok(requests[0].mobile_uuid);
  assert.equal(requests[0].local_revision, 0);
  assert.equal(h.calls.filter(c => c.key === 'goBack').length, 1);
  assert.equal(await h.storage.getCurrentOfficialResidentCount(), 0);
});

test('Resident storage failure retains form values/unsaved guard and safely retries once', async t => {
  let fail = true; let saves = 0;
  const h = await formHarness(t, 'ResidentForm', { storage: { saveResident: async () => {
    saves++; if (fail) throw new Error('Unable to save'); return 8;
  } } });
  await fillResident(h);
  await h.button('residentSaveDevice').props.onPress(); await h.settle();
  assert.ok(JSON.stringify(h.render()).includes('Ana'));
  assert.equal(h.removal().blocked, true);
  assert.equal(h.calls.some(c => c.key === 'goBack'), false);
  fail = false; await h.button('residentSaveDevice').props.onPress(); await h.settle();
  assert.equal(saves, 2); assert.equal(h.calls.filter(c => c.key === 'goBack').length, 1);
});

test('Next validates only the current step; Back retains identity and does not persist a partial request', async t => {
  const h = await formHarness(t);
  h.button('next').props.onPress(); await h.settle();
  assert.ok(JSON.stringify(h.render()).includes('residentStepIdentity'));
  assert.equal((await h.storage.getPendingChangeSummary()).residents, 0);
  await fillResident(h);
  const review = h.nodes().find(n => n.type === 'Modal' && n.props.visible && JSON.stringify(n.props.children).includes('residentSaveDevice'));
  review.props.onRequestClose(); await h.settle();
  for (let i = 0; i < 3; i++) { h.button('back').props.onPress(); await h.settle(); }
  assert.equal(h.field('firstName').props.value, 'Ana');
  assert.ok(JSON.stringify(h.render()).includes('residentStepIdentity'));
  assert.equal((await h.storage.getPendingChangeSummary()).residents, 0);
  h.button('next').props.onPress(); await h.settle();
  assert.equal(h.field('civilStatus').props.value, 'Single');
  assert.equal(h.field('citizenship').props.value, 'Filipino');
});

test('profile selectors use downloaded enum vocabulary; conditional PWD field and review retain complete entered values', async t => {
  const h = await formHarness(t); await fillResident(h);
  h.nodes().find(n => n.type === 'Modal' && n.props.visible).props.onRequestClose(); await h.settle();
  h.button('back').props.onPress(); await h.settle();
  h.field('residentOccupation').props.onChangeText('Farmer'); await h.settle();
  h.button('residentEmployment').props.onPress(); await h.settle();
  const choices = h.nodes().find(n => n.type === 'FlatList');
  assert.deepEqual(choices.props.data, payload.resident_profile_choices.employment_status);
  choices.props.renderItem({ item: 'Employed' }).props.onPress(); await h.settle();
  h.button('next').props.onPress(); await h.settle();
  assert.equal(h.nodes().some(n => n.type === 'TextInput' && n.props.accessibilityLabel === 'residentDisability'), false);
  h.button('residentPwd: yes').props.onPress(); await h.settle();
  h.field('residentDisability').props.onChangeText('Recorded disability'); await h.settle();
  h.button('residentReviewSave').props.onPress(); await h.settle();
  assert.ok(JSON.stringify(h.render()).includes('Farmer'));
  assert.ok(JSON.stringify(h.render()).includes('Recorded disability'));
  await h.button('residentSaveDevice').props.onPress(); await h.settle();
  const request = (await h.storage.getResidentRequests())[0];
  assert.equal(request.occupation, 'Farmer'); assert.equal(request.employment_status, 'Employed'); assert.equal(request.is_pwd, true);
  assert.equal(request.disability_type, 'Recorded disability');
});

test('correction wizard prefills full known profile and moving steps creates no changed fields', async t => {
  const record = { id: 1, household_id: 1, first_name: 'Original', last_name: 'Pilot', birth_date: '1990-01-01', birth_place: 'Tubigon',
    sex: 'Female', civil_status: 'Single', citizenship: 'Filipino', relationship_to_head: 'Daughter', resident_status: 'active', is_active: true,
    philsys_card_no: 'PRESERVED', occupation: 'Driver', employment_status: 'Employed', highest_education_level: 'College', education_status: 'Graduate',
    is_pwd: false, is_ofw: false, is_solo_parent: false, is_osy: false, is_osc: false, is_ip: false };
  const h = await formHarness(t, 'ResidentForm', { payload: { ...payload, residents: [record] }, params: { localId: 1 } });
  assert.equal(h.field('philsysCardNumber').props.value, 'PRESERVED'); assert.equal(h.removal().blocked, false);
  h.button('next').props.onPress(); await h.settle(); h.button('next').props.onPress(); await h.settle();
  assert.equal(h.field('residentOccupation').props.value, 'Driver'); assert.equal(h.removal().blocked, false);
  assert.equal((await h.storage.getPendingChangeSummary()).residents, 0);
  h.field('residentOccupation').props.onChangeText(''); await h.settle();
  h.button('next').props.onPress(); await h.settle(); h.button('residentReviewSave').props.onPress(); await h.settle();
  await h.button('residentSaveDevice').props.onPress(); await h.settle();
  assert.deepEqual((await h.storage.getPendingSyncPayload(1)).payload.residents[0].proposed_changes, { occupation: null });
});

test('rejected/submitted modes and incompatible official cache cannot save through the wizard', async t => {
  for (const status of ['submitted', 'rejected']) {
    const data = { ...payload, residents: [{ id: status === 'submitted' ? 1 : null, household_id: 1,
      mobile_uuid: '00000000-0000-4000-8000-000000000099', first_name: 'Blocked', last_name: 'Pilot', birth_date: '1990-01-01', birth_place: 'Tubigon',
      sex: 'Female', civil_status: 'Single', citizenship: 'Filipino', relationship_to_head: 'Daughter', resident_status: 'active', is_active: true,
      verification_status: status }] };
    const h = await formHarness(t, 'ResidentForm', { payload: data, params: { localId: 1 } });
    assert.equal(h.button('next').props.disabled, true); assert.equal((await h.storage.getPendingChangeSummary()).residents, 0);
  }
  const h = await formHarness(t, 'ResidentForm', { params: { localId: 999 } });
  assert.equal(h.button('next').props.disabled, true); assert.ok(JSON.stringify(h.render()).includes('residentProfileRefreshRequired'));
});

test('review contains no technical request identifiers and Android Back dismisses it without saving', async t => {
  const h = await formHarness(t); await fillResident(h);
  const modal = h.nodes().find(n => n.type === 'Modal' && n.props.visible);
  const contents = JSON.stringify(modal.props.children);
  for (const forbidden of ['mobile_uuid', 'server_id', 'local_revision', 'sync_status', 'proposed_changes']) assert.equal(contents.includes(forbidden), false);
  assert.ok(contents.includes('residentSaveDeviceNote')); assert.ok(contents.includes('residentStepEducation'));
  modal.props.onRequestClose(); await h.settle();
  assert.equal(h.nodes().filter(n => n.type === 'Modal' && n.props.visible).length, 0);
  assert.equal((await h.storage.getPendingChangeSummary()).residents, 0); assert.equal(h.removal().blocked, true);
});
