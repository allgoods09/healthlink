import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import vm from 'node:vm';
import { storageHarness } from './storageHarness.mjs';
import { withHouseholdContract } from './householdFixture.mjs';
const ts = createRequire(import.meta.url)('typescript');
const tick = () => new Promise(setImmediate);

export const householdDownload = () => withHouseholdContract({ resident_contract_version: 2, user: { id: 1 },
  assignment: { barangay: { id: 1 }, purok: { id: 1, display_name: 'Purok 1' } }, server_time: '2026-10-07',
  households: [{ id: 1, purok_id: 1, household_no: '2', household_address: 'Pilot home', is_active: false,
    is_social_aid_beneficiary: true, current_member_count: 4, current_head_name: 'Ana Ybañez', is_vacant: false },
    { id: 2, purok_id: 2, household_no: '10', household_address: 'Lookup address', current_head_name: 'Hidden person', is_active: true }],
  residents: [], field_visits: [{ id: 1, household_id: 1, visited_at: '2026-10-07', notes: 'Recorded history',
    recorded_by_name: 'Original BHW', photos: [{ path: 'visit-photos/private.jpg' }] }], risk_assessments: [] });

// Production callbacks and SQLite, with controlled hooks. Not a native renderer.
export async function householdUiHarness(t, name, options = {}) {
  const h = await storageHarness(); t.after(h.close);
  await h.storage.prepareDatasetForUser(1); await h.storage.replaceBootstrapData(options.payload ?? householdDownload());
  if (options.setup) await options.setup(h);
  const hooks = []; let cursor = 0; let effects = []; let removal; let focused = true; const timers = new Map(); let timerId = 0;
  const calls = []; const pickers = []; const confirmations = [];
  const react = {
    createElement: (type, props, ...children) => typeof type === 'function' ? type({ ...props, children }) : ({ type, props: { ...props, children } }),
    useState(initial) { const i = cursor++; if (!(i in hooks)) hooks[i] = typeof initial === 'function' ? initial() : initial;
      return [hooks[i], value => { hooks[i] = typeof value === 'function' ? value(hooks[i]) : value; }]; },
    useRef(initial) { return hooks[cursor++] ??= { current: initial }; },
    useEffect(callback, deps) { const i = cursor++; const old = hooks[i];
      if (!old || deps.some((d, n) => !Object.is(d, old.deps[n]))) effects.push(() => { old?.cleanup?.(); hooks[i].cleanup = callback(); });
      hooks[i] = { deps, cleanup: old?.cleanup }; },
  };
  const context = { user: { id: 1 }, assignment: householdDownload().assignment, dataVersion: 1,
    requestConfirmation: async request => { confirmations.push(request); return true; }, bumpDataVersion() {}, ...options.context };
  const navigation = Object.fromEntries(['navigate', 'goBack', 'dispatch', 'setOptions'].map(key => [key, (...args) => calls.push({ key, args })]));
  const route = { params: options.params ?? {} };
  const cache = new Map();
  const rn = Object.fromEntries(['Pressable', 'Text', 'TextInput', 'ScrollView', 'View', 'Modal', 'Switch', 'FlatList', 'Image', 'KeyboardAvoidingView'].map(n => [n, n]));
  Object.assign(rn, { Platform: { OS: options.platform ?? 'android' }, Alert: { alert() {} }, useWindowDimensions: () => ({ width: 320, height: 640 }),
    Keyboard: { dismiss: () => calls.push({ key: 'dismissKeyboard', args: [] }) },
    StyleSheet: { create: value => value, absoluteFill: { position: 'absolute' }, hairlineWidth: 1 } });
  const libs = {
    react, 'react-native': rn, '@react-navigation/native': { useIsFocused: () => focused, usePreventRemove: (blocked, callback) => { removal = { blocked, callback }; } },
    '@expo/vector-icons': { Ionicons: 'Ionicons' }, 'react-native-safe-area-context': { useSafeAreaInsets: () => ({ top: 24, bottom: 20 }) },
    '@react-native-community/datetimepicker': { __esModule: true, default: 'DateTimePicker',
      DateTimePickerAndroid: { open(value) { pickers.push(value); }, dismiss: async mode => { calls.push({ key: 'dismissPicker', args: [mode] }); } } },
    'expo-camera': { CameraView: props => { props.ref.current = { takePictureAsync: options.capture ?? (async () => ({ uri: 'file:///camera.jpg', base64: 'photo' })) }; return { type: 'CameraView', props }; },
      useCameraPermissions: () => [{ granted: true }, async () => ({ granted: true })] },
    'expo-image-picker': { useMediaLibraryPermissions: () => [{ granted: true }, async () => ({ granted: true })],
      launchImageLibraryAsync: options.gallery ?? (async () => ({ canceled: true, assets: [] })) },
    '../lib/storage': { ...h.storage, ...options.storage }, '../lib/useLocalEditor': { useLocalEditor() {} },
    '../components/KeyboardShiftView': { KeyboardShiftView: 'KeyboardShiftView' }, '../components/TopHeader': { TopHeader: 'TopHeader' },
    '../hooks/useKeyboardAwareScroll': { useKeyboardAwareScroll: () => ({ scrollRef: { current: null }, keyboardInset: 0 }) },
    '../i18n': { i18n: options.i18n ?? { t: (key, args) => args ? `${key}:${JSON.stringify(args)}` : key } },
  };
  function load(path) {
    if (cache.has(path)) return cache.get(path);
    const module = { exports: {} };
    const source = readFileSync(new URL(`../src/${path}`, import.meta.url), 'utf8');
    const code = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx: ts.JsxEmit.React, esModuleInterop: true } }).outputText;
    vm.runInThisContext(`(function(require, module, exports, setTimeout, clearTimeout){${code}})`)(n => {
      if (n in libs) return libs[n];
      if (n.startsWith('../')) return load(`${n.slice(3)}.${n.includes('components/') ? 'tsx' : 'ts'}`);
      if (n.startsWith('./')) return load(`lib/${n.slice(2)}.ts`);
      throw new Error(`Unexpected dependency ${n}`);
    }, module, module.exports, callback => { const id = ++timerId; timers.set(id, callback); return id; }, id => timers.delete(id));
    cache.set(path, module.exports); return module.exports;
  }
  const theme = load('theme.ts')[options.dark ? 'darkTheme' : 'lightTheme'];
  libs['../context/AppContext'] = { useAppContext: () => context, useAppTheme: () => theme, useThemedStyles: factory => factory(theme) };
  const screen = load(`screens/${name}Screen.tsx`)[`${name}Screen`]; let tree;
  function render() { cursor = 0; effects = []; tree = screen({ route, navigation }); effects.forEach(callback => callback()); return tree; }
  async function settle() { for (let i = 0; i < 12; i++) { render(); await tick(); } render(); }
  function nodes(node) { if (arguments.length === 0) node = tree; if (!node || typeof node !== 'object') return []; if (Array.isArray(node)) return node.flatMap(n => nodes(n));
    return [node, ...nodes(node.props?.children), ...nodes(node.props?.ListHeaderComponent), ...nodes(node.props?.ListEmptyComponent), ...nodes(node.props?.ListFooterComponent)]; }
  function button(label) { return nodes().find(n => n.type === 'Pressable' && n.props.accessibilityLabel === label); }
  function input(label) { return nodes().find(n => n.type === 'TextInput' && n.props.accessibilityLabel === label); }
  function texts(node = tree) { return nodes(node).filter(n => n.type === 'Text').flatMap(n => n.props.children.flat(Infinity)).filter(n => typeof n === 'string').join(' '); }
  function cards() { const list = nodes().find(n => n.type === 'FlatList'); return list.props.data.map(item => list.props.renderItem({ item })); }
  const flushTimers = () => { for (const [id, callback] of timers) { timers.delete(id); callback(); } };
  await settle();
  return { ...h, load, theme, context, route, calls, confirmations, pickers, render, settle, nodes, button, input, texts, cards, flushTimers,
    removal: () => removal, focus: value => { focused = value; }, unmount: () => hooks.forEach(hook => hook?.cleanup?.()) };
}
