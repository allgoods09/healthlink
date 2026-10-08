import assert from 'node:assert/strict';
import test from 'node:test';
import { createHomeHarness, nodes, textContent } from './homePresentationHarness.mjs';

const flush = () => new Promise((resolve) => setImmediate(resolve));
const styleOf = (node) => Object.assign({}, ...[node.props.style].flat(Infinity).filter(Boolean));

for (const locale of ['en', 'ceb']) for (const mode of ['light', 'dark']) {
  for (const [width, fontScale] of [[390, 1], [320, 1], [390, 1.6]]) {
    test(`${locale}/${mode}/${width}dp/font=${fontScale}: approved Home branding and responsive icon rows`, () => {
      const h = createHomeHarness({ locale, mode, width, fontScale });
      const tree = h.render(); const all = nodes(tree);
      const theme = h.load('theme.ts')[`${mode}Theme`]; const i18n = h.load('i18n.ts').i18n;
      const logo = all.find(n => n.type === 'Image');
      assert.equal(logo.props.source.uri, 'test-asset:tubigon-logo.png');
      assert.equal(logo.props.resizeMode, 'contain'); assert.equal(logo.props.accessible, false);
      assert.equal(styleOf(logo).width, 104); assert.equal(styleOf(logo).height, 104);
      assert.ok(textContent(tree).includes(i18n.t('appTitle')));
      const banner = all.find(n => styleOf(n).backgroundColor === theme.colors.brandBackground);
      assert.equal(styleOf(banner).borderRadius, 8); assert.equal(styleOf(banner).minHeight, 196);
      assert.equal(styleOf(banner).flexDirection, width < 380 || fontScale > 1.2 ? 'column' : 'row');
      assert.equal(styleOf(banner).height, undefined);
      const greeting = all.find(n => n.props.accessibilityRole === 'header' && textContent(n).includes('Jose Test'));
      assert.equal(styleOf(greeting).color, theme.colors.textOnBrand);
      assert.equal(styleOf(greeting).fontWeight, '700'); assert.equal(styleOf(greeting).fontSize, 26);
      for (const label of ['openDirectory', 'recordVisitAction', 'newHouseholdDraft', 'newResidentDraft']) {
        const row = all.find(n => n.type === 'Pressable' && n.props.accessibilityLabel === i18n.t(label));
        assert.equal(styleOf(row).minHeight, 64); assert.equal(styleOf(row).height, undefined);
        assert.equal(styleOf(row).backgroundColor, theme.colors.surface); assert.equal(styleOf(row).borderRadius, 6);
        const circle = nodes(row).find(n => n.type === 'View');
        assert.equal(styleOf(circle).backgroundColor, theme.colors.infoSoft);
        assert.equal(styleOf(circle).width, 40); assert.equal(styleOf(circle).borderRadius, 20);
        const icons = nodes(row).filter(n => n.type === 'Ionicons');
        assert.equal(icons[0].props.color, theme.colors.primary); assert.equal(icons[0].props.accessible, false);
        assert.equal(icons[1].props.name, 'chevron-forward');
        const text = nodes(row).find(n => n.type === 'Text');
        assert.equal(styleOf(text).minWidth, 0); assert.equal(styleOf(text).flex, 1);
      }
      for (const n of all.filter(n => n.type === 'Text')) {
        assert.equal(n.props.numberOfLines, undefined); assert.notEqual(n.props.allowFontScaling, false);
        assert.equal(styleOf(n).height, undefined);
      }
      assert.equal(all.filter(n => n.type === 'ScrollView').length, 1);
    });
  }
}

for (const name of ['Joe Marie Abapo', 'Synthetic Legitimate Surname', '  Jos\u00e9 Pe\u00f1a  ', '', undefined]) {
  test(`greeting uses only authenticated name ${JSON.stringify(name)} or neutral BHW fallback`, () => {
    const h = createHomeHarness({ context: { user: { name } } });
    const before = JSON.stringify(h.context.user);
    const output = textContent(h.render()); const i18n = h.load('i18n.ts').i18n;
    assert.ok(output.includes(i18n.t('homeWelcome', { name: name?.trim() || 'BHW' })));
    assert.equal(JSON.stringify(h.context.user), before);
    if (name !== 'Joe Marie Abapo') assert.ok(!output.includes('Joe Marie'));
  });
}

test('Home keeps four exact quick routes, separate Sync and notification navigation', () => {
  const h = createHomeHarness();
  const tree = h.render();
  const buttons = nodes(tree).filter((node) => node.type === 'Pressable');
  assert.equal(buttons.length, 7);
  const quick = buttons.slice(2);
  for (const [index, button] of quick.entries()) {
    assert.equal(button.props.accessibilityRole, 'button');
    const icons = nodes(button).filter((node) => node.type === 'Ionicons');
    assert.equal(icons.length, index < 4 ? 2 : 1);
    assert.equal(icons[0].props.name, ['people-outline', 'clipboard-outline', 'document-text-outline', 'person-add-outline', 'sync-outline'][index]);
    if (index < 4) assert.equal(icons[1].props.name, 'chevron-forward');
    assert.equal(nodes(button).filter((node) => node.type === 'Text').length, 1);
    button.props.onPress();
  }
  buttons[0].props.onPress();
  assert.deepEqual(h.calls.navigation, [
    ['DirectoryTab'], ['VisitForm'], ['HouseholdForm'], ['ResidentForm'], ['SyncTab'], ['Notifications'],
  ]);
  assert.equal(h.calls.signOut, 0);
  assert.equal(h.calls.confirmations.length, 0);
});

for (const count of [0, 4, 120]) {
  test(`notification ${count}: accessible count and capped visual badge, navigation only`, () => {
    const h = createHomeHarness({ context: { unreadNotificationCount: count } });
    const tree = h.render();
    const button = nodes(tree).find((node) => node.type === 'Pressable');
    assert.equal(button.props.accessibilityRole, 'button');
    assert.match(button.props.accessibilityLabel, /Notifications/);
    if (count) assert.ok(button.props.accessibilityLabel.includes(String(count)));
    const badge = nodes(button).find((node) => node.type === 'Text');
    if (!count) assert.equal(badge, undefined);
    else {
      assert.equal(textContent(badge), count > 99 ? '99+' : String(count));
      assert.equal(badge.props.accessible, false);
      assert.equal(styleOf(badge).position, 'absolute');
      assert.ok(styleOf(badge).top < 0);
      const iconContainer = nodes(button).find((node) => node.type === 'View');
      assert.equal(styleOf(iconContainer).position, 'relative');
      assert.ok(nodes(iconContainer).includes(badge));
    }
    button.props.onPress();
    assert.deepEqual(h.calls.navigation, [['Notifications']]);
  });
}

for (const locale of ['en', 'ceb']) {
  test(`${locale}: retained facts/localized copy update directly from context without storage reads`, () => {
    const name = 'Jos\u00e9 Yba\u00f1ez with a long household worker display name';
    const h = createHomeHarness({ locale, context: { user: { name }, pendingSyncCount: 7 } });
    const i18n = h.load('i18n.ts').i18n;
    const output = textContent(h.render());
    for (const expected of [name, 'Pooc Oriental', 'Purok 2', '7', i18n.t('home'),
      i18n.t('homeWelcome', { name }), i18n.t('assignmentTitle'), i18n.t('homeRole'),
      i18n.t('quickActions'), i18n.t('homePendingSync'), i18n.t('bootstrapPending')]) {
      assert.ok(output.includes(expected), expected);
    }
    const buttons = nodes(h.render()).filter((node) => node.type === 'Pressable').slice(2);
    assert.deepEqual(buttons.map((node) => node.props.accessibilityLabel), [
      i18n.t('openDirectory'), i18n.t('recordVisitAction'), i18n.t('newHouseholdDraft'),
      i18n.t('newResidentDraft'), i18n.t('sync'),
    ]);
    h.context.user = null;
    assert.ok(textContent(h.render()).includes(i18n.t('homeWelcome', { name: 'BHW' })));
    for (const removed of ['offlineData', 'recentVisits', 'householdsOnDevice', 'residentsOnDevice',
      'visitsOnDevice', 'openDirectoryBody', 'recordVisitActionBody', 'newHouseholdDraftBody', 'newResidentDraftBody']) {
      assert.ok(!output.includes(i18n.t(removed)), removed);
    }
    h.context.lastSyncAt = '2026-10-07T13:14:00Z';
    h.context.pendingSyncCount = 9;
    const updated = textContent(h.render());
    assert.ok(updated.includes(h.load('lib/format.ts').formatFriendlyDateTime(h.context.lastSyncAt)));
    assert.ok(updated.includes('9'));
    for (const node of nodes(h.render()).filter((node) => node.type === 'Text')) {
      assert.notEqual(node.props.allowFontScaling, false);
      assert.equal(node.props.numberOfLines, undefined);
    }
    h.context.assignment = null;
    h.context.lastSyncAt = 'invalid timestamp';
    assert.match(textContent(h.render()), /Barangay\s+·\s+Unassigned Purok/);
    assert.ok(textContent(h.render()).includes('invalid timestamp'));
  });
}

for (const locale of ['en', 'ceb']) {
  for (const pendingSyncCount of [0, 3]) {
    for (const confirmed of [false, true]) {
      test(`${locale}: Home and More share logout; pending=${pendingSyncCount}, confirm=${confirmed}`, async () => {
        for (const screen of ['Home', 'More']) {
          const h = createHomeHarness({ locale, context: { pendingSyncCount } });
          h.context.requestConfirmation = async (request) => {
            h.calls.confirmations.push(request);
            return confirmed; // Android Back and Cancel both return false through the existing host.
          };
          const i18n = h.load('i18n.ts').i18n;
          const tree = h.render(screen);
          const button = nodes(tree).find((node) =>
            node.type === 'Pressable' && node.props.accessibilityLabel === i18n.t('logout'));
          button.props.onPress();
          await flush();
          assert.equal(h.calls.logout, 1);
          assert.equal(h.calls.confirmations.length, 1);
          assert.equal(JSON.stringify(h.calls.confirmations[0]), JSON.stringify({
            title: i18n.t(pendingSyncCount ? 'logoutWarningTitle' : 'logoutConfirmationTitle'),
            message: pendingSyncCount ? i18n.t('logoutWarningBody', { count: pendingSyncCount }) : i18n.t('logoutConfirmationBody'),
            confirmLabel: i18n.t(pendingSyncCount ? 'logoutAnyway' : 'logout'),
            tone: pendingSyncCount ? 'warning' : 'danger',
          }));
          assert.equal(h.calls.signOut, confirmed ? 1 : 0);
          assert.deepEqual(h.calls.navigation, []);
        }
      });
    }
  }
}

test('logout waits for confirmation and existing signOut completion', async () => {
  const h = createHomeHarness();
  let confirm;
  let finish;
  let completed = false;
  const result = h.load('lib/confirmLogout.ts').confirmLogout({
    pendingSyncCount: 2,
    requestConfirmation: () => new Promise((resolve) => { confirm = resolve; }),
    signOut: () => { h.calls.signOut += 1; return new Promise((resolve) => { finish = resolve; }); },
  }).then(() => { completed = true; });
  assert.equal(h.calls.signOut, 0);
  confirm(true);
  await flush();
  assert.equal(h.calls.signOut, 1);
  assert.equal(completed, false);
  finish();
  await result;
  assert.equal(completed, true);
});

for (const mode of ['light', 'dark']) {
  test(`${mode}: real opt-in styles preserve neutral surfaces and narrow/large-text safeguards`, () => {
    const h = createHomeHarness({ mode });
    const theme = h.load('theme.ts')[`${mode}Theme`];
    const header = h.load('components/RootHeader.tsx').rootHeaderStyles(theme);
    const compact = h.load('components/CompactUi.tsx').compactStyles(theme);
    assert.equal(header.wrapper.backgroundColor, theme.colors.surface);
    assert.notEqual(header.wrapper.backgroundColor, theme.colors.primary);
    assert.equal(header.title.color, theme.colors.text);
    assert.equal(header.title.flex, 1);
    assert.equal(header.title.minWidth, 0);
    assert.equal(header.title.textAlign, 'center');
    assert.ok(['400', '500', 'normal'].includes(header.title.fontWeight));
    assert.equal(header.action.flexShrink, 0);
    assert.equal(header.action.minHeight, 48);
    assert.equal(header.action.minWidth, 48);
    assert.equal(compact.action.minHeight, 50);
    assert.equal(compact.action.borderRadius, 6);
    assert.equal(compact.action.backgroundColor, theme.colors.surface);
    assert.equal(compact.actionLabel.color, theme.colors.text);
    assert.equal(compact.secondary.color, theme.colors.textMuted);
    assert.equal(compact.textActionLabel.color, theme.colors.primary);
    assert.equal(compact.textAction.borderColor, theme.colors.primary);
    assert.notEqual(compact.textAction.borderWidth, 0);
    assert.equal(compact.banner.backgroundColor, theme.colors.brandBackground);
    assert.equal(compact.bannerText.color, theme.colors.textOnBrand);
    assert.equal(compact.banner.flexWrap, 'wrap');
    assert.equal(compact.bannerContext.minWidth, 0);
    assert.equal(compact.bannerContext.flexShrink, 1);
    assert.equal(header.badge.color, theme.colors.textOnPrimary);
    assert.equal(compact.factRow.flexWrap, 'wrap');
    assert.equal(compact.factValue.minWidth, 0);
    assert.equal(compact.actionLabel.flexShrink, 1);
    for (const [key, style] of [...Object.entries(header), ...Object.entries(compact)]) {
      if (key === 'bannerLogo') continue; // Decorative image dimensions do not constrain text.
      assert.equal(style.height, undefined);
      assert.equal(style.elevation, undefined);
      assert.equal(style.shadowOpacity, undefined);
      if (!['notificationIcon', 'badge'].includes(key)) assert.equal(style.position, undefined);
    }
    assert.equal(JSON.stringify(theme.radius), JSON.stringify({ sm: 10, md: 16, lg: 22 }));
    assert.equal(h.load('theme.ts').resolveTheme('system', mode).mode, mode);
    assert.equal(nodes(h.render()).filter((node) => node.type === 'ScrollView').length, 1);
    const tree = h.render();
    const title = nodes(tree).find((node) => node.type === 'Text' && textContent(node) === 'Home');
    const headerRow = nodes(tree).find((node) => Array.isArray(node.props.children) && node.props.children.includes(title));
    const [leftTrack, , rightTrack] = headerRow.props.children;
    assert.equal(styleOf(leftTrack).width, styleOf(rightTrack).width);
    assert.equal(nodes(leftTrack).filter((node) => node.type === 'Pressable').length, 0);
    assert.equal(nodes(rightTrack).filter((node) => node.type === 'Pressable').length, 2);
    assert.equal(styleOf(title).position, undefined);
    for (const label of ['homePendingSync', 'lastSync']) {
      const fact = nodes(tree).find((node) => node.type === 'Text' && textContent(node) === h.load('i18n.ts').i18n.t(label));
      const row = nodes(tree).find((node) => Array.isArray(node.props.children) && node.props.children.includes(fact));
      assert.equal(row.type, 'View');
      assert.equal(row.props.onPress, undefined);
      assert.equal(nodes(row).filter((node) => node.type === 'Pressable').length, 0);
    }
    const sync = nodes(tree).find((node) => node.type === 'Pressable' && node.props.accessibilityLabel === 'Sync');
    assert.equal(styleOf(sync).borderColor, theme.colors.primary);
    assert.ok(styleOf(sync).borderWidth > 0);
    assert.ok(styleOf(sync).minHeight >= 48);
    sync.props.onPress();
    assert.deepEqual(h.calls.navigation, [['SyncTab']]);
  });
}
