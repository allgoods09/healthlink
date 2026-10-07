import assert from 'node:assert/strict';
import test from 'node:test';
import { createHomeHarness, nodes, textContent } from './homePresentationHarness.mjs';
import { fixture } from './residentDirectoryFixture.mjs';

const tick = () => new Promise(setImmediate);
const styleOf = (node) => Object.assign({}, ...[node.props.style].flat(Infinity).filter(Boolean));

function directory(t, options = {}) {
  const reads = [];
  const storage = options.storage ?? {
    hasBootstrapData: async () => { reads.push('ready'); return true; },
    getCurrentOfficialResidentCount: async () => { reads.push('residents'); return 305; },
    getCurrentOfficialHouseholdCount: async () => { reads.push('households'); return 89; },
  };
  const h = createHomeHarness({ ...options, storage, context: {
    assignment: { barangay: { id: 1, name: 'Pooc Oriental' }, purok: { id: 1, display_name: 'Purok 1' } },
    dataVersion: 1, isOnline: false, ...options.context,
  } });
  t.after(h.unmount);
  let tree;
  const render = () => (tree = h.render('Directory'));
  const settle = async () => { for (let i = 0; i < 5; i++) { render(); await tick(); } return render(); };
  const rows = () => nodes(tree).filter((node) => node.type === 'Pressable').slice(2);
  return { ...h, reads, render, settle, rows, tree: () => tree };
}

for (const locale of ['en', 'ceb']) {
  for (const mode of ['light', 'dark']) {
    test(`${locale}/${mode}: only neutral Residents/Households rows, truthful trailing counts and exact destinations`, async (t) => {
      const h = directory(t, { locale, mode });
      const tree = await h.settle();
      const i18n = h.load('i18n.ts').i18n;
      const theme = h.load('theme.ts')[`${mode}Theme`];
      assert.deepEqual(h.reads, ['ready', 'residents', 'households', 'ready']);
      assert.equal(h.rows().length, 2);
      for (const [index, key, count, route] of [[0, 'directoryResidents', 305, 'Residents'], [1, 'directoryHouseholds', 89, 'Households']]) {
        const row = h.rows()[index];
        assert.equal(row.props.accessibilityRole, 'button');
        assert.equal(row.props.accessibilityLabel, `${i18n.t(key)}: ${count}`);
        assert.equal(nodes(row).filter((node) => node.type === 'Ionicons').length, 0);
        const texts = nodes(row).filter((node) => node.type === 'Text');
        assert.deepEqual(texts.map(textContent), [i18n.t(key), String(count)]);
        assert.equal(styleOf(texts[1]).color, theme.colors.textMuted);
        assert.equal(styleOf(texts[1]).marginLeft, 'auto');
        assert.equal(styleOf(texts[0]).minWidth, 0);
        const style = styleOf(row);
        assert.equal(style.backgroundColor, theme.colors.surface);
        assert.equal(style.borderColor, theme.colors.border);
        assert.ok(style.borderRadius <= 8);
        assert.ok(style.minHeight >= 64 && style.minHeight <= 68);
        assert.ok(style.paddingVertical >= 18 && style.paddingVertical <= 20);
        assert.equal(style.flexWrap, 'wrap');
        assert.equal(style.height, undefined);
        row.props.onPress();
        assert.deepEqual(h.calls.navigation[index], [route]);
      }
      for (const key of ['cachedResidentNote', 'viewResidentRecords', 'viewHouseholdRecords', 'assignmentUnavailable']) {
        assert.ok(!textContent(tree).includes(i18n.t(key)));
      }
      const title = nodes(tree).find((node) => node.type === 'Text' && textContent(node) === i18n.t('directory'));
      assert.equal(styleOf(title).textAlign, 'center');
      assert.equal(styleOf(title).fontWeight, '400');
      assert.equal(nodes(tree).filter((node) => node.type === 'Image').length, 0);
      const scope = nodes(tree).find((node) => node.type === 'Text' && /Pooc Oriental\s+·\s+Purok 1/.test(textContent(node)));
      assert.ok(scope);
      const metadataStyle = styleOf(scope);
      assert.equal(metadataStyle.color, theme.colors.textMuted);
      assert.ok(metadataStyle.fontSize >= 12 && metadataStyle.fontSize <= 13);
      assert.equal(metadataStyle.fontWeight, '400');
      assert.equal(metadataStyle.backgroundColor, undefined);
      assert.equal(scope.props.onPress, undefined);
      const rendered = nodes(tree);
      assert.ok(rendered.indexOf(scope) < rendered.indexOf(h.rows()[0]));
    });
  }
}

test('real scoped SQLite count readers supply the displayed current official counts', async (t) => {
  const db = await fixture(t);
  const h = directory(t, { storage: db.storage });
  await h.settle();
  assert.deepEqual(h.rows().map((row) => nodes(row).filter((node) => node.type === 'Text').map(textContent)),
    [['Residents', '5'], ['Households', '4']]);
});

test('loading has no fabricated zero, and a genuine empty dataset renders two zero counts', async (t) => {
  let release;
  const h = directory(t, { storage: {
    hasBootstrapData: () => new Promise((resolve) => { release = resolve; }),
    getCurrentOfficialResidentCount: async () => 0,
    getCurrentOfficialHouseholdCount: async () => 0,
  } });
  h.render();
  assert.equal(h.rows().length, 0);
  assert.ok(!textContent(h.tree()).includes('Pooc Oriental'));
  assert.equal(nodes(h.tree()).filter((node) => node.type === 'ActivityIndicator').length, 1);
  release(true); await tick(); release(true);
  await h.settle();
  assert.deepEqual(h.rows().map((row) => nodes(row).filter((node) => node.type === 'Text').map(textContent)),
    [['Residents', '0'], ['Households', '0']]);
});

test('missing assignment and failure retain explanatory alerts, existing Retry reloads counts', async (t) => {
  let ready = false;
  let fail = false;
  const h = directory(t, { storage: {
    hasBootstrapData: async () => ready,
    getCurrentOfficialResidentCount: async () => { if (fail) throw Error('private detail'); return 2; },
    getCurrentOfficialHouseholdCount: async () => 1,
  } });
  await h.settle();
  const i18n = h.load('i18n.ts').i18n;
  assert.ok(textContent(h.tree()).includes(i18n.t('assignmentUnavailable')));
  assert.equal(h.rows().length, 0);
  assert.ok(!textContent(h.tree()).includes('Pooc Oriental'));
  ready = true; fail = true; h.context.dataVersion++;
  await h.settle();
  assert.ok(textContent(h.tree()).includes(i18n.t('savedRecordsError')));
  assert.ok(!textContent(h.tree()).includes('private detail'));
  assert.ok(!textContent(h.tree()).includes('Pooc Oriental'));
  fail = false;
  nodes(h.tree()).find((node) => node.props.accessibilityLabel === i18n.t('retry')).props.onPress();
  await h.settle();
  assert.equal(h.rows().length, 2);
});

test('ready metadata uses changed assignment labels, with no invented missing-label fallback', async (t) => {
  const h = directory(t, { context: { assignment: {
    barangay: { id: 9, name: 'Another Barangay' }, purok: { id: 7, display_name: 'Purok Seven' },
  } } });
  await h.settle();
  assert.match(textContent(h.tree()), /Another Barangay\s+·\s+Purok Seven/);
  h.context.assignment.barangay.name = '';
  await h.settle();
  assert.equal(h.rows().length, 2);
  assert.ok(!textContent(h.tree()).includes('Purok Seven'));
});

test('assignment/dataVersion invalidation and focus preserve stale-response protection', async (t) => {
  let release;
  let value = 1;
  const h = directory(t, { storage: {
    hasBootstrapData: async () => true,
    getCurrentOfficialResidentCount: () => value === 1 ? new Promise((resolve) => { release = resolve; }) : Promise.resolve(value),
    getCurrentOfficialHouseholdCount: async () => value,
  } });
  h.render(); await tick();
  value = 8; h.context.assignment.purok.id = 2; h.context.dataVersion++;
  await h.settle();
  release(999); await h.settle();
  assert.ok(h.rows()[0].props.accessibilityLabel.endsWith(': 8'));
  assert.ok(!textContent(h.tree()).includes('999'));
  h.setFocused(false); h.context.dataVersion++; h.render();
  assert.equal(h.rows().length, 0);
  h.setFocused(true); await h.settle();
  assert.equal(h.rows().length, 2);
});

for (const count of [0, 120]) {
  test(`Directory notification count ${count} reuses header and navigates only`, async (t) => {
    const h = directory(t, { context: { unreadNotificationCount: count } });
    await h.settle();
    const bell = nodes(h.tree()).find((node) => node.type === 'Pressable');
    const badge = nodes(bell).find((node) => node.type === 'Text');
    if (!count) assert.equal(badge, undefined);
    else {
      assert.equal(textContent(badge), '99+');
      assert.equal(styleOf(badge).position, 'absolute');
      assert.ok(bell.props.accessibilityLabel.includes('120'));
    }
    bell.props.onPress();
    assert.deepEqual(h.calls.navigation, [['Notifications']]);
  });
}

for (const pendingSyncCount of [0, 3]) {
  for (const confirmed of [false, true]) {
    test(`Directory reuses confirmed logout, pending=${pendingSyncCount}, confirm=${confirmed}`, async (t) => {
      const h = directory(t, { context: { pendingSyncCount } });
      h.context.requestConfirmation = async (request) => { h.calls.confirmations.push(request); return confirmed; };
      await h.settle();
      const i18n = h.load('i18n.ts').i18n;
      nodes(h.tree()).find((node) => node.props.accessibilityLabel === i18n.t('logout')).props.onPress();
      await tick();
      assert.equal(h.calls.logout, 1);
      assert.equal(h.calls.confirmations.length, 1);
      assert.equal(h.calls.confirmations[0].tone, pendingSyncCount ? 'warning' : 'danger');
      assert.equal(h.calls.confirmations[0].message, pendingSyncCount ? i18n.t('logoutWarningBody', { count: pendingSyncCount }) : i18n.t('logoutConfirmationBody'));
      assert.equal(h.calls.signOut, confirmed ? 1 : 0);
      assert.deepEqual(h.calls.navigation, []);
    });
  }
}
