import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { test } from 'node:test';
import vm from 'node:vm';
import { storageHarness } from './storageHarness.mjs';

const require = createRequire(import.meta.url);
const ts = require('typescript');
const tick = () => new Promise(setImmediate);
const payload = { resident_contract_version: 1, user: { id: 1 }, assignment: { barangay: { id: 1 }, purok: { id: 1 } },
  server_time: '2026-10-06', resident_relationship_choices: ['Daughter', 'Son'],
  households: [{ id: 1, purok_id: 1, household_no: '2', household_address: 'Pilot home', is_active: true }],
  residents: [], field_visits: [], risk_assessments: [] };

// Execute the production form callbacks with controlled hooks/navigation and real
// SQLite. This is not a claim of native rendering or device-level interaction QA.
async function formHarness(t, name = 'ResidentForm', overrides = {}) {
  const h = await storageHarness();
  t.after(h.close);
  await h.storage.prepareDatasetForUser(1);
  await h.storage.replaceBootstrapData(payload);
  const hooks = []; let cursor = 0; let effects = []; let removal; const calls = [];
  const react = {
    createElement: (type, props, ...children) => ({ type, props: { ...props, children } }),
    useState(initial) { const i = cursor++; if (!(i in hooks)) hooks[i] = initial;
      return [hooks[i], value => { hooks[i] = typeof value === 'function' ? value(hooks[i]) : value; }]; },
    useRef(initial) { const i = cursor++; return hooks[i] ??= { current: initial }; },
    useEffect(callback, deps) { const i = cursor++; const old = hooks[i];
      if (!old || deps.some((d, n) => !Object.is(d, old.deps[n]))) effects.push(() => { old?.cleanup?.(); hooks[i].cleanup = callback(); });
      hooks[i] = { deps, cleanup: old?.cleanup }; },
  };
  const route = { params: name === 'HouseholdForm' ? { returnToResident: true } : {} };
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
    '../hooks/useKeyboardAwareScroll': { useKeyboardAwareScroll: () => ({}) },
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
  function nodes(node = tree) { if (!node || typeof node !== 'object') return []; if (Array.isArray(node)) return node.flatMap(n => nodes(n));
    return [node, ...nodes(node.props?.children)]; }
  function button(label) { return nodes().find(n => n.type === 'Pressable' && JSON.stringify(n.props.children).includes(label)); }
  function field(label) { const all = nodes(); const index = all.findIndex(n => n.type === 'Text' && n.props.children[0] === label);
    return all.slice(index + 1).find(n => n.type === 'TextInput'); }
  await settle();
  return { ...h, render, settle, route, calls, button, field, nodes, removal: () => removal };
}

test('Resident form requires explicit sex/civil status/relationship and only retains the legitimate citizenship default', async t => {
  const h = await formHarness(t);
  assert.equal(h.field('civilStatus').props.value, '');
  assert.equal(h.field('religion').props.value, '');
  assert.equal(h.field('citizenship').props.value, 'Filipino');
  const text = JSON.stringify(h.render());
  assert.ok(text.includes('Choose relationship'));
  assert.ok(!text.includes('Propose as household head'));
  assert.ok(!h.nodes().some(n => n.type === 'Switch'));
});

test('separate Household request handoff preserves Resident form values and safely preselects the returned local household', async t => {
  const h = await formHarness(t);
  h.field('firstName').props.onChangeText('Unsaved Ana');
  h.render();
  h.button('Create Household Request').props.onPress();
  assert.deepEqual(h.calls.find(c => c.key === 'navigate').args, ['HouseholdForm', { returnToResident: true }]);
  const id = await h.storage.saveHousehold({ purok_id: 1, household_no: '3', household_address: 'Created home',
    is_active: true, is_social_aid_beneficiary: false }, 1);
  h.route.params.createdHouseholdLocalId = id;
  await h.settle();
  assert.equal(h.field('firstName').props.value, 'Unsaved Ana');
  assert.ok(JSON.stringify(h.render()).includes('Propose as household head'));
  assert.equal(h.route.params.createdHouseholdLocalId, undefined);
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
  assert.equal(confirmations[0].message, 'Unsaved changes will be lost.');
});

test('Household handoff double-tap creates one request and returns its stable local ID with merge semantics', async t => {
  let finish; const confirmation = new Promise(resolve => { finish = resolve; });
  const h = await formHarness(t, 'HouseholdForm', { requestConfirmation: () => confirmation });
  h.field('householdNo').props.onChangeText('9'); h.field('householdAddress').props.onChangeText('Created home'); h.render();
  const save = h.button('save').props.onPress;
  const first = save(); const second = save(); finish(true); await Promise.all([first, second]);
  const rows = await h.storage.getHouseholds();
  assert.equal(rows.length, 2);
  const call = h.calls.find(c => c.key === 'popTo');
  assert.deepEqual(call.args, ['ResidentForm', { createdHouseholdLocalId: rows.find(r => r.household_no === '9').local_id }, { merge: true }]);
});

test('Household handoff storage failure preserves entered values and allows retry without navigation', async t => {
  let fail = true;
  const h = await formHarness(t, 'HouseholdForm', { storage: { saveHousehold: async () => { if (fail) throw new Error('Unable to save'); return 7; } } });
  h.field('householdNo').props.onChangeText('9'); h.field('householdAddress').props.onChangeText('Retained address'); h.render();
  await h.button('save').props.onPress(); h.render();
  assert.equal(h.field('householdAddress').props.value, 'Retained address');
  assert.equal(h.calls.some(c => c.key === 'popTo'), false);
  assert.equal(h.button('save').props.disabled, false);
  fail = false; await h.button('save').props.onPress();
  assert.equal(h.calls.find(c => c.key === 'popTo').args[1].createdHouseholdLocalId, 7);
});

async function fillResident(h) {
  for (const [label, value] of [['firstName', 'Ana'], ['lastName', 'Santos'], ['birthDate', '1990/01/01'],
    ['birthPlace', 'Tubigon'], ['civilStatus', 'Single']]) h.field(label).props.onChangeText(value);
  h.button('Female').props.onPress(); h.render();
  const homes = h.nodes().find(n => n.type === 'FlatList' && n.props.data?.[0]?.household_no);
  homes.props.renderItem({ item: homes.props.data[0] }).props.onPress(); h.render();
  const relations = h.nodes().find(n => n.type === 'FlatList' && n.props.data?.[0] === 'Daughter');
  relations.props.renderItem({ item: 'Daughter' }).props.onPress(); await h.settle();
}

test('Resident form double-save preserves one UUID/revision and exits only after successful local save', async t => {
  let finish; const confirmation = new Promise(resolve => { finish = resolve; });
  const h = await formHarness(t, 'ResidentForm', { requestConfirmation: () => confirmation });
  await fillResident(h);
  const save = h.button('save').props.onPress;
  const first = save(); const second = save(); h.render();
  assert.equal(h.button('Saving...').props.disabled, true);
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
  assert.equal(h.field('firstName').props.value, 'Ana');
  assert.equal(h.removal().blocked, true);
  assert.equal(h.calls.some(c => c.key === 'goBack'), false);
  fail = false; await h.button('save').props.onPress(); await h.settle();
  assert.equal(saves, 2); assert.equal(h.calls.filter(c => c.key === 'goBack').length, 1);
});
