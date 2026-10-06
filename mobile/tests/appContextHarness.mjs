import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import vm from 'node:vm';
import { storageHarness } from './storageHarness.mjs';

const require = createRequire(import.meta.url);
const ts = require('typescript');
export const bootstrap = id => ({
  resident_contract_version: 2,
  user: { id }, assignment: { barangay: { id }, purok: { id } }, server_time: '2026-10-03',
  households: [], residents: [], field_visits: [], risk_assessments: [],
});

// Drive AppProvider's async session actions, not native UI rendering. Storage SQL
// is real; transport and hook scheduling are controlled to reproduce late replies.
export async function appContextHarness(overrides = {}, beforeBoot = async () => {}) {
  const h = await storageHarness();
  await beforeBoot(h.storage);
  const calls = [];
  const api = {
    mobileLogin: async (_url, { email }) => ({ token: `token-${email}`, user: { id: Number(email), role: 'bhw',
      assigned_barangay_id: Number(email), assigned_purok_id: Number(email) } }),
    mobileLogout: async () => ({ success: true }),
    mobileBootstrap: async (_url, token) => bootstrap(Number(token.split('-')[1])),
    mobileVerify: async (_url, token) => ({ valid: true, user: { id: Number(token.split('-')[1]),
      assigned_barangay_id: Number(token.split('-')[1]), assigned_purok_id: Number(token.split('-')[1]) } }),
    mobileCheckRelease: async () => ({ update: { available: false, required: false } }),
    mobileNotifications: async () => ({ notifications: [], unread_count: 0 }),
    mobileSync: async () => { throw new Error('Unexpected upload'); },
    ...overrides,
  };
  for (const name of Object.keys(api)) {
    const implementation = api[name];
    api[name] = (...args) => { calls.push({ name, args }); return implementation(...args); };
  }
  const hooks = [];
  let cursor = 0;
  let effects = [];
  const react = {
    createContext: () => ({ Provider: 'Provider' }),
    createElement: (type, props, ...children) => ({ type, props: { ...props, children } }),
    useState(initial) {
      const i = cursor++;
      if (!(i in hooks)) hooks[i] = typeof initial === 'function' ? initial() : initial;
      return [hooks[i], value => { hooks[i] = typeof value === 'function' ? value(hooks[i]) : value; }];
    },
    useRef(initial) {
      const i = cursor++;
      return hooks[i] ??= { current: initial };
    },
    useMemo: callback => callback(),
    useEffect(callback, deps) {
      const i = cursor++;
      const old = hooks[i];
      if (!old || deps.some((dep, index) => !Object.is(dep, old[index]))) effects.push(callback);
      hooks[i] = deps;
    },
  };
  const module = { exports: {} };
  const source = readFileSync(new URL('../src/context/AppContext.tsx', import.meta.url), 'utf8');
  const code = ts.transpileModule(source, { compilerOptions: {
    module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx: ts.JsxEmit.React, esModuleInterop: true,
  } }).outputText;
  const dependencies = {
    react,
    'react-native': { Platform: { OS: 'android' }, useColorScheme: () => 'light', AppState: { addEventListener: () => ({ remove() {} }) } },
    '@react-native-community/netinfo': { addEventListener(callback) { callback({ isConnected: true }); return () => {}; } },
    '../lib/storage': h.storage,
    '../lib/syncGuard': h.guard,
    '../lib/api': api,
    '../lib/config': { MOBILE_API_BASE_URL: 'https://test.invalid' },
    '../i18n': { i18n: { t: key => key }, setLocale() {} },
    '../theme': { resolveTheme: () => ({ colors: { surface: '#fff', text: '#000' } }) },
    '../../app.json': { expo: { version: 'test', android: { versionCode: 1 } } },
  };
  vm.runInThisContext(`(function(require, module, exports, setTimeout) {${code}\n})`)(
    name => { if (!(name in dependencies)) throw new Error(name); return dependencies[name]; },
    module, module.exports, callback => queueMicrotask(callback)
  );
  function render() {
    cursor = 0;
    effects = [];
    const result = module.exports.AppProvider({ children: null }).props.value;
    for (const effect of effects) effect();
    return result;
  }
  for (let n = 0; n < 50 && !render().isReady; n++) await new Promise(setImmediate);
  if (!render().isReady) throw new Error('AppProvider did not finish booting');
  return { ...h, calls, render };
}
