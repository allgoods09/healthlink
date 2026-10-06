import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { data } from './residentDirectoryFixture.mjs';
import { screenHarness } from './residentScreensHarness.mjs';

const tick = () => new Promise(setImmediate);
const deferred = () => { let resolve; const promise = new Promise(r => { resolve = r; }); return { resolve, promise }; };
const page = (ids, next = null) => ({ rows: ids.map(id => ({ local_id: id, server_id: id, first_name: 'Name', last_name: String(id),
  birth_date: '1990-01-01', sex: 'Male', household_no: '2', household_purok_id: 1 })), total: 100, next, invalidated: false });

test('Directory has two official-count cards, assignment context, offline notice and correct navigation', async t => {
  const h = await screenHarness(t, 'Directory');
  const cards = h.nodes().filter(n => n.type === 'MenuCard');
  assert.deepEqual(cards.map(c => [c.props.title, c.props.badge]), [['directoryResidents', '5'], ['directoryHouseholds', '4']]);
  cards.forEach(c => c.props.onPress()); assert.deepEqual(h.calls, [['Residents'], ['Households']]);
  assert.ok(JSON.stringify(h.render()).includes('Purok 1'));
  assert.ok(JSON.stringify(h.render()).includes('cachedResidentNote'));
  assert.equal(h.nodes().find(n => n.type === 'TopHeader').props.actionAccessibilityLabel, 'sync');
});

test('Directory assignment unavailable hides cards and query errors offer retry without fabricating counts', async t => {
  const h = await screenHarness(t, 'Directory', { storage: { hasBootstrapData: async () => false } });
  assert.equal(h.nodes().filter(n => n.type === 'MenuCard').length, 0);
  assert.ok(JSON.stringify(h.render()).includes('assignmentUnavailable'));
  const error = await screenHarness(t, 'Directory', { storage: { getCurrentOfficialResidentCount: async () => { throw new Error('private'); } } });
  assert.ok(error.button('retry')); assert.ok(!JSON.stringify(error.render()).includes('private'));
});

test('Resident list has simple official rows and no clinical/edit/DOB/relationship fields; actions navigate correctly', async t => {
  const h = await screenHarness(t, 'Residents');
  assert.equal(h.list().props.data.length, 5);
  const row = h.list().props.renderItem({ item: h.list().props.data[0] });
  const text = JSON.stringify(row);
  assert.ok(text.includes('Male')); assert.ok(text.includes('householdNo'));
  assert.ok(!text.includes('birth_date')); assert.ok(!text.includes('PhilPEN')); assert.ok(!text.includes('relationship_to_head'));
  const view = h.nodes(row).find(n => n.type === 'Pressable'); view.props.onPress();
  assert.equal(h.calls[0][0], 'ResidentDetails');
  h.button('addResidentRequest').props.onPress(); assert.equal(h.calls[1][0], 'ResidentForm');
  assert.ok(h.button('residentRequests (0)')); assert.ok(h.input().props.accessibilityLabel);
});

test('search debounces and retains input; stale first responses and old-page completions cannot replace/append', async t => {
  const oldPage = deferred(); const staleSearch = deferred(); const queries = [];
  const cursor = { offset: 50, criteria: '', version: 'one' };
  const h = await screenHarness(t, 'Residents', { storage: {
    getCurrentOfficialResidentsPage: async (criteria, continuation) => {
      queries.push(criteria.search);
      if (continuation) return oldPage.promise;
      if (criteria.search === 'C') return staleSearch.promise;
      return page(criteria.search === 'Cris' ? [3] : [1], cursor);
    },
  } });
  h.list().props.onEndReached();
  h.input().props.onChangeText('C'); await h.settle();
  assert.equal(queries.includes('C'), false);
  await h.flushDebounce();
  h.input().props.onChangeText('Cris'); await h.flushDebounce();
  assert.equal(h.input().props.value, 'Cris');
  assert.deepEqual(h.list().props.data.map(r => r.local_id), [3]);
  oldPage.resolve(page([99])); staleSearch.resolve(page([88])); await tick(); await h.settle();
  assert.deepEqual(h.list().props.data.map(r => r.local_id), [3]);
});

test('paging deduplicates repeated end events/rows, criteria reset, and reverting search restores usable paging', async t => {
  const cursor = { offset: 50, criteria: '', version: 'one' }; let appends = 0;
  const h = await screenHarness(t, 'Residents', { storage: {
    getCurrentOfficialResidentsPage: async (_, next) => { if (next) { appends++; return page([1, 2]); } return page([1], cursor); },
  } });
  h.list().props.onEndReached(); h.list().props.onEndReached(); await h.settle();
  assert.equal(appends, 1); assert.deepEqual(h.list().props.data.map(r => r.local_id), [1, 2]);
  h.input().props.onChangeText('Unfinished'); await h.settle();
  h.input().props.onChangeText(''); await h.settle();
  assert.deepEqual(h.list().props.data.map(r => r.local_id), [1]);
  h.list().props.onEndReached(); await h.settle(); assert.equal(appends, 2);
  h.button('filter').props.onPress(); h.render(); h.button('Female').props.onPress(); await h.settle();
  assert.deepEqual(h.list().props.data.map(r => r.local_id), [1]);
  assert.ok(h.button('Female').props.accessibilityState.selected);
});

test('Resident list distinguishes no assignment, no population, no matches and query errors/retry', async t => {
  const no = await screenHarness(t, 'Residents', { storage: { hasBootstrapData: async () => false } });
  assert.ok(JSON.stringify(no.list().props.ListEmptyComponent).includes('assignmentUnavailable'));
  const empty = await screenHarness(t, 'Residents', { storage: { getCurrentOfficialResidentCount: async () => 0,
    getCurrentOfficialResidentsPage: async () => ({ ...page([]), total: 0 }) } });
  assert.ok(JSON.stringify(empty.list().props.ListEmptyComponent).includes('noAssignedResidents'));
  const h = await screenHarness(t, 'Residents');
  h.input().props.onChangeText('No name'); await h.flushDebounce();
  assert.ok(JSON.stringify(h.list().props.ListEmptyComponent).includes('noResidentMatches'));
  const error = await screenHarness(t, 'Residents', { storage: { getCurrentOfficialResidentsPage: async () => { throw new Error('SQL'); } } });
  assert.ok(JSON.stringify(error.list().props.ListEmptyComponent).includes('savedRecordsError'));
  assert.ok(!JSON.stringify(error.render()).includes('SQL'));
});

test('Requests display rejection reason/read-only status without resubmission; official correction association retained', async t => {
  const payload = data(); payload.residents[0].verification_status = 'submitted';
  const h = await screenHarness(t, 'ResidentRequests', { payload });
  assert.equal(h.list().props.data.length, 2);
  const rejected = h.list().props.data.find(r => !r.server_id);
  const row = h.list().props.renderItem({ item: rejected });
  const text = JSON.stringify(row);
  assert.ok(text.includes('notApprovedResident')); assert.ok(text.includes('Check name'));
  assert.ok(!text.includes('editSavedRequest')); assert.ok(!text.includes('Resubmit'));
  h.nodes(row).find(n => n.type === 'Pressable').props.onPress();
  assert.deepEqual(h.calls[0], ['ResidentDetails', { localId: rejected.local_id, request: true }]);
});

test('official Detail sections/action gates preserve correction under-review and unsynced assessment resume', async t => {
  const payload = data(); payload.residents[0].verification_status = 'submitted';
  const h = await screenHarness(t, 'ResidentDetails', { payload, params: { localId: 1 }, storage: {
    getRiskAssessmentsForResident: async () => [{ local_id: 7, sync_status: 'pending_create' }],
  } });
  assert.ok(!h.button('requestResidentUpdate')); assert.ok(h.button('updateUnderReview').props.disabled);
  assert.ok(h.button('continueAssessment'));
  h.button('continueAssessment').props.onPress();
  assert.deepEqual(h.calls[0], ['RiskAssessmentForm', { residentLocalId: 1, riskAssessmentLocalId: 7 }]);
  const text = JSON.stringify(h.render());
  for (const key of ['identitySection', 'householdProfile', 'contactSection', 'demographicsSection', 'requestStatus']) assert.ok(text.includes(key));
  assert.ok(!text.includes('BMI')); assert.ok(!text.includes('Screening Status'));
});

test('eligible Detail Request Update works, underage PhilPEN is absent and stale/foreign IDs fail closed', async t => {
  const h = await screenHarness(t, 'ResidentDetails', { params: { localId: 1 } });
  assert.ok(h.button('requestResidentUpdate')); assert.ok(h.button('assessPhilpen'));
  h.button('requestResidentUpdate').props.onPress(); assert.deepEqual(h.calls[0], ['ResidentForm', { localId: 1 }]);
  const child = await screenHarness(t, 'ResidentDetails', { params: { localId: 4 } });
  assert.ok(child.button('requestResidentUpdate')); assert.ok(!child.button('assessPhilpen'));
  const foreign = await screenHarness(t, 'ResidentDetails', { params: { localId: 5 } });
  assert.ok(JSON.stringify(foreign.render()).includes('residentUnavailable')); assert.ok(!foreign.button('requestResidentUpdate'));
  await h.storage.verifyDatasetAssignment(1, 1, 2); h.context.dataVersion++; await h.settle();
  assert.ok(JSON.stringify(h.render()).includes('residentUnavailable'));
});

test('rejected new request detail remains explicitly nonofficial, reason visible and no edit/assessment/resubmit', async t => {
  const h = await screenHarness(t, 'ResidentDetails', { params: { localId: 11, request: true } });
  const text = JSON.stringify(h.render());
  assert.ok(text.includes('newResidentRequest')); assert.ok(text.includes('Check name'));
  assert.ok(!h.button('editSavedRequest')); assert.ok(!h.button('requestResidentUpdate')); assert.ok(!h.button('assessPhilpen'));
});

test('filter/sort native sheets dismiss with Android back; selected/actions accessible and styles wrap at narrow widths', async t => {
  const h = await screenHarness(t, 'Residents');
  h.button('filter').props.onPress(); h.render();
  const modal = h.nodes().find(n => n.type === 'Modal' && n.props.visible);
  assert.equal(modal.props.animationType, 'none'); assert.ok(modal.props.onRequestClose);
  assert.ok(h.button('clearFilters'));
  modal.props.onRequestClose(); h.render(); assert.equal(h.nodes().some(n => n.type === 'Modal' && n.props.visible), false);
  const buttons = h.nodes().filter(n => n.type === 'Pressable');
  assert.ok(buttons.every(n => n.props.accessibilityRole === 'button'));
  const action = h.button('addResidentRequest');
  assert.equal(action.props.style[0].minHeight, 48);
  assert.ok(!action.props.style[0].width);
});

test('load-more failure keeps rows with retry and dataVersion resets paging without stale append', async t => {
  let fail = true; const calls = []; const cursor = { offset: 50, version: 'one', criteria: '' };
  const h = await screenHarness(t, 'Residents', { storage: {
    getCurrentOfficialResidentsPage: async (_, next) => { calls.push(next); if (next && fail) throw new Error('SQL private');
      return next ? page([2]) : page([1], cursor); },
  } });
  h.list().props.onEndReached(); await h.settle();
  assert.deepEqual(h.list().props.data.map(r => r.local_id), [1]);
  assert.ok(JSON.stringify(h.list().props.ListFooterComponent).includes('savedRecordsError'));
  assert.ok(!JSON.stringify(h.render()).includes('SQL private'));
  fail = false;
  const retry = h.nodes(h.list().props.ListFooterComponent).find(n => n.type === 'Pressable');
  retry.props.onPress(); await h.settle();
  assert.deepEqual(h.list().props.data.map(r => r.local_id), [1, 2]);
  h.context.dataVersion++; await h.settle(); assert.deepEqual(h.list().props.data.map(r => r.local_id), [1]);
  assert.equal(calls.at(-1), undefined);
});

test('sort selection and clearing composed filters issue the same authorized query and reset list', async t => {
  const calls = [];
  const h = await screenHarness(t, 'Residents', { storage: { getCurrentOfficialResidentsPage: async criteria => {
    calls.push(criteria); return page([1]);
  } } });
  h.button('filter').props.onPress(); h.render();
  h.button('Female').props.onPress(); h.render(); h.button('60+').props.onPress(); await h.settle();
  assert.equal(calls.at(-1).sex, 'Female'); assert.equal(calls.at(-1).ageGroup, '60+');
  h.button('clearFilters').props.onPress(); await h.settle(); assert.equal(calls.at(-1).sex, undefined);
  h.nodes().find(n => n.type === 'Modal' && n.props.visible).props.onRequestClose(); h.render();
  h.button('sort: nameAsc').props.onPress(); h.render(); h.button('oldest').props.onPress(); await h.settle();
  assert.equal(calls.at(-1).sort, 'oldest'); assert.ok(h.button('sort: oldest').props.accessibilityState.selected);
});

test('Resident navigation destinations are registered and touched-screen copy exists in both locales', () => {
  const navigation = readFileSync(new URL('../src/navigation/AppNavigator.tsx', import.meta.url), 'utf8');
  for (const name of ['Residents', 'Households', 'ResidentRequests', 'ResidentDetails', 'ResidentForm', 'RiskAssessmentForm']) {
    assert.ok(navigation.includes(`name="${name}"`));
  }
  const copy = readFileSync(new URL('../src/i18n.ts', import.meta.url), 'utf8');
  const locales = [copy.slice(copy.indexOf('en: {'), copy.indexOf('ceb: {')), copy.slice(copy.indexOf('ceb: {'))];
  for (const screen of ['Directory', 'Residents', 'ResidentRequests', 'ResidentDetails']) {
    const source = readFileSync(new URL(`../src/screens/${screen}Screen.tsx`, import.meta.url), 'utf8');
    for (const match of source.matchAll(/i18n\.t\('([^']+)'/g)) {
      for (const locale of locales) assert.ok(new RegExp(`\\b${match[1]}:`).test(locale), `Missing ${match[1]}`);
    }
  }
});

test('open submitted request resolves to its one official profile after approval refresh without a navigation dead end', async t => {
  const payload = data(); payload.residents.at(-1).verification_status = 'submitted';
  const h = await screenHarness(t, 'ResidentDetails', { payload, params: { localId: 11, request: true } });
  assert.ok(JSON.stringify(h.render()).includes('newResidentRequest'));
  const approved = data(); approved.residents.at(-1).id = 55;
  approved.residents.at(-1).verification_status = 'approved'; approved.residents.at(-1).verification_notes = null;
  await h.storage.replaceBootstrapData(approved); h.context.dataVersion++; await h.settle();
  assert.ok(JSON.stringify(h.render()).includes('officialResident')); assert.ok(h.button('requestResidentUpdate'));
  assert.ok(!JSON.stringify(h.render()).includes('residentUnavailable'));
  assert.equal((await h.storage.getResidents()).filter(r => r.server_id === 55).length, 1);
  assert.equal((await h.storage.getResidentRequests()).length, 0);
});
