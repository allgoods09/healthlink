import assert from 'node:assert/strict';
import { test } from 'node:test';
import { createHomeHarness, nodes, textContent } from './homePresentationHarness.mjs';

const tick = () => new Promise(setImmediate);
const style = n => Object.assign({}, ...[n.props.style].flat(Infinity).filter(Boolean));
function fixture(options = {}) {
  const credentials = []; const toasts = [];
  const h = createHomeHarness({ ...options, context: {
    signIn: async value => credentials.push(value), showToast: (...args) => toasts.push(args), ...options.context,
  } });
  const i18n = h.load('i18n.ts').i18n;
  const render = () => h.render('Login');
  const find = (type, label) => nodes(render()).find(n => n.type === type && n.props.accessibilityLabel === i18n.t(label));
  return { ...h, i18n, render, credentials, toasts, input: label => find('TextInput', label), button: label => find('Pressable', label) };
}

for (const locale of ['en', 'ceb']) for (const mode of ['light', 'dark']) {
  test(`${locale}/${mode}: actual seal/photo, distinct brand/form, compact scalable controls and guidance`, () => {
    const h = fixture({ locale, mode, width: 320, fontScale: 1.6, keyboardInset: 220 });
    const tree = h.render(); const all = nodes(tree); const theme = h.load('theme.ts')[`${mode}Theme`];
    const photo = all.find(n => n.type === 'ImageBackground');
    assert.equal(photo.props.source.uri, 'test-asset:healthlink-bg.jpg');
    assert.equal(nodes(photo).some(n => n.type === 'TextInput'), false);
    const logo = nodes(photo).find(n => n.type === 'Image');
    assert.equal(logo.props.source.uri, 'test-asset:tubigon-logo.png');
    assert.equal(style(logo).width, 92); assert.equal(style(logo).height, 92);
    assert.ok(textContent(photo).includes('HEALTHLINK')); assert.ok(textContent(photo).includes('TUBIGON'));
    assert.equal(style(photo).paddingTop, 44);
    assert.equal(all.find(n => n.type === 'StatusBar').props.style, 'light');
    for (const key of ['loginWelcome', 'loginAccess', 'loginPortal', 'loginGuidance']) {
      assert.ok(textContent(tree).includes(h.i18n.t(key))); assert.ok(!h.i18n.t(key).includes('missing'));
    }
    const welcome = all.find(n => n.props.accessibilityRole === 'header');
    assert.equal(style(welcome).color, theme.colors.text);
    const form = all.find(n => n.type === 'View' && nodes(n).includes(welcome) && style(n).padding === 20);
    assert.equal(style(form).backgroundColor, theme.colors.surface);
    for (const key of ['email', 'password']) {
      const input = h.input(key); assert.equal(input.props.onFocus instanceof Function, true);
      assert.equal(style(input).minWidth, 0); assert.equal(style(input).color, theme.colors.text);
      // Each render creates elements anew; locate the corresponding control in the original tree.
      const original = all.find(n => n.type === 'TextInput' && n.props.accessibilityLabel === h.i18n.t(key));
      const parent = all.find(n => Array.isArray(n.props.children) && n.props.children.includes(original));
      assert.equal(style(parent).minHeight, 54); assert.equal(style(parent).borderRadius, 6);
      assert.equal(style(parent).backgroundColor, theme.colors.inputBackground);
      assert.equal(style(parent).elevation, undefined);
    }
    assert.equal(style(h.button('signIn')).minHeight, 54); assert.equal(style(h.button('signIn')).borderRadius, 6);
    assert.equal(style(h.button('signIn')).backgroundColor, theme.colors.primary);
    assert.equal(style(h.button('forgotPassword')).minHeight, 48);
    const scroll = all.find(n => n.type === 'ScrollView');
    assert.equal(scroll.props.keyboardShouldPersistTaps, 'handled'); assert.equal(scroll.props.scrollEventThrottle, 16);
    assert.equal(typeof scroll.props.onScroll, 'function'); assert.ok(scroll.props.ref);
    assert.ok(all.some(n => style(n).paddingBottom === 220));
    for (const n of all) { assert.equal(n.props.numberOfLines, undefined); assert.notEqual(n.props.allowFontScaling, false); }
    assert.equal(style(photo).height, undefined); assert.equal(style(form).height, undefined);
    assert.deepEqual(h.credentials, []);
  });
}

test('credentials, secure visibility and ForgotPassword keep exact handlers/navigation', async () => {
  const h = fixture();
  assert.equal(h.input('email').props.autoCapitalize, 'none'); assert.equal(h.input('email').props.keyboardType, 'email-address');
  h.input('email').props.onChangeText('person@example.test'); h.input('password').props.onChangeText('unchanged password');
  assert.equal(h.input('password').props.secureTextEntry, true);
  h.button('showPassword').props.onPress(); assert.equal(h.input('password').props.secureTextEntry, false);
  h.button('hidePassword').props.onPress(); assert.equal(h.input('password').props.secureTextEntry, true);
  h.button('forgotPassword').props.onPress(); assert.deepEqual(h.calls.navigation, [['ForgotPassword']]);
  await h.button('signIn').props.onPress();
  assert.equal(h.credentials.length, 1);
  assert.deepEqual({ ...h.credentials[0] }, { email: 'person@example.test', password: 'unchanged password' });
});

test('submitting keeps spinner and disabled native press target until completion', async () => {
  let finish; let requests = 0;
  const h = fixture({ context: { signIn: () => { requests++; return new Promise(resolve => { finish = resolve; }); } } });
  const pending = h.button('signIn').props.onPress(); const button = h.button('signIn');
  assert.equal(button.props.disabled, true); assert.equal(button.props.accessibilityState.busy, true);
  assert.equal(nodes(button).filter(n => n.type === 'ActivityIndicator').length, 1);
  // Native Pressable suppresses subsequent user presses while disabled.
  if (!button.props.disabled) button.props.onPress(); assert.equal(requests, 1);
  finish(); await pending; assert.equal(h.button('signIn').props.disabled, false);
});

test('existing Error and non-Error failure toast handling is unchanged', async () => {
  for (const failure of [new Error('Existing server rejection'), 'unknown']) {
    const h = fixture({ context: { signIn: async () => { throw failure; } } });
    await h.button('signIn').props.onPress();
    assert.deepEqual(h.toasts, [[failure instanceof Error ? failure.message : 'Sign in failed.', 'error']]);
    assert.equal(h.button('signIn').props.disabled, false);
  }
});

test('status warning still displays and is suppressed while an authentication error exists', async () => {
  const h = fixture({ context: { statusMessage: 'Existing account warning', signIn: async () => { throw new Error('Denied'); } } });
  h.render(); assert.deepEqual(h.toasts, [['Existing account warning', 'warning']]);
  await h.button('signIn').props.onPress(); await tick(); h.render();
  assert.deepEqual(h.toasts, [['Existing account warning', 'warning'], ['Denied', 'error']]);
});

for (const platform of ['android', 'ios']) test(`${platform}: keyboard-aware scroll/focus and avoidance remain connected`, () => {
  const h = fixture({ platform }); const tree = h.render();
  const avoidance = nodes(tree).find(n => n.type === 'KeyboardAvoidingView');
  assert.equal(avoidance.props.behavior, platform === 'ios' ? 'padding' : undefined);
  h.input('email').props.onFocus(); h.input('password').props.onFocus();
  nodes(tree).find(n => n.type === 'ScrollView').props.onScroll();
  assert.deepEqual(h.calls.navigation, [['test:focus'], ['test:focus'], ['test:scroll']]);
});
