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
  const hooks = []; let cursor = 0; let effects = []; let removal; const calls = []; const pickers = [];
  const react = {
    createElement: (type, props, ...children) => typeof type === 'function'
      ? type({ ...props, children }) : ({ type, props: { ...props, children } }),
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
  const themeModule = { exports: {} };
  const themeCode = ts.transpileModule(readFileSync(new URL('../src/theme.ts', import.meta.url), 'utf8'), { compilerOptions: { module: ts.ModuleKind.CommonJS } }).outputText;
  vm.runInThisContext(`(function(module, exports){${themeCode}})`)(themeModule, themeModule.exports);
  const theme = themeModule.exports[overrides.themeMode === 'dark' ? 'darkTheme' : 'lightTheme'];
  const deps = {
    react, 'react-native': Object.fromEntries(['Pressable', 'Text', 'TextInput', 'ScrollView', 'View', 'Modal', 'Switch', 'FlatList'].map(n => [n, n])),
    '@react-navigation/native': { usePreventRemove: (blocked, callback) => { removal = { blocked, callback }; } },
    '@react-native-community/datetimepicker': { DateTimePickerAndroid: { open(options) { pickers.push(options); } } },
    '@expo/vector-icons': { Ionicons: 'Ionicons' },
    'react-native-safe-area-context': { useSafeAreaInsets: () => ({ bottom: 20 }) },
    '../context/AppContext': { useAppContext: () => context, useAppTheme: () => theme, useThemedStyles: factory => factory(theme) },
    '../components/KeyboardShiftView': { KeyboardShiftView: 'KeyboardShiftView' },
    '../hooks/useKeyboardAwareScroll': { useKeyboardAwareScroll: () => ({ scrollRef: { current: { scrollTo() {} } }, keyboardInset: 0 }) },
    '../i18n': { i18n: { t: (key, options) => key === 'residentStepProgress' ? `Step ${options.step} of 5`
      : key === 'residentHouseholdHeadLine' ? `Household Head: ${options.head}`
      : key === 'residentHouseholdVacant' ? 'Vacant' : key } },
    '../lib/storage': { ...h.storage, ...overrides.storage },
    '../lib/residentWorkflow': h.workflow,
    '../lib/useLocalEditor': { useLocalEditor() {} },
  };
  Object.assign(deps['react-native'], { KeyboardAvoidingView: 'KeyboardAvoidingView', Platform: { OS: 'android' },
    useWindowDimensions: () => ({ width: 320, height: 640 }),
    StyleSheet: { create: value => value, absoluteFill: { position: 'absolute' }, hairlineWidth: 1 } });
  const sheetModule = { exports: {} };
  const sheetCode = ts.transpileModule(readFileSync(new URL('../src/components/SelectionBottomSheet.tsx', import.meta.url), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.React, esModuleInterop: true } }).outputText;
  vm.runInThisContext(`(function(require, module, exports){${sheetCode}})`)(n => deps[n], sheetModule, sheetModule.exports);
  deps['../components/SelectionBottomSheet'] = sheetModule.exports;
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
  return { ...h, render, settle, route, calls, pickers, theme, button, field, nodes, removal: () => removal };
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
  const homes = h.nodes().find(n => n.type === 'FlatList');
  assert.equal(homes.props.data.some(row => row.value === String(id)), false);
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
  const homes = h.nodes().find(n => n.type === 'FlatList');
  homes.props.renderItem({ item: homes.props.data[0] }).props.onPress(); h.render();
  h.button('relationshipToHead').props.onPress(); await h.settle();
  const relations = h.nodes().find(n => n.type === 'FlatList');
  relations.props.renderItem({ item: relations.props.data.find(row => row.value === 'Daughter') }).props.onPress(); await h.settle();
  h.button('next').props.onPress(); await h.settle();
  h.field('civilStatus').props.onChangeText('Single'); await h.settle();
  h.button('next').props.onPress(); await h.settle();
  h.button('next').props.onPress(); await h.settle();
  h.button('next').props.onPress(); await h.settle();
}

test('Resident form double-save preserves one UUID/revision and exits only after successful local save', async t => {
  let finish; const pending = new Promise(resolve => { finish = resolve; });
  const h = await formHarness(t, 'ResidentForm', { storage: { saveResident: async (...args) => { await pending; return h.storage.saveResident(...args); } } });
  await fillResident(h);
  const save = h.button('save').props.onPress;
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
  await h.button('save').props.onPress(); await h.settle();
  assert.ok(JSON.stringify(h.render()).includes('Ana'));
  assert.equal(h.removal().blocked, true);
  assert.equal(h.calls.some(c => c.key === 'goBack'), false);
  fail = false; await h.button('save').props.onPress(); await h.settle();
  assert.equal(saves, 2); assert.equal(h.calls.filter(c => c.key === 'goBack').length, 1);
});

test('Next validates only the current step; Back retains identity and does not persist a partial request', async t => {
  const h = await formHarness(t);
  h.button('next').props.onPress(); await h.settle();
  assert.ok(JSON.stringify(h.render()).includes('residentStepIdentity'));
  assert.equal((await h.storage.getPendingChangeSummary()).residents, 0);
  await fillResident(h);
  assert.equal(h.nodes().some(n => n.type === 'Modal' && n.props.visible), false);
  h.button('back').props.onPress(); await h.settle();
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
  h.button('back').props.onPress(); await h.settle();
  h.button('back').props.onPress(); await h.settle();
  h.field('residentOccupation').props.onChangeText('Farmer'); await h.settle();
  h.button('residentEmployment').props.onPress(); await h.settle();
  const choices = h.nodes().find(n => n.type === 'FlatList');
  assert.deepEqual(choices.props.data.map(row => row.value), payload.resident_profile_choices.employment_status);
  choices.props.renderItem({ item: choices.props.data[0] }).props.onPress(); await h.settle();
  h.button('next').props.onPress(); await h.settle();
  assert.equal(h.nodes().some(n => n.type === 'TextInput' && n.props.accessibilityLabel === 'residentDisability'), false);
  h.button('residentPwd: yes').props.onPress(); await h.settle();
  h.field('residentDisability').props.onChangeText('Recorded disability'); await h.settle();
  h.button('next').props.onPress(); await h.settle();
  assert.ok(JSON.stringify(h.render()).includes('Farmer'));
  assert.ok(JSON.stringify(h.render()).includes('Recorded disability'));
  await h.button('save').props.onPress(); await h.settle();
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
  h.button('next').props.onPress(); await h.settle(); h.button('next').props.onPress(); await h.settle();
  await h.button('save').props.onPress(); await h.settle();
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

test('inline Review contains no technical request identifiers and Back returns to Step 4 without saving', async t => {
  const h = await formHarness(t); await fillResident(h);
  const contents = JSON.stringify(h.render());
  for (const forbidden of ['mobile_uuid', 'server_id', 'local_revision', 'sync_status', 'proposed_changes']) assert.equal(contents.includes(forbidden), false);
  assert.ok(contents.includes('residentSaveDeviceNote')); assert.ok(contents.includes('residentStepEducation'));
  assert.ok(contents.includes('Step 5 of 5'));
  assert.equal(h.button('residentSaveDevice'), undefined);
  h.button('back').props.onPress(); await h.settle();
  assert.ok(JSON.stringify(h.render()).includes('Step 4 of 5'));
  assert.equal(h.nodes().filter(n => n.type === 'Modal' && n.props.visible).length, 0);
  assert.equal((await h.storage.getPendingChangeSummary()).residents, 0); assert.equal(h.removal().blocked, true);
});

test('every finite selector selects immediately, marks its current row, and dismisses without mutation', async t => {
  const h = await formHarness(t);
  async function checkSelector(label, value) {
    h.button(label).props.onPress(); await h.settle();
    const modal = h.nodes().find(n => n.type === 'Modal');
    assert.equal(modal.props.visible, true); assert.equal(modal.props.animationType, 'slide');
    let list = h.nodes().find(n => n.type === 'FlatList');
    const item = list.props.data.find(row => row.value === value);
    const row = list.props.renderItem({ item });
    assert.equal(row.props.accessibilityRole, 'radio');
    row.props.onPress(); await h.settle();
    assert.equal(h.nodes().find(n => n.type === 'Modal').props.visible, false);
    assert.equal(h.button(label).props.accessibilityValue.text, value);
    for (const dismiss of ['cancel', 'backdrop', 'android']) {
      h.button(label).props.onPress(); await h.settle();
      list = h.nodes().find(n => n.type === 'FlatList');
      const selected = list.props.renderItem({ item: list.props.data.find(option => option.value === value) });
      assert.deepEqual(selected.props.accessibilityState, { selected: true, checked: true });
      assert.ok(h.nodes(selected).some(n => n.props.testID === 'selection-checkmark'));
      assert.equal(list.props.data.some(option => ['cancel', 'confirm', 'apply'].includes(option.value)), false);
      assert.equal(h.button('confirm'), undefined);
      if (dismiss === 'cancel') h.button('cancel').props.onPress();
      if (dismiss === 'backdrop') h.nodes().find(n => n.props.testID === 'selection-backdrop').props.onPress();
      if (dismiss === 'android') h.nodes().find(n => n.type === 'Modal').props.onRequestClose();
      await h.settle();
      assert.equal(h.button(label).props.accessibilityValue.text, value);
      assert.equal(h.nodes().find(n => n.type === 'Modal').props.visible, false);
    }
  }
  await checkSelector('relationshipToHead', 'Daughter');
  await fillResident(h);
  h.button('back').props.onPress(); await h.settle();
  h.button('back').props.onPress(); await h.settle();
  await checkSelector('residentEmployment', 'Employed');
  await checkSelector('residentEducationLevel', 'College');
  await checkSelector('residentEducationStatus', 'Graduate');
  assert.equal((await h.storage.getPendingChangeSummary()).residents, 0);
});

test('DOB remains one editable row with a 48dp calendar and non-deprecated mutation/dismiss callbacks', async t => {
  const h = await formHarness(t);
  await fillResident(h);
  for (let step = 0; step < 4; step++) { h.button('back').props.onPress(); await h.settle(); }
  const control = h.nodes().find(n => n.props.testID === 'resident-dob-control');
  assert.equal(control.props.style.flexDirection, 'row');
  const input = h.nodes(control).find(n => n.type === 'TextInput');
  const calendar = h.nodes(control).find(n => n.type === 'Pressable');
  assert.equal(input.props.style.minWidth, 0); assert.equal(input.props.style.flex, 1);
  assert.equal(calendar.props.style.width, 48); assert.equal(calendar.props.style.minHeight, 48);
  assert.equal(calendar.props.accessibilityLabel, 'residentBirthDateCalendar');
  input.props.onChangeText('19900101'); await h.settle();
  assert.equal(h.field('birthDate').props.value, '1990/01/01');
  h.button('residentBirthDateCalendar').props.onPress();
  const picker = h.pickers[0];
  assert.equal('onChange' in picker, false);
  assert.equal(picker.value.getFullYear(), 1990);
  assert.ok(picker.maximumDate <= new Date());
  picker.onDismiss(); await h.settle();
  assert.equal(h.field('birthDate').props.value, '1990/01/01');
  picker.onValueChange({}, new Date(1991, 1, 3)); await h.settle();
  assert.equal(h.field('birthDate').props.value, '1991/02/03');
  h.field('birthDate').props.onChangeText('29990101'); await h.settle();
  h.button('next').props.onPress(); await h.settle();
  assert.ok(JSON.stringify(h.render()).includes('Step 1 of 5'));
  assert.equal((await h.storage.getPendingChangeSummary()).residents, 0);
});

test('household sheet retains truthful loading, error and empty states without stale selectable rows', async t => {
  let finish;
  const pending = new Promise(resolve => { finish = resolve; });
  const loading = await formHarness(t, 'ResidentForm', { storage: { getResidentHouseholdOptions: () => pending } });
  loading.button('chooseHousehold').props.onPress(); await loading.settle();
  assert.ok(JSON.stringify(loading.render()).includes('loading'));
  assert.equal(loading.nodes().find(n => n.type === 'FlatList').props.data.length, 0);
  finish([]); await loading.settle();
  assert.ok(JSON.stringify(loading.render()).includes('residentHouseholdFirst'));
  const error = await formHarness(t, 'ResidentForm', { storage: { getResidentHouseholdOptions: async () => { throw new Error('private detail'); } } });
  error.button('chooseHousehold').props.onPress(); await error.settle();
  assert.ok(JSON.stringify(error.render()).includes('savedRecordsError'));
  assert.equal(JSON.stringify(error.render()).includes('private detail'), false);
  assert.equal(error.nodes().find(n => n.type === 'FlatList').props.data.length, 0);
  const empty = await formHarness(t);
  empty.button('chooseHousehold').props.onPress(); await empty.settle();
  empty.nodes().find(n => n.type === 'TextInput' && n.props.accessibilityLabel === 'residentHouseholdSearch').props.onChangeText('No such household');
  await empty.settle();
  assert.equal(empty.nodes().find(n => n.type === 'FlatList').props.data.length, 0);
  assert.equal(empty.nodes().find(n => n.type === 'FlatList').props.ListEmptyComponent.props.children[0], 'noMatchingRecords');
});

test('household sheet keeps authoritative head/headless/vacant context, search and selected identity', async t => {
  const h = await formHarness(t, 'ResidentForm', { payload: { ...payload, households: [
    { ...payload.households[0], current_head_name: 'Recorded Head' },
    { id: 2, purok_id: 1, household_no: '3', household_address: 'Headless home', is_active: true, is_vacant: false },
    { id: 3, purok_id: 1, household_no: '4', household_address: 'Vacant home', is_active: true, is_vacant: true },
  ] } });
  h.button('chooseHousehold').props.onPress(); await h.settle();
  let list = h.nodes().find(n => n.type === 'FlatList');
  assert.equal(list.props.data.length, 3);
  assert.ok(list.props.data.some(row => row.description.startsWith('Household Head: Recorded Head\n')));
  assert.ok(list.props.data.some(row => row.description.startsWith('Household Head: noDesignatedHead\n')));
  assert.ok(list.props.data.some(row => row.description.startsWith('Household Head: Vacant\n')));
  assert.ok(list.props.data.every(row => row.description.includes('home')));
  const search = h.nodes().find(n => n.type === 'TextInput' && n.props.accessibilityLabel === 'residentHouseholdSearch');
  search.props.onChangeText('Vacant'); await h.settle();
  list = h.nodes().find(n => n.type === 'FlatList');
  assert.equal(list.props.data.length, 1);
  list.props.renderItem({ item: list.props.data[0] }).props.onPress(); await h.settle();
  assert.equal(h.button('chooseHousehold').props.accessibilityValue.text, '4');
  assert.equal(h.nodes().find(n => n.type === 'Modal').props.visible, false);
  h.button('chooseHousehold').props.onPress(); await h.settle();
  list = h.nodes().find(n => n.type === 'FlatList');
  assert.equal(list.props.renderItem({ item: list.props.data[0] }).props.accessibilityState.checked, true);
  assert.equal(h.button('Create Household Request'), undefined);
});

test('Resident DOB and household-search placeholders use current theme colors in light and dark modes', async t => {
  for (const themeMode of ['light', 'dark']) {
    const h = await formHarness(t, 'ResidentForm', { themeMode });
    h.button('chooseHousehold').props.onPress(); await h.settle();
    const placeholders = h.nodes().filter(n => n.type === 'TextInput' && n.props.placeholder);
    assert.deepEqual(placeholders.map(n => n.props.accessibilityLabel).sort(), ['birthDate', 'residentHouseholdSearch']);
    for (const input of placeholders) {
      assert.equal(input.props.placeholderTextColor, h.theme.colors.placeholder);
      assert.equal(input.props.style.color, h.theme.colors.text);
      if (input.props.accessibilityLabel === 'residentHouseholdSearch')
        assert.equal(input.props.style.backgroundColor, h.theme.colors.inputBackground);
    }
    assert.equal(h.nodes().find(n => n.props.testID === 'resident-dob-control').props.style.backgroundColor, h.theme.colors.inputBackground);
    const option = h.nodes().find(n => n.type === 'FlatList').props.renderItem({ item: h.nodes().find(n => n.type === 'FlatList').props.data[0] });
    assert.ok(h.nodes(option).some(n => n.type === 'Text' && n.props.style.color === h.theme.colors.textMuted));
  }
  const copy = readFileSync(new URL('../src/i18n.ts', import.meta.url), 'utf8');
  assert.match(copy, /residentHouseholdHeadLine: 'Household Head: %\{head\}'/);
  assert.match(copy, /residentHouseholdHeadLine: 'Ulo sa panimalay: %\{head\}'/);
});

test('320px source-layout contract caps sheet height, reserves safe area, scrolls rows and keeps Cancel tertiary', async t => {
  const h = await formHarness(t);
  h.button('relationshipToHead').props.onPress(); await h.settle();
  const overlay = h.nodes().find(n => n.type === 'KeyboardAvoidingView');
  assert.equal(overlay.props.style.justifyContent, 'flex-end');
  assert.equal(overlay.props.style.backgroundColor, h.theme.colors.overlay);
  assert.equal(overlay.props.behavior, 'height');
  const sheet = h.nodes().find(n => n.props.accessibilityViewIsModal);
  assert.equal(sheet.props.style[1].height, 640 * 0.72);
  assert.equal(sheet.props.style[1].maxHeight, '72%');
  assert.equal(sheet.props.style[1].paddingBottom, 20);
  assert.ok(sheet.props.style[0].borderTopLeftRadius > 0);
  assert.equal(sheet.props.style[0].borderBottomLeftRadius, undefined);
  const list = h.nodes().find(n => n.type === 'FlatList');
  assert.equal(list.props.style.flex, 1); assert.equal(list.props.style.minHeight, 0);
  assert.equal(list.props.keyboardShouldPersistTaps, 'handled');
  const option = list.props.renderItem({ item: list.props.data[0] });
  assert.equal(option.props.style[0].minHeight, 48);
  assert.equal(option.props.style[0].borderRadius, undefined);
  assert.equal(h.nodes(option).find(n => n.type === 'View').props.style.minWidth, 0);
  const cancel = h.button('cancel');
  assert.equal(cancel.props.style.backgroundColor, undefined);
  assert.equal(cancel.props.style.borderWidth, undefined);
  assert.equal(cancel.props.style.minHeight, 48);
});

test('five-step progression, final primary Save and secondary Back preserve values without navigation writes', async t => {
  const h = await formHarness(t); await fillResident(h);
  for (let step = 5; step >= 1; step--) {
    assert.ok(JSON.stringify(h.render()).includes(`Step ${step} of 5`));
    assert.equal(Boolean(h.button('save')), step === 5);
    const primary = h.button(step === 5 ? 'save' : 'next');
    assert.equal(primary.props.style[2].backgroundColor, h.theme.colors.primary);
    assert.equal(primary.props.accessibilityState.busy, false);
    assert.equal(h.nodes().filter(n => n.props.style?.[0]?.height === 5).length, 5);
    assert.equal((await h.storage.getPendingChangeSummary()).residents, 0);
    if (step > 1) {
      assert.equal(h.button('back').props.style[0].backgroundColor, undefined);
      h.button('back').props.onPress(); await h.settle();
    }
  }
  assert.equal(h.field('firstName').props.value, 'Ana');
  const copy = readFileSync(new URL('../src/i18n.ts', import.meta.url), 'utf8');
  assert.match(copy, /save: 'Save'/); assert.match(copy, /residentStepProgress: 'Step %\{step\} of 5'/);
});
