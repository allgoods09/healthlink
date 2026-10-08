import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import vm from 'node:vm';

const require = createRequire(import.meta.url);
const ts = require('typescript');
const root = resolve(dirname(new URL(import.meta.url).pathname), '../src');

export function nodes(tree) {
  if (tree == null || typeof tree !== 'object') return [];
  if (Array.isArray(tree)) return tree.flatMap(nodes);
  return [tree, ...nodes(tree.props?.children)];
}

export function textContent(tree) {
  if (tree == null || typeof tree === 'boolean') return '';
  if (Array.isArray(tree)) return tree.map(textContent).join(' ');
  if (typeof tree !== 'object') return String(tree);
  return textContent(tree.props?.children);
}

// Execute real screen/component code with host primitives, not a native layout simulator.
export function createHomeHarness({ locale = 'en', mode = 'light', context: overrides = {}, storage, apiBaseUrl, linking } = {}) {
  const cache = new Map();
  const calls = { navigation: [], confirmations: [], signOut: 0, logout: 0, back: 0 };
  const context = {
    user: { name: 'Jose Test', email: 'test@example.test' },
    assignment: { barangay: { name: 'Pooc Oriental' }, purok: { display_name: 'Purok 2' } },
    pendingSyncCount: 0, unreadNotificationCount: 0, lastSyncAt: null,
    notifications: [], language: locale, appearancePreference: mode,
    appVersion: 'test', bootstrapCompleted: true, isOnline: false, releaseCheck: null,
    requestConfirmation: async (request) => { calls.confirmations.push(request); return false; },
    signOut: async () => { calls.signOut += 1; },
    refreshReleaseStatus: () => { throw new Error('Unexpected release refresh'); },
    refreshNotifications: () => { throw new Error('Unexpected notification refresh'); },
    markNotificationRead: () => { throw new Error('Unexpected mark-read'); },
    syncNow: () => { throw new Error('Unexpected Home sync'); },
    ...overrides,
  };
  const element = (type, props) => typeof type === 'function' ? type(props) : { type, props };
  const hooks = [];
  let cursor = 0;
  let effects = [];
  let focused = true;
  const react = {
    useState(initial) {
      const index = cursor++;
      if (!(index in hooks)) hooks[index] = initial;
      return [hooks[index], (value) => { hooks[index] = typeof value === 'function' ? value(hooks[index]) : value; }];
    },
    useEffect(callback, deps) {
      const index = cursor++;
      const old = hooks[index];
      if (!old || deps.some((value, n) => !Object.is(value, old.deps[n]))) {
        effects.push(() => { old?.cleanup?.(); hooks[index].cleanup = callback(); });
      }
      hooks[index] = { deps, cleanup: old?.cleanup };
    },
  };
  const native = {
    Linking: linking,
    View: 'View', Text: 'Text', Pressable: 'Pressable', ScrollView: 'ScrollView', Image: 'Image', ActivityIndicator: 'ActivityIndicator',
    StyleSheet: { create: (styles) => styles, hairlineWidth: 0.5 },
  };
  function load(relative) {
    const filename = resolve(root, relative);
    if (cache.has(filename)) return cache.get(filename).exports;
    const module = { exports: {} };
    cache.set(filename, module);
    const localRequire = (id) => {
      if (id === 'react') return { ...react, default: {}, __esModule: true };
      if (id === '@react-navigation/native') return { useIsFocused: () => focused };
      if (id === 'react/jsx-runtime') return { jsx: element, jsxs: element };
      if (id === 'react-native') return native;
      if (id === '@expo/vector-icons') return { Ionicons: 'Ionicons' };
      if (id === 'react-native-safe-area-context') return { useSafeAreaInsets: () => ({ top: 24 }) };
      if (id === 'expo-localization') return { getLocales: () => [{ languageCode: locale }] };
      if (id === 'i18n-js') return require(id);
      if (id.endsWith('/context/AppContext')) return {
        useAppContext: () => context,
        useAppTheme: () => load('theme.ts')[`${mode}Theme`],
        useThemedStyles: (factory) => factory(load('theme.ts')[`${mode}Theme`]),
      };
      if (id.endsWith('/components/MenuCard')) return { MenuCard: 'MenuCard' };
      if (id.endsWith('/components/TopHeader')) return { TopHeader: 'TopHeader' };
      if (id === '../../assets/tubigon-logo.png' || id === '../../assets/apk-logo-icon.png') {
        readFileSync(resolve(dirname(filename), id));
        return { uri: `test-asset:${id.split('/').at(-1)}` };
      }
      if (id.endsWith('/lib/storage') && storage) return storage;
      if (id.endsWith('/lib/config') && apiBaseUrl !== undefined) return { MOBILE_API_BASE_URL: apiBaseUrl };
      if (id.includes('storage') || !id.startsWith('.')) throw new Error(`Unexpected dependency: ${id}`);
      const dependency = resolve(dirname(filename), `${id}.ts${id.includes('components/') ? 'x' : ''}`);
      const actual = id.endsWith('confirmLogout') ? load('lib/confirmLogout.ts') : load(dependency);
      if (id.endsWith('confirmLogout')) return {
        confirmLogout: (args) => { calls.logout += 1; return actual.confirmLogout(args); },
      };
      return actual;
    };
    const code = ts.transpileModule(readFileSync(filename, 'utf8'), {
      compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    vm.runInNewContext(code, { module, exports: module.exports, require: localRequire, Intl, Date, URL, Error }, { filename });
    return module.exports;
  }
  const navigation = { navigate: (...args) => calls.navigation.push(args), goBack: () => { calls.back++; } };
  function render(screen = 'Home') {
    cursor = 0;
    effects = [];
    const tree = load(`screens/${screen}Screen.tsx`)[`${screen}Screen`]({ navigation });
    effects.forEach((effect) => effect());
    return tree;
  }
  return {
    context, calls, load, render,
    setFocused: (value) => { focused = value; },
    unmount: () => hooks.forEach((hook) => hook?.cleanup?.()),
  };
}
