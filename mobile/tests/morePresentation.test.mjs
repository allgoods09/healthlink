import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { createHomeHarness, nodes, textContent } from './homePresentationHarness.mjs';
const tick = () => new Promise(setImmediate);
const styleOf = (node) => Object.assign({}, ...[node.props.style].flat(Infinity).filter(Boolean));
const controls = (tree) => nodes(tree).filter((n) => n.type === 'Pressable');
function more(options = {}) {
  const calls = { release: 0, languages: [], appearances: [], urls: [], toasts: [] };
  const h = createHomeHarness({ ...options,
    apiBaseUrl: options.apiBaseUrl ?? 'https://healthlink.example.test/api',
    linking: options.linking ?? { openURL: async (url) => { calls.urls.push(url); } },
    context: {
      appVersion: '1.0.20',
      refreshReleaseStatus: async () => { calls.release++; },
      showToast: (...args) => calls.toasts.push(args),
      setLanguagePreference: async (value) => { calls.languages.push(value); h.context.language = value; },
      setAppearancePreference: async (value) => { calls.appearances.push(value); h.context.appearancePreference = value; },
      ...options.context,
    } });
  return { ...h, actions: calls, render: (screen = 'More') => h.render(screen) };
}
for (const locale of ['en', 'ceb']) for (const mode of ['light', 'dark']) {
  test(`${locale}/${mode}: More is exactly four neutral navigation rows, not inline content`, () => {
    const h = more({ locale, mode });
    const tree = h.render();
    const scroll = nodes(tree).find((n) => n.type === 'ScrollView');
    const rows = controls(scroll);
    const i18n = h.load('i18n.ts').i18n;
    const theme = h.load('theme.ts')[`${mode}Theme`];
    assert.equal(rows.length, 4);
    for (const [index, route, key] of [[0,'Account','accountTitle'],[1,'Language','languageTitle'],[2,'Appearance','appearanceTitle'],[3,'AboutApp','aboutApp']]) {
      const row = rows[index];
      assert.equal(textContent(row), i18n.t(key));
      assert.equal(row.props.accessibilityLabel, i18n.t(key));
      assert.equal(styleOf(row).backgroundColor, theme.colors.surface);
      assert.equal(styleOf(row).minHeight, 66);
      assert.equal(styleOf(row).borderRadius, 6);
      assert.equal(nodes(row).filter((n) => n.type === 'Text').length, 1);
      assert.equal(nodes(row).filter((n) => n.type === 'Ionicons').length, 0);
      row.props.onPress();
      assert.deepEqual(h.calls.navigation[index], [route]);
    }
    assert.ok(!textContent(scroll).includes('Jose Test'));
    assert.ok(!textContent(scroll).includes('1.0.20'));
    assert.ok(!textContent(scroll).includes(i18n.t('english')));
    assert.equal(h.actions.release, 0);
  });
  for (const [screen, key] of [['Account','accountTitle'],['Language','languageTitle'],['Appearance','appearanceTitle'],['AboutApp','aboutApp']]) {
    test(`${locale}/${mode}: ${screen} child has centered neutral Back-only header and wrapping content`, () => {
      const h = more({ locale, mode });
      const tree = h.render(screen);
      const header = tree.props.children[0];
      const title = nodes(header).find((n) => n.type === 'Text');
      assert.equal(textContent(title), h.load('i18n.ts').i18n.t(key));
      assert.equal(styleOf(title).textAlign, 'center');
      assert.equal(styleOf(title).fontWeight, '400');
      assert.equal(styleOf(title).minWidth, 0);
      assert.equal(controls(header).length, 1);
      assert.equal(styleOf(controls(header)[0]).minHeight, 48);
      controls(header)[0].props.onPress();
      assert.equal(h.calls.back, 1);
      assert.deepEqual(h.calls.navigation, []);
      assert.equal(nodes(header).filter((n) => n.type === 'Ionicons')[0].props.name, 'arrow-back');
      for (const n of nodes(tree)) {
        assert.equal(n.props.numberOfLines, undefined);
        assert.equal(n.props.allowFontScaling, undefined);
        if (n.type !== 'Image' && styleOf(n).borderRadius !== 38) assert.equal(styleOf(n).height, undefined);
      }
    });
  }
}
test('Account exposes only available user-facing fields with proper role display, no fabricated values or internal metadata', () => {
  const h = more({ context: { user: { name: 'Long Name '.repeat(10), email: 'person@example.test', role: 'bhw', id: 999, token: 'SECRET' } } });
  const tree = h.render('Account');
  const text = textContent(tree);
  for (const value of [h.context.user.name, 'person@example.test', h.load('i18n.ts').i18n.t('accountBhwRole'), 'Pooc Oriental', 'Purok 2']) assert.ok(text.includes(value));
  assert.ok(!text.includes('SECRET')); assert.ok(!text.includes('999'));
  assert.equal(controls(tree).length, 1);
  h.context.user = null; h.context.assignment = null;
  const empty = nodes(h.render('Account')).find((n) => n.type === 'ScrollView');
  assert.equal(textContent(empty).trim(), '');
});
test('Account initials handle single/multiple words, Unicode, whitespace and unusable names safely', () => {
  const h = more();
  const initials = h.load('screens/AccountScreen.tsx').accountInitials;
  for (const [name, expected] of [[undefined, ''], ['', ''], ['   ', ''], ['--- !!', ''], ['Jose', 'J'],
    ['  Jose  Test  ', 'JT'], ['Celine Maria Ybanez', 'CY'], ['\u00c9lodie Mu\u00f1oz', '\u00c9M']]) {
    assert.equal(initials(name), expected);
  }
  h.context.user = { name: 'Jose Test', role: 'bhw' };
  const avatar = nodes(h.render('Account')).find((n) => styleOf(n).borderRadius === 38);
  assert.equal(textContent(avatar), 'JT');
  assert.equal(styleOf(avatar).width, 76);
  assert.equal(styleOf(avatar).height, 76);
  assert.equal(avatar.props.importantForAccessibility, 'no-hide-descendants');
  h.context.user = { name: '---', role: 'unsupported' };
  const tree = h.render('Account');
  assert.ok(nodes(tree).some((n) => n.type === 'Ionicons' && n.props.name === 'person-outline'));
  assert.ok(!textContent(tree).includes(h.load('i18n.ts').i18n.t('accountBhwRole')));
});
for (const locale of ['en', 'ceb']) for (const mode of ['light', 'dark']) {
  test(`${locale}/${mode}: profile sections and branded About use theme tokens and unconstrained meaningful text`, () => {
    const h = more({ locale, mode, context: { appearancePreference: 'system', appVersion: '9.8.7',
      user: { name: 'Long Display Name '.repeat(8), email: 'longemail'.repeat(15) + '@example.test', role: 'bhw' } } });
    const i18n = h.load('i18n.ts').i18n;
    const theme = h.load('theme.ts')[`${mode}Theme`];
    const account = h.render('Account');
    const headings = nodes(account).filter((n) => n.type === 'Text' && n.props.accessibilityRole === 'header');
    for (const key of ['accountInformation', 'accountAssignedArea']) assert.ok(headings.some((n) => textContent(n) === i18n.t(key)));
    assert.ok(!textContent(account).includes(i18n.t('homeRole')));
    const avatar = nodes(account).find((n) => styleOf(n).borderRadius === 38);
    assert.equal(styleOf(avatar).backgroundColor, theme.colors.primarySoft);
    const about = h.render('AboutApp');
    const logo = nodes(about).find((n) => n.type === 'Image');
    const config = JSON.parse(readFileSync(new URL('../app.json', import.meta.url), 'utf8'));
    assert.equal(config.expo.icon, './assets/apk-logo-icon.png');
    assert.equal(logo.props.source.uri, 'test-asset:apk-logo-icon.png');
    assert.equal(logo.props.resizeMode, 'contain');
    assert.equal(styleOf(logo).width, 88);
    assert.equal(styleOf(logo).height, 88);
    assert.equal(logo.props.accessible, false);
    assert.ok(textContent(about).includes(config.expo.name));
    assert.ok(textContent(about).includes('9.8.7'));
    assert.ok(!textContent(about).includes('1.0.20'));
    const paragraph = nodes(about).find((n) => n.type === 'Text' && textContent(n) === i18n.t('aboutAppPurpose'));
    assert.ok(paragraph);
    assert.equal(styleOf(paragraph).textAlign, 'center');
    assert.equal(styleOf(paragraph).color, theme.colors.textMuted);
    if (locale === 'en') assert.match(textContent(paragraph), /assigned health records.*field visits.*manually synchronize/);
    else assert.match(textContent(paragraph), /rekord sa panglawas.*pagbisita sa panimalay.*manwal nga pag-sync/);
    assert.doesNotMatch(textContent(paragraph), /automatic|real-time|diagnosis/i);
    const update = controls(about)[1];
    assert.equal(styleOf(update).minHeight, 66);
    assert.equal(styleOf(update).borderRadius, 6);
    assert.equal(styleOf(update).backgroundColor, theme.colors.surface);
    for (const tree of [account, about]) {
      assert.ok(nodes(tree).some((n) => n.type === 'ScrollView'));
      for (const text of nodes(tree).filter((n) => n.type === 'Text')) {
        assert.equal(text.props.numberOfLines, undefined);
        assert.equal(text.props.allowFontScaling, undefined);
        assert.equal(styleOf(text).height, undefined);
        assert.equal(styleOf(text).width, undefined);
      }
    }
  });
}
for (const [screen, field, entries] of [['Language','languages',['en','ceb']],['Appearance','appearances',['system','light','dark']]]) {
  test(`${screen} vertical selections call exact existing setters with checkmarks and accessible selected state`, async () => {
    const h = more({ context: { appearancePreference: 'system' } });
    for (const [index, value] of entries.entries()) {
      const rows = controls(h.render(screen)).slice(1);
      assert.equal(rows.length, entries.length);
      assert.equal(rows[index].props.accessibilityRole, 'radio');
      assert.equal(styleOf(rows[index]).minHeight, 60);
      rows[index].props.onPress(); await tick();
      const selected = controls(h.render(screen)).slice(1)[index];
      assert.equal(selected.props.accessibilityState.checked, true);
      assert.equal(selected.props.accessibilityState.selected, true);
      assert.equal(nodes(selected).filter((n) => n.type === 'Ionicons').length, 1);
      assert.equal(h.actions[field].at(-1), value);
    }
    assert.equal(h.actions.release, 0);
  });
}
test('About App shows actual version and opens only the public update webpage once without refreshing releases', async () => {
  const h = more();
  const tree = h.render('AboutApp');
  assert.ok(textContent(tree).includes('HealthLink'));
  assert.ok(textContent(tree).includes('1.0.20'));
  controls(tree)[1].props.onPress(); await tick();
  assert.deepEqual(h.actions.urls, ['https://healthlink.example.test/mobile/bhw/update']);
  assert.equal(h.actions.release, 0);
  assert.equal(h.calls.signOut, 0);
});
test('update URL accepts configured HTTPS origin, strips API path/query, and rejects invalid/unsafe targets', () => {
  const h = more();
  const resolve = h.load('lib/updatePage.ts').bhwUpdatePageUrl;
  assert.equal(resolve('https://healthlink.example.test/api/mobile?key=x'), 'https://healthlink.example.test/mobile/bhw/update');
  for (const bad of ['', 'bad', 'http://localhost:8000', 'https://localhost', 'https://127.0.0.1', 'https://[::1]', 'https://user:pass@example.test', 'file:///tmp/update']) assert.equal(resolve(bad), null, bad);
});
test('browser failure is handled without installing, altering releases or losing current page', async () => {
  const h = more({ linking: { openURL: async () => { throw new Error('private error'); } } });
  controls(h.render('AboutApp'))[1].props.onPress(); await tick();
  assert.equal(h.actions.toasts.length, 1);
  assert.equal(h.actions.toasts[0][0], h.load('i18n.ts').i18n.t('updatePageUnavailable'));
  assert.equal(h.actions.release, 0);
});
test('four authenticated child routes registered with hidden native headers, existing tab definitions unchanged', () => {
  const source = readFileSync(new URL('../src/navigation/AppNavigator.tsx', import.meta.url), 'utf8');
  for (const route of ['Account','Language','Appearance','AboutApp']) {
    assert.ok(source.includes(`<Stack.Screen name="${route}" component={${route}Screen} options={{ headerShown: false }} />`));
  }
  assert.ok(source.includes('name="MoreTab"'));
  assert.ok(source.includes('tabBarStyle:'));
});
for (const count of [0, 2, 120]) {
  test(`notification count ${count} reuses badge and only navigates`, () => {
    const h = more({ context: { unreadNotificationCount: count } });
    const bell = controls(h.render())[0];
    const badge = nodes(bell).find((n) => n.type === 'Text');
    if (!count) assert.equal(badge, undefined);
    else {
      assert.equal(textContent(badge), count > 99 ? '99+' : String(count));
      assert.ok(bell.props.accessibilityLabel.includes(String(count)));
    }
    bell.props.onPress();
    assert.deepEqual(h.calls.navigation, [['Notifications']]);
    assert.equal(h.actions.release, 0);
  });
}

for (const locale of ['en', 'ceb']) {
  for (const pendingSyncCount of [0, 3]) {
    for (const outcome of ['cancel', 'back', 'confirm']) {
      test(`${locale}: pending=${pendingSyncCount}, modal ${outcome} retains safe confirmed logout`, async () => {
        const h = more({ locale, context: { pendingSyncCount } });
        let resolve;
        h.context.requestConfirmation = (request) => {
          h.calls.confirmations.push(request);
          return new Promise((done) => { resolve = done; });
        };
        controls(h.render())[1].props.onPress();
        assert.equal(h.calls.logout, 1);
        assert.equal(h.calls.signOut, 0);
        const request = h.calls.confirmations[0];
        const i18n = h.load('i18n.ts').i18n;
        assert.equal(request.message, pendingSyncCount ? i18n.t('logoutWarningBody', { count: pendingSyncCount }) : i18n.t('logoutConfirmationBody'));
        assert.equal(request.tone, pendingSyncCount ? 'warning' : 'danger');
        if (pendingSyncCount) {
          assert.ok(request.message.includes('3'));
          if (locale === 'en') {
            assert.match(request.message, /does not delete/);
            assert.match(request.message, /remain on this device/);
            assert.match(request.message, /Only the same account can sign back in to sync/);
          } else {
            assert.match(request.message, /dili mopapas/);
            assert.match(request.message, /magpabilin kini sa device/);
            assert.match(request.message, /mao rang account.*i-sync/);
          }
        }
        const modal = h.load('components/ActionConfirmationModal.tsx').ActionConfirmationModal({ confirmation: request, onResolve: resolve });
        if (outcome === 'back') modal.props.onRequestClose();
        else {
          const label = outcome === 'cancel' ? i18n.t('cancel') : request.confirmLabel;
          controls(modal).find((n) => n.props.accessibilityLabel === label).props.onPress();
        }
        await tick();
        assert.equal(h.calls.signOut, outcome === 'confirm' ? 1 : 0);
        assert.equal(h.actions.release, 0);
        assert.deepEqual(h.actions.languages, []);
        assert.deepEqual(h.actions.appearances, []);
        assert.deepEqual(h.calls.navigation, []);
      });
    }
  }
}
