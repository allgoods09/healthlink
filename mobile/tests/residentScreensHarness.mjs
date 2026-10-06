import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import vm from 'node:vm';
import { fixture } from './residentDirectoryFixture.mjs';

const require = createRequire(import.meta.url);
const ts = require('typescript');
const tick = () => new Promise(setImmediate);

// Controlled production JSX/hooks/callbacks, backed by real SQLite. Not native rendering.
export async function screenHarness(t, name, options = {}) {
  const h = await fixture(t, options.payload);
  const context = { assignment: { barangay: { id: 1 }, purok: { id: 1, display_name: 'Purok 1' } },
    dataVersion: 1, isOnline: false, ...options.context };
  const route = { params: options.params ?? {} };
  const calls = []; const navigation = { navigate: (...args) => calls.push(args) };
  const hooks = []; let cursor = 0; let effects = []; let tree; let focused = true;
  const timers = new Map(); let nextTimer = 0;
  const react = {
    createElement(type, props, ...children) {
      const combined = { ...props, children };
      return typeof type === 'function' ? type(combined) : { type, props: combined };
    },
    useState(initial) { const i = cursor++; if (!(i in hooks)) hooks[i] = initial;
      return [hooks[i], value => { hooks[i] = typeof value === 'function' ? value(hooks[i]) : value; }]; },
    useRef(initial) { const i = cursor++; return hooks[i] ??= { current: initial }; },
    useEffect(callback, deps) { const i = cursor++; const old = hooks[i];
      if (!old || deps.some((d, n) => !Object.is(d, old.deps[n]))) effects.push(() => { old?.cleanup?.(); hooks[i].cleanup = callback(); });
      hooks[i] = { deps, cleanup: old?.cleanup }; },
  };
  const theme = { colors: {}, spacing: { md: 16, sm: 8, xl: 32 }, radius: { md: 16, lg: 24 } };
  const native = Object.fromEntries(['ActivityIndicator', 'FlatList', 'Pressable', 'Text', 'TextInput', 'View', 'Modal', 'ScrollView'].map(n => [n, n]));
  native.StyleSheet = { create: value => value, absoluteFill: {} };
  const cache = new Map();
  function load(path) {
    if (cache.has(path)) return cache.get(path);
    const module = { exports: {} };
    const code = ts.transpileModule(readFileSync(new URL(`../src/${path}`, import.meta.url), 'utf8'), {
      compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx: ts.JsxEmit.React, esModuleInterop: true },
    }).outputText;
    const deps = {
      react, 'react-native': native, '@react-navigation/native': { useIsFocused: () => focused },
      '../context/AppContext': { useAppContext: () => context, useAppTheme: () => theme, useThemedStyles: fn => fn(theme) },
      '../lib/storage': { ...h.storage, ...options.storage }, '../lib/residentWorkflow': h.workflow,
      '../lib/residentPresentation': h.presentation, '../i18n': { i18n: { t: key => key } },
      '../components/MenuCard': { MenuCard: 'MenuCard' }, '../components/TopHeader': { TopHeader: 'TopHeader' },
      '../components/KeyboardShiftView': { KeyboardShiftView: 'KeyboardShiftView' },
    };
    const localRequire = dep => {
      if (dep === '../components/ResidentUi') return load('components/ResidentUi.tsx');
      if (dep === '../lib/format') return load('lib/format.ts');
      if (!(dep in deps)) throw new Error(dep); return deps[dep];
    };
    vm.runInThisContext(`(function(require, module, exports, setTimeout, clearTimeout){${code}})`)(localRequire,
      module, module.exports, fn => { const id = ++nextTimer; timers.set(id, fn); return id; }, id => timers.delete(id));
    cache.set(path, module.exports); return module.exports;
  }
  const screen = load(`screens/${name}Screen.tsx`)[`${name}Screen`];
  function render() { cursor = 0; effects = []; tree = screen({ navigation, route }); effects.forEach(fn => fn()); return tree; }
  async function settle() { for (let i = 0; i < 6; i++) { render(); await tick(); } render(); }
  function nodes(node = tree) {
    if (!node || typeof node !== 'object') return [];
    if (Array.isArray(node)) return node.flatMap(n => nodes(n));
    return [node, ...nodes(node.props?.children)];
  }
  const button = label => nodes().find(node => node.type === 'Pressable' && node.props.accessibilityLabel === label);
  const list = () => nodes().find(node => node.type === 'FlatList');
  t.after(() => { for (const hook of hooks) hook?.cleanup?.(); });
  await settle();
  return { ...h, context, route, calls, render, settle, nodes, list, button,
    input: () => nodes().find(node => node.type === 'TextInput'),
    flushDebounce: async () => { render(); const tasks = [...timers.values()]; timers.clear(); tasks.forEach(fn => fn()); await settle(); },
    setFocused(value) { focused = value; },
  };
}
