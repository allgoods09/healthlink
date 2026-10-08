import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { householdDownload, householdUiHarness } from './householdUiHarness.mjs';
import { createHomeHarness } from './homePresentationHarness.mjs';

const style = node => Object.assign({}, ...[node.props.style].flat(Infinity).filter(Boolean));
const list = h => h.nodes().find(n => n.type === 'FlatList');
async function search(h, value) {
  h.input('hhVisitSearch').props.onChangeText(value); h.render(); h.flushTimers(); await h.settle();
}

for (const locale of ['en', 'ceb']) for (const dark of [false, true]) {
  test(`${locale}/${dark ? 'dark' : 'light'}: shared header, compact wrapping cards and localized facts`, async t => {
    const i18n = createHomeHarness({ locale }).load('i18n.ts').i18n;
    const payload = householdDownload();
    payload.households[0].household_no = 'B-02-Long-household';
    payload.households[0].base_snapshot.household_no = 'B-02-Long-household';
    payload.field_visits[0].recorded_by_name = 'Original recorder with a long full name';
    payload.field_visits[0].notes = 'n'.repeat(200);
    const h = await householdUiHarness(t, 'Visits', { payload, dark, i18n });
    const card = h.cards()[0];
    assert.ok(h.texts(card).includes('B-02-Long-household'));
    assert.ok(h.texts(card).includes(i18n.t('hhVisitDateLabel')));
    assert.ok(h.texts(card).includes(h.load('lib/format.ts').formatFriendlyDateTime(payload.field_visits[0].visited_at)));
    assert.ok(h.texts(card).replace(/\s+/g, ' ').includes(`${i18n.t('hhVisitBhwLabel')} Original recorder with a long full name`));
    assert.ok(h.texts(card).includes(i18n.t('hhVisitSynced')));
    assert.ok(h.texts(card).includes(i18n.t('photoCountLabel', { count: 1 })));
    assert.ok(h.texts(card).includes('n'.repeat(180)));
    assert.ok(!h.texts(card).includes('n'.repeat(181)));
    assert.equal(style(card).backgroundColor, h.theme.colors.surface);
    assert.equal(style(card).borderRadius, 8);
    assert.equal(style(card).elevation, undefined);
    const action = h.nodes(card).find(n => n.type === 'Pressable');
    assert.equal(action.props.accessibilityLabel, i18n.t('viewDetails'));
    assert.ok(style(action).minHeight >= 48);
    assert.equal(style(action).backgroundColor, undefined);
    action.props.onPress();
    assert.deepEqual(h.calls.at(-1).args, ['VisitForm', { localId: 1 }]);
    const title = h.nodes().find(n => n.type === 'Text' && h.texts(n) === i18n.t('visits'));
    assert.equal(style(title).textAlign, 'center'); assert.equal(style(title).fontWeight, '400');
    assert.equal(h.nodes().some(n => n.type === 'TopHeader'), false);
    assert.ok(h.nodes().some(n => n.type === 'View' && style(n).paddingTop === 24));
    assert.equal(style(h.button(i18n.t('startVisitNow'))).minHeight, 50);
    assert.equal(style(h.input(i18n.t('hhVisitSearch'))).borderRadius, 6);
    for (const n of h.nodes(card)) {
      assert.equal(n.props.numberOfLines, undefined);
      assert.equal(n.props.allowFontScaling, undefined);
      assert.equal(style(n).height, undefined);
    }
    assert.equal(style(card.props.children[0]).flexWrap, 'wrap');
    assert.equal(style(card.props.children.at(-1)).flexWrap, 'wrap');
  });
}

for (const count of [0, 2, 120]) test(`notification count ${count}: existing badge and navigation only`, async t => {
  const h = await householdUiHarness(t, 'Visits', { context: { unreadNotificationCount: count,
    syncNow: () => assert.fail('Must not sync'), refreshNotifications: () => assert.fail('Must not refresh') } });
  const bell = h.nodes().find(n => n.type === 'Pressable' && n.props.accessibilityLabel.startsWith('notifications'));
  const badge = h.nodes(bell).find(n => n.type === 'Text');
  if (count) assert.equal(badge.props.children[0], count > 99 ? '99+' : count);
  else assert.equal(badge, undefined);
  bell.props.onPress(); assert.deepEqual(h.calls.at(-1).args, ['Notifications']);
  h.button('sync').props.onPress(); assert.deepEqual(h.calls.at(-1).args, ['SyncTab']);
  h.button('startVisitNow').props.onPress(); assert.deepEqual(h.calls.at(-1).args, ['VisitForm']);
});

for (const locale of ['en', 'ceb']) for (const confirmed of [false, true]) {
  test(`${locale} logout confirmed=${confirmed}: shared pending same-account warning`, async t => {
    let signedOut = 0; const requests = [];
    const i18n = createHomeHarness({ locale }).load('i18n.ts').i18n;
    const h = await householdUiHarness(t, 'Visits', { i18n, context: { pendingSyncCount: 2,
      requestConfirmation: async request => { requests.push(request); return confirmed; },
      signOut: async () => { signedOut++; } } });
    h.button(i18n.t('logout')).props.onPress(); await h.settle();
    assert.equal(requests.length, 1); assert.equal(requests[0].tone, 'warning');
    assert.equal(requests[0].message, i18n.t('logoutWarningBody', { count: 2 }));
    assert.equal(signedOut, confirmed ? 1 : 0);
  });
}

test('search preserves debounce, typed value, whitespace/case normalization and literal percent/underscore', async t => {
  const payload = householdDownload(); payload.field_visits[0].notes = 'Literal %_ Note';
  const h = await householdUiHarness(t, 'Visits', { payload });
  h.input('hhVisitSearch').props.onChangeText('Missing'); h.render();
  assert.equal(list(h).props.data.length, 1, 'No filtering until debounce');
  h.input('hhVisitSearch').props.onChangeText('   LITERAL   %_  '); h.render(); h.flushTimers(); await h.settle();
  assert.equal(h.input('hhVisitSearch').props.value, '   LITERAL   %_  ');
  assert.equal(h.cards().length, 1);
  await search(h, '%other'); assert.equal(h.cards().length, 0); assert.match(h.texts(), /noMatchingRecords/);
  await search(h, '2'); assert.equal(h.cards().length, 1);
  const source = readFileSync(new URL('../src/screens/VisitsScreen.tsx', import.meta.url), 'utf8');
  assert.match(source, /}, 300\)/); assert.doesNotMatch(source, /Filter|fetch\(|syncNow|refreshNotifications/);
});

test('unknown recorder is honest, pending status is independent of official household, notes/count unchanged', async t => {
  const h = await householdUiHarness(t, 'Visits', { setup: async ({ storage }) => {
    await storage.saveVisit({ household_server_id: 1, visited_at: '2026-10-08', photos: [], notes: null }, 1);
  }, context: { user: { id: 1, name: 'Viewer must not become recorder' } } });
  const pending = h.cards().find(card => h.texts(card).includes('hhVisitPending'));
  assert.ok(pending); assert.match(h.texts(pending), /hhVisitBhwLabel\s+hhRecorderUnknown/);
  assert.match(h.texts(pending), /noNotesSaved|photoCountLabel/);
  assert.doesNotMatch(h.texts(pending), /Viewer must/);
});

for (const state of ['loading', 'error', 'refresh', 'empty']) {
  test(`${state}: original exceptional state and New Visit visibility preserved`, async t => {
    let failed = state === 'error';
    const h = await householdUiHarness(t, 'Visits', { storage: {
      hasHouseholdData: async () => state !== 'refresh',
      getVisits: () => state === 'loading' ? new Promise(() => {}) : failed ? Promise.reject(new Error('private error')) : Promise.resolve([]),
      getWaitingHouseholdVisits: async () => [],
    } });
    assert.match(h.texts(), new RegExp({ loading: 'loading', error: 'savedRecordsError', refresh: 'hhRefresh', empty: 'noRecentVisits' }[state]));
    assert.equal(Boolean(h.button('startVisitNow')), state === 'empty');
    assert.doesNotMatch(h.texts(), /private error/);
    if (state === 'error') { failed = false; h.button('retry').props.onPress(); await h.settle(); assert.match(h.texts(), /noRecentVisits/); }
  });
}

test('ordinary Show More remains 30 at a time and resets with search', async t => {
  const payload = householdDownload();
  payload.field_visits = Array.from({ length: 61 }, (_, i) => ({ ...payload.field_visits[0], id: i + 1, notes: `Record ${i}` }));
  const h = await householdUiHarness(t, 'Visits', { payload });
  assert.equal(h.cards().length, 30); h.button('hhShowMore').props.onPress(); await h.settle();
  assert.equal(h.cards().length, 60); h.button('hhShowMore').props.onPress(); await h.settle();
  assert.equal(h.cards().length, 61); assert.equal(h.button('hhShowMore'), undefined);
  await search(h, 'Record'); assert.equal(h.cards().length, 30);
});

test('retained request-parent records stay read-only with all states, review notes, photos and separate Show More', async t => {
  const retained = Array.from({ length: 6 }, (_, i) => ({ local_id: i + 10, household_no: `Request ${i}`,
    visited_at: '2026-10-07', notes: 'Retained notes', recorded_by_name: 'Legacy original', photos: [{}],
    waiting_status: ['waiting', 'approved', 'rejected', 'protected'][i % 4], verification_notes: 'Review notes' }));
  const h = await householdUiHarness(t, 'Visits', { storage: { getWaitingHouseholdVisits: async () => retained } });
  for (const state of ['Waiting', 'Approved', 'Rejected', 'Protected']) assert.match(h.texts(), new RegExp(`hhVisit${state}`));
  assert.match(h.texts(), /Review notes|Retained notes|Legacy original|photoCountLabel/);
  assert.equal(h.nodes().filter(n => n.type === 'Pressable' && n.props.accessibilityLabel === 'viewDetails').length, 0);
  assert.doesNotMatch(h.texts(), /Request 5/); h.button('hhShowMore').props.onPress(); await h.settle();
  assert.match(h.texts(), /Request 5/); assert.equal(h.button('hhShowMore'), undefined);
});

test('stale asynchronous reads and focus/assignment changes cannot reveal old history', async t => {
  let finish;
  const h = await householdUiHarness(t, 'Visits', { storage: { hasHouseholdData: async () => true,
    getVisits: () => new Promise(resolve => { finish = resolve; }), getWaitingHouseholdVisits: async () => [] } });
  const old = finish; h.context.assignment = { barangay: { id: 2 }, purok: { id: 3 } }; await h.settle();
  old([{ local_id: 100, household_no: 'Old scope', visited_at: '2026-10-07', photos: [] }]); await h.settle();
  assert.equal(h.cards().length, 0);
  finish([]); await h.settle(); assert.match(h.texts(), /noRecentVisits/);
  h.focus(false); h.render(); assert.equal(h.cards().length, 0); assert.equal(h.button('startVisitNow'), undefined);
});
