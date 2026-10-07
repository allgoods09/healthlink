import assert from 'node:assert/strict';
import test from 'node:test';
import { createHomeHarness, nodes, textContent } from './homePresentationHarness.mjs';

const tick = () => new Promise(setImmediate);
const styleOf = (node) => Object.assign({}, ...[node.props.style].flat(Infinity).filter(Boolean));
const buttons = (tree) => nodes(tree).filter((n) => n.type === 'Pressable');
function sync(options = {}) {
  let uploads = 0;
  const h = createHomeHarness({ ...options, context: {
    isOnline: true, isSyncing: false, statusMessage: null,
    syncNow: () => { uploads++; }, ...options.context,
  } });
  return { ...h, render: () => h.render('Sync'), uploads: () => uploads };
}

for (const locale of ['en', 'ceb']) {
  for (const mode of ['light', 'dark']) {
    test(`${locale}/${mode}: stacked truthful facts and a full-width primary manual action`, () => {
      const h = sync({ locale, mode, context: { pendingSyncCount: 3, lastSyncAt: '2026-10-07T13:45:00Z' } });
      const tree = h.render();
      const i18n = h.load('i18n.ts').i18n;
      const theme = h.load('theme.ts')[`${mode}Theme`];
      const texts = nodes(tree).filter((n) => n.type === 'Text').map(textContent);
      const date = h.load('lib/format.ts').formatFriendlyDateTime(h.context.lastSyncAt);
      for (const value of [i18n.t('syncPendingChanges'), '3', i18n.t('lastSync'), date,
        i18n.t('syncConnection'), i18n.t('online'), i18n.t('assignment')]) assert.ok(texts.includes(value), value);
      assert.match(textContent(tree), /Pooc Oriental\s+·\s+Purok 2/);
      const controls = buttons(tree);
      assert.equal(controls.length, 3);
      const action = controls[2];
      assert.equal(action.props.accessibilityLabel, i18n.t('syncNow'));
      assert.equal(action.props.onPress, h.context.syncNow);
      assert.equal(action.props.disabled, false);
      assert.equal(styleOf(action).backgroundColor, theme.colors.primary);
      assert.equal(styleOf(action).borderRadius, 6);
      assert.ok(styleOf(action).minHeight >= 48);
      assert.equal(styleOf(action).alignSelf, undefined);
      assert.equal(styleOf(nodes(action).find((n) => n.type === 'Text')).color, theme.colors.textOnPrimary);
      assert.equal(nodes(action).filter((n) => n.type === 'Ionicons').length, 0);
      assert.equal(h.uploads(), 0);
      action.props.onPress();
      assert.equal(h.uploads(), 1);
      for (const key of ['syncWorkspaceTitle', 'syncWorkspaceBody', 'currentStatus']) {
        assert.ok(!textContent(tree).includes(i18n.t(key)));
      }
      for (const key of ['dataProtectionTitle', 'dataProtectionBody', 'devicePolicyTitle', 'devicePolicyBody']) {
        assert.ok(textContent(tree).includes(i18n.t(key)));
      }
      assert.equal(nodes(tree).filter((n) => n.type === 'MenuCard' || n.type === 'TopHeader').length, 0);
      const scroll = nodes(tree).find((n) => n.type === 'ScrollView');
      assert.equal(scroll.props.contentContainerStyle.padding, 16);
      for (const n of nodes(scroll)) {
        assert.equal(n.props.numberOfLines, undefined);
        assert.equal(n.props.allowFontScaling, undefined);
        assert.equal(styleOf(n).height, undefined);
      }
      const title = nodes(tree).find((n) => n.type === 'Text' && textContent(n) === i18n.t('sync'));
      assert.equal(styleOf(title).textAlign, 'center');
      assert.equal(styleOf(title).fontWeight, '400');
      assert.equal(controls[0].props.style.minHeight, 48);
    });
  }
}

test('syncing uses existing wording, disables the native button and does not start sync on render', () => {
  const h = sync({ context: { isSyncing: true } });
  const action = buttons(h.render())[2];
  assert.equal(action.props.disabled, true);
  assert.equal(action.props.accessibilityState.disabled, true);
  assert.equal(action.props.accessibilityState.busy, true);
  assert.equal(action.props.accessibilityLabel, h.load('i18n.ts').i18n.t('syncing'));
  assert.equal(action.props.onPress, h.context.syncNow);
  assert.equal(h.uploads(), 0);
  h.context.isSyncing = false;
  assert.equal(buttons(h.render())[2].props.disabled, false);
});

test('offline and missing assignment retain existing facts/fallback without a network reader', () => {
  const h = sync({ context: { isOnline: false, assignment: null, pendingSyncCount: 0 } });
  const tree = h.render();
  assert.ok(textContent(tree).includes(h.load('i18n.ts').i18n.t('offline')));
  assert.ok(textContent(tree).includes('Unassigned'));
  assert.equal(buttons(tree)[2].props.onPress, h.context.syncNow);
  assert.equal(buttons(tree)[2].props.disabled, false);
  assert.equal(h.uploads(), 0);
});

for (const [bootstrapCompleted, lastSyncAt] of [[false, '2026-10-07T13:45:00Z'], [true, null], [true, 'invalid-date']]) {
  test(`last-sync readiness and fallback unchanged: ${bootstrapCompleted}/${lastSyncAt}`, () => {
    const h = sync({ context: { bootstrapCompleted, lastSyncAt } });
    const expected = bootstrapCompleted && lastSyncAt
      ? h.load('lib/format.ts').formatFriendlyDateTime(lastSyncAt) ?? lastSyncAt
      : h.load('i18n.ts').i18n.t('bootstrapPending');
    assert.ok(textContent(h.render()).includes(expected));
  });
}

for (const statusMessage of [
  'Upload failed: household UUID abc; HTTP 422; field purok_id rejected.',
  'Protected refresh: editor open; pending records retained.',
  'Offline: connect before submitting. Existing work remains on device.',
]) {
  test(`status retains full detail: ${statusMessage}`, () => {
    const h = sync({ context: { statusMessage } });
    const status = nodes(h.render()).find((n) => n.props.accessibilityLiveRegion === 'polite');
    assert.equal(textContent(status), statusMessage);
    assert.equal(h.uploads(), 0);
  });
}

for (const required of [false, true]) {
  test(`update available/required=${required} and maintenance preserve conditions and exact messages`, () => {
    const h = sync({ context: { releaseCheck: {
      update: { available: true, required, message: 'Release detail: minimum version 9' },
      maintenance: { maintenance_message: 'Maintenance until 18:00; cached records remain usable.' },
    } } });
    const i18n = h.load('i18n.ts').i18n;
    const text = textContent(h.render());
    assert.ok(text.includes(i18n.t(required ? 'updateRequiredTitle' : 'updateAvailableTitle')));
    assert.ok(text.includes('Release detail: minimum version 9'));
    assert.ok(text.includes('Maintenance until 18:00; cached records remain usable.'));
    assert.equal(buttons(h.render())[2].props.onPress, h.context.syncNow);
    h.context.releaseCheck.update.message = null;
    assert.ok(textContent(h.render()).includes(i18n.t('updateAvailableBody')));
    h.context.releaseCheck.update.available = false;
    h.context.releaseCheck.maintenance.maintenance_message = null;
    assert.ok(!textContent(h.render()).includes(i18n.t('updateAvailableBody')));
    assert.ok(!textContent(h.render()).includes(i18n.t('mobileMaintenanceTitle')));
  });
}

for (const count of [0, 2, 120]) {
  test(`header notification count ${count} navigates only and caps visual badge`, () => {
    const h = sync({ context: { unreadNotificationCount: count } });
    const bell = buttons(h.render())[0];
    const badge = nodes(bell).find((n) => n.type === 'Text');
    if (count === 0) assert.equal(badge, undefined);
    else {
      assert.equal(textContent(badge), count > 99 ? '99+' : String(count));
      assert.ok(bell.props.accessibilityLabel.includes(String(count)));
    }
    bell.props.onPress();
    assert.deepEqual(h.calls.navigation, [['Notifications']]);
    assert.equal(h.uploads(), 0);
  });
}

for (const pendingSyncCount of [0, 3]) {
  for (const confirmed of [false, true]) {
    test(`shared logout pending=${pendingSyncCount}, confirmed=${confirmed}`, async () => {
      const h = sync({ context: { pendingSyncCount } });
      h.context.requestConfirmation = async (request) => { h.calls.confirmations.push(request); return confirmed; };
      buttons(h.render())[1].props.onPress();
      await tick();
      assert.equal(h.calls.logout, 1);
      assert.equal(h.calls.confirmations.length, 1);
      const i18n = h.load('i18n.ts').i18n;
      assert.equal(h.calls.confirmations[0].message, pendingSyncCount
        ? i18n.t('logoutWarningBody', { count: pendingSyncCount }) : i18n.t('logoutConfirmationBody'));
      assert.equal(h.calls.confirmations[0].tone, pendingSyncCount ? 'warning' : 'danger');
      assert.equal(h.calls.signOut, confirmed ? 1 : 0);
      assert.equal(h.uploads(), 0);
    });
  }
}
