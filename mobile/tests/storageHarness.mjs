import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { DatabaseSync } from 'node:sqlite';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const require = createRequire(import.meta.url);
const ts = require('typescript');

// Run the production storage SQL against SQLite without a React Native runtime.
export async function storageHarness() {
  const sqlite = new DatabaseSync(':memory:');
  const secrets = new Map();
  let beforeQuery = async () => {};
  const params = args => (Array.isArray(args[0]) ? args[0] : args).map(value => value ?? null);
  const db = {
    async execAsync(sql) { await beforeQuery(sql); sqlite.exec(sql); },
    async runAsync(sql, ...args) {
      await beforeQuery(sql);
      return sqlite.prepare(sql).run(...params(args));
    },
    async getAllAsync(sql, ...args) {
      await beforeQuery(sql);
      return sqlite.prepare(sql).all(...params(args));
    },
    async getFirstAsync(sql, ...args) {
      await beforeQuery(sql);
      return sqlite.prepare(sql).get(...params(args)) ?? null;
    },
    async withTransactionAsync(task) {
      sqlite.exec('BEGIN');
      try { await task(); sqlite.exec('COMMIT'); }
      catch (error) { sqlite.exec('ROLLBACK'); throw error; }
    },
    async withExclusiveTransactionAsync(task) {
      return db.withTransactionAsync(() => task(db));
    },
  };
  const cache = new Map();
  function load(relative) {
    if (cache.has(relative)) return cache.get(relative);
    const filename = fileURLToPath(new URL(`../src/lib/${relative}.ts`, import.meta.url));
    const code = ts.transpileModule(readFileSync(filename, 'utf8'), {
      compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
    }).outputText;
    const module = { exports: {} };
    const localRequire = name => {
      if (name === 'expo-sqlite') return { openDatabaseAsync: async () => db };
      if (name === 'expo-secure-store') return {
        setItemAsync: async (key, value) => { secrets.set(key, value); },
        getItemAsync: async key => secrets.get(key) ?? null,
        deleteItemAsync: async key => { secrets.delete(key); },
      };
      if (name === './syncGuard') return load('syncGuard');
      throw new Error(`Unexpected storage dependency: ${name}`);
    };
    vm.runInThisContext(`(function(require, module, exports) {${code}\n})`, { filename })(
      localRequire, module, module.exports
    );
    cache.set(relative, module.exports);
    return module.exports;
  }
  const storage = load('storage');
  await storage.initializeStorage();
  return {
    storage, guard: load('syncGuard'), db, sqlite,
    intercept: callback => { beforeQuery = callback; },
    close: () => sqlite.close(),
  };
}
