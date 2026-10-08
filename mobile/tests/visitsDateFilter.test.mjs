import assert from 'node:assert/strict';
import { test } from 'node:test';
import { householdUiHarness } from './householdUiHarness.mjs';
import { createHomeHarness } from './homePresentationHarness.mjs';

const date = (day, hour = 12, minute = 0) => new Date(2026, 9, day, hour, minute);
const visit = (id, day, extra = {}) => ({ local_id: id, household_no: `000${id}`, visited_at: date(day).toISOString(),
  notes: 'Literal %_ note', photos: [], recorded_by_name: 'Original BHW', sync_status: 'synced', ...extra });
const rows = () => [visit(1, 1), visit(2, 3, { sync_status: 'pending_create' }), visit(3, 5), visit(4, 7)];
const modal = h => h.nodes().find(n => n.type === 'Modal');
const list = h => h.nodes().find(n => n.type === 'FlatList');
const ids = h => list(h).props.data.map(row => row.local_id);
const style = node => Object.assign({}, ...[node.props.style].flat(Infinity).filter(Boolean));
const filter = h => h.nodes().find(n => n.type === 'Pressable' && ['hhVisitFilter', 'hhVisitFilterActive'].includes(n.props.accessibilityLabel));
async function open(h) { filter(h).props.onPress(); await h.settle(); }
async function choose(h, field, value, event = 'set') {
  h.nodes().find(n => n.type === 'Pressable' && n.props.accessibilityLabel.startsWith(`${field === 'from' ? 'hhVisitFrom' : 'hhVisitTo'}:`)).props.onPress();
  h.pickers.at(-1).onChange({ type: event }, value); await h.settle();
}
async function apply(h) { h.button('hhVisitApply').props.onPress(); await h.settle(); }
async function range(h, from, to) { await open(h); if (from) await choose(h, 'from', from); if (to) await choose(h, 'to', to); await apply(h); }
async function harness(t, options = {}) {
  let reads = 0;
  const h = await householdUiHarness(t, 'Visits', { ...options, storage: {
    getVisits: async () => { reads++; return rows(); }, getWaitingHouseholdVisits: async () => [], ...options.storage,
  } });
  return { ...h, reads: () => reads };
}

for (const [name, from, to, expected] of [
  ['From only', date(3), null, [2, 3, 4]], ['To only', null, date(3), [1, 2]],
  ['closed inclusive interval', date(1), date(5), [1, 2, 3]], ['same day', date(3), date(3), [2]],
  ['neither date', null, null, [1, 2, 3, 4]],
]) test(`${name}: actual picker selections/Apply filter before pagination`, async t => {
  const h = await harness(t); await range(h, from, to);
  assert.deepEqual(ids(h), expected); assert.equal(modal(h), undefined);
  assert.equal(filter(h).props.accessibilityState.selected, Boolean(from || to));
  assert.equal(h.reads(), 1, 'No data refresh when applying');
  const visible = h.texts(h.cards());
  if (expected.includes(2)) assert.match(visible, /hhVisitPending/);
  if (expected.includes(1)) assert.match(visible, /hhVisitSynced/);
  assert.match(visible, /Original BHW/);
});

test('includes whole final day, not created/updated timestamps', async t => {
  const data = [visit(1, 5, { visited_at: date(5, 0, 0).toISOString(), updated_at: date(20).toISOString() }),
    visit(2, 5, { visited_at: new Date(2026, 9, 5, 23, 59, 59, 999).toISOString() }), visit(3, 6)];
  const h = await harness(t, { storage: { getVisits: async () => data } });
  await range(h, date(5), date(5)); assert.deepEqual(ids(h), [1, 2]);
});

for (const tz of ['Asia/Manila', 'America/Los_Angeles', 'UTC']) {
  test(`${tz}: local-day matching agrees with display across offsets, UTC midnight and DST`, async t => {
    const h = await harness(t); const match = h.load('screens/VisitsScreen.tsx').matchesVisitDateRange;
    const format = h.load('lib/format.ts').formatFriendlyDateTime;
    const original = process.env.TZ;
    try {
      process.env.TZ = tz;
      const cases = ['2026-10-05T23:59:59-08:00', '2026-10-05T00:15:00+08:00',
        '2026-10-05', '2026-11-01T01:30:00-07:00', '2026-11-01T01:30:00-08:00'];
      for (const timestamp of cases) {
        const recorded = new Date(timestamp);
        const selected = new Date(recorded.getFullYear(), recorded.getMonth(), recorded.getDate());
        assert.equal(match(timestamp, { from: selected, to: selected }), true, `${timestamp} in ${tz}`);
        const next = new Date(selected); next.setDate(next.getDate() + 1);
        assert.equal(match(timestamp, { from: next, to: null }), false);
        assert.ok(format(timestamp).includes(new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric', year: 'numeric' }).format(selected)));
      }
      const chosen = date(5);
      assert.equal(match('2026-10-05T23:59:59-07:00', { from: chosen, to: chosen }), tz === 'America/Los_Angeles');
      assert.equal(match('2026-10-05T00:15:00+08:00', { from: chosen, to: chosen }), tz === 'Asia/Manila');
    } finally { if (original === undefined) delete process.env.TZ; else process.env.TZ = original; }
  });
}

test('draft choices never affect results/indicator; invalid Apply preserves old filter and stays open', async t => {
  const h = await harness(t); await range(h, date(1), date(3));
  await open(h); await choose(h, 'from', date(7));
  assert.deepEqual(ids(h), [1, 2]); assert.equal(filter(h).props.accessibilityState.selected, true);
  await apply(h); assert.equal(modal(h).props.visible, true); assert.deepEqual(ids(h), [1, 2]);
  assert.match(h.texts(modal(h)), /hhVisitInvalidRange/);
  assert.ok(h.nodes(modal(h)).some(n => n.props.accessibilityRole === 'alert'));
  await choose(h, 'to', date(7)); await apply(h); assert.deepEqual(ids(h), [4]);
});

for (const dismissal of ['close', 'back', 'backdrop', 'escape']) test(`${dismissal}: unapplied edits discarded and old bounds restored on reopen`, async t => {
  const h = await harness(t); await range(h, date(1), date(3)); await open(h); await choose(h, 'to', date(7));
  if (dismissal === 'close') h.button('close').props.onPress();
  if (dismissal === 'back') modal(h).props.onRequestClose();
  if (dismissal === 'backdrop') h.button('cancel').props.onPress();
  if (dismissal === 'escape') h.nodes(modal(h)).find(n => n.props.accessibilityViewIsModal).props.onAccessibilityEscape();
  await h.settle(); assert.equal(modal(h), undefined); assert.deepEqual(ids(h), [1, 2]);
  await open(h);
  const end = h.nodes(modal(h)).find(n => n.type === 'Pressable' && n.props.accessibilityLabel.startsWith('hhVisitTo:'));
  assert.match(end.props.accessibilityLabel, /2026\/10\/03/);
});

test('Clear restores date-unfiltered results but preserves search and database rows', async t => {
  const h = await harness(t); const before = await h.db.getAllAsync('SELECT * FROM field_visits');
  h.input('hhVisitSearch').props.onChangeText('  Literal  %_ '); h.render(); h.flushTimers(); await h.settle();
  await range(h, date(3), date(3)); assert.deepEqual(ids(h), [2]);
  await open(h); h.button('clearFilters').props.onPress(); await h.settle();
  assert.deepEqual(ids(h), [1, 2, 3, 4]); assert.equal(filter(h).props.accessibilityState.selected, false);
  assert.equal(h.input('hhVisitSearch').props.value, '  Literal  %_ '); assert.equal(modal(h), undefined);
  assert.deepEqual(await h.db.getAllAsync('SELECT * FROM field_visits'), before); assert.equal(h.reads(), 1);
});

test('search keeps 300ms debounce and date conjunction; applying dates does not clear pending typed input', async t => {
  const h = await harness(t); await range(h, date(3), date(5));
  h.input('hhVisitSearch').props.onChangeText('0003'); h.render(); assert.deepEqual(ids(h), [2, 3]);
  h.flushTimers(); await h.settle(); assert.deepEqual(ids(h), [3]);
  await open(h); await choose(h, 'to', date(7)); await apply(h);
  assert.equal(h.input('hhVisitSearch').props.value, '0003'); assert.deepEqual(ids(h), [3]);
  h.input('hhVisitSearch').props.onChangeText('%other'); h.render(); h.flushTimers(); await h.settle();
  assert.deepEqual(ids(h), []); assert.match(h.texts(), /hhVisitNoSearchDateMatches/);
  assert.equal(filter(h).props.accessibilityState.selected, true);
});

test('malformed/missing dates stay unfiltered; active bounds exclude rather than invent dates for both datasets', async t => {
  const bad = ['', null, undefined, 'not a date', '2026', '2026-02-30', '2026-13-01', '2026-10-05Tbad'];
  const data = [...bad.map((visited_at, i) => visit(i + 10, 5, { visited_at })), visit(1, 5)];
  const retained = data.map(v => ({ ...v, waiting_status: 'rejected', verification_notes: 'Review' }));
  const h = await harness(t, { storage: { getVisits: async () => data, getWaitingHouseholdVisits: async () => retained } });
  assert.equal(ids(h).length, 9); await range(h, date(1), date(7)); assert.deepEqual(ids(h), [1]);
  assert.match(h.texts(), /hhVisitRejected|Review/);
  const match = h.load('screens/VisitsScreen.tsx').matchesVisitDateRange;
  for (const value of bad) {
    assert.equal(match(value, { from: null, to: null }), true);
    assert.equal(match(value, { from: date(1), to: null }), false);
    assert.equal(match(value, { from: null, to: date(7) }), false);
  }
});

test('retained states/grouping remain read-only and use filtered pagination counts', async t => {
  const retained = Array.from({ length: 9 }, (_, i) => ({ ...visit(i + 10, i < 7 ? 3 : 7),
    waiting_status: ['waiting', 'approved', 'rejected', 'protected'][i % 4], verification_notes: `Review ${i}` }));
  const h = await harness(t, { storage: { getVisits: async () => [], getWaitingHouseholdVisits: async () => retained } });
  await range(h, date(3), date(3));
  for (const state of ['Waiting', 'Approved', 'Rejected', 'Protected']) assert.match(h.texts(), new RegExp(`hhVisit${state}`));
  assert.doesNotMatch(h.texts(), /hhVisitNoDateMatches/);
  assert.doesNotMatch(h.texts(), /Review 5/); h.button('hhShowMore').props.onPress(); await h.settle();
  assert.match(h.texts(), /Review 6/); assert.doesNotMatch(h.texts(), /Review 7/); assert.equal(h.button('hhShowMore'), undefined);
  assert.equal(h.nodes().some(n => n.props.accessibilityLabel === 'viewDetails'), false);
  await open(h); h.button('clearFilters').props.onPress(); await h.settle(); assert.doesNotMatch(h.texts(), /Review 5/);
});

test('ordinary filtered Show More counts/reset; applying does not reorder or mutate source arrays', async t => {
  const data = Array.from({ length: 100 }, (_, i) => visit(i + 1, i < 65 ? 3 : 7)); const before = structuredClone(data);
  const h = await harness(t, { storage: { getVisits: async () => data } });
  h.button('hhShowMore').props.onPress(); await h.settle(); assert.equal(ids(h).length, 60);
  await range(h, date(3), date(3)); assert.equal(ids(h).length, 30);
  h.button('hhShowMore').props.onPress(); await h.settle(); assert.equal(ids(h).length, 60);
  h.button('hhShowMore').props.onPress(); await h.settle(); assert.equal(ids(h).length, 65);
  assert.equal(h.button('hhShowMore'), undefined); assert.deepEqual(ids(h), data.slice(0, 65).map(v => v.local_id));
  assert.deepEqual(data, before);
});

test('date no-match versus empty saved history stays truthful', async t => {
  const h = await harness(t); await range(h, date(20), null);
  assert.equal(ids(h).length, 0); assert.match(h.texts(), /hhVisitNoDateMatches/);
  const empty = await harness(t, { storage: { getVisits: async () => [] } });
  await range(empty, date(20), null); assert.match(empty.texts(), /noRecentVisits/);
  assert.doesNotMatch(empty.texts(), /hhVisitNoDateMatches/);
});

test('dismissed/late native picker cannot change reopened draft, blurred scope, or unmounted screen', async t => {
  const h = await harness(t); await open(h); await choose(h, 'from', date(3), 'dismissed');
  await apply(h); assert.equal(filter(h).props.accessibilityState.selected, false);
  await open(h); h.nodes().find(n => n.type === 'Pressable' && n.props.accessibilityLabel.startsWith('hhVisitFrom:')).props.onPress();
  const old = h.pickers.at(-1); modal(h).props.onRequestClose(); await h.settle(); await open(h);
  old.onChange({ type: 'set' }, date(3)); await h.settle(); await apply(h); assert.deepEqual(ids(h), [1, 2, 3, 4]);
  await open(h); await choose(h, 'from', date(3)); h.context.assignment = { barangay: { id: 2 }, purok: { id: 3 } }; await h.settle();
  assert.equal(modal(h), undefined);
  await open(h); h.nodes().find(n => n.type === 'Pressable' && n.props.accessibilityLabel.startsWith('hhVisitFrom:')).props.onPress();
  const pending = h.pickers.at(-1); h.unmount(); pending.onChange({ type: 'set' }, date(20));
  assert.ok(h.calls.some(c => c.key === 'dismissPicker'));
});

for (const locale of ['en', 'ceb']) for (const dark of [false, true]) {
  test(`${locale}/${dark ? 'dark' : 'light'}: square icon, localized controls, accessible active state, neutral scrollable modal`, async t => {
    const i18n = createHomeHarness({ locale }).load('i18n.ts').i18n;
    const h = await harness(t, { i18n, dark });
    const control = h.button(i18n.t('hhVisitFilter')); assert.equal(control.props.accessibilityRole, 'button');
    assert.equal(style(control).width, style(control).height); assert.ok(style(control).width >= 48);
    assert.equal(style(control).borderRadius, 6); assert.equal(style(control).backgroundColor, h.theme.colors.surface);
    assert.equal(style(h.input(i18n.t('hhVisitSearch'))).flex, 1);
    control.props.onPress(); await h.settle(); assert.equal(modal(h).props.visible, true);
    assert.ok(h.calls.some(c => c.key === 'dismissKeyboard'));
    const panel = h.nodes(modal(h)).find(n => n.props.accessibilityViewIsModal);
    assert.equal(style(panel).backgroundColor, h.theme.colors.surface); assert.equal(style(panel).height, undefined);
    assert.ok(h.nodes(panel).some(n => n.type === 'ScrollView'));
    for (const key of ['hhVisitFilter', 'hhVisitDateRange', 'hhVisitFrom', 'hhVisitTo', 'hhVisitSelectDate', 'hhVisitApply', 'clearFilters']) {
      assert.ok(h.texts(panel).includes(i18n.t(key)), key); assert.ok(!i18n.t(key).includes('missing'));
    }
    for (const action of ['hhVisitApply', 'clearFilters']) assert.ok(style(h.button(i18n.t(action))).minHeight >= 48);
    const background = () => h.nodes().find(n => 'accessibilityElementsHidden' in n.props);
    assert.equal(background().props.importantForAccessibility, 'no-hide-descendants');
    assert.equal(background().props.accessibilityElementsHidden, true);
    h.nodes(panel).find(n => n.type === 'Pressable' && n.props.accessibilityLabel.startsWith(`${i18n.t('hhVisitFrom')}:`)).props.onPress();
    h.pickers.at(-1).onChange({ type: 'set' }, date(3)); await h.settle();
    h.button(i18n.t('hhVisitApply')).props.onPress(); await h.settle();
    assert.equal(h.button(i18n.t('hhVisitFilterActive')).props.accessibilityState.selected, true);
    assert.ok(h.nodes(h.button(i18n.t('hhVisitFilterActive'))).some(n => style(n).backgroundColor === h.theme.colors.primary));
    assert.equal(background().props.importantForAccessibility, 'auto');
    assert.equal(background().props.accessibilityElementsHidden, false);
  });
}

test('iOS uses installed native component and applies only selected draft', async t => {
  const h = await harness(t, { platform: 'ios', dark: true }); await open(h);
  h.nodes().find(n => n.type === 'Pressable' && n.props.accessibilityLabel.startsWith('hhVisitFrom:')).props.onPress(); await h.settle();
  const picker = h.nodes().find(n => n.type === 'DateTimePicker'); assert.equal(picker.props.mode, 'date');
  assert.equal(picker.props.themeVariant, 'dark'); picker.props.onChange({ type: 'set' }, date(3)); await h.settle();
  assert.deepEqual(ids(h), [1, 2, 3, 4]); await apply(h); assert.deepEqual(ids(h), [2, 3, 4]);
  picker.props.onChange({ type: 'set' }, date(20)); await h.settle(); assert.deepEqual(ids(h), [2, 3, 4]);
});

test('New Visit/details/Sync remain their original navigation-only destinations with active dates', async t => {
  const h = await harness(t, { context: { syncNow: () => assert.fail('No sync') } }); await range(h, date(1), date(1));
  h.button('startVisitNow').props.onPress(); assert.deepEqual(h.calls.at(-1).args, ['VisitForm']);
  h.button('sync').props.onPress(); assert.deepEqual(h.calls.at(-1).args, ['SyncTab']);
  h.nodes(h.cards()[0]).find(n => n.props.accessibilityLabel === 'viewDetails').props.onPress();
  assert.deepEqual(h.calls.at(-1).args, ['VisitForm', { localId: 1 }]);
});
