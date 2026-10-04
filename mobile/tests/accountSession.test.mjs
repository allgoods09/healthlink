import assert from 'node:assert/strict';
import { test } from 'node:test';
import { appContextHarness, bootstrap } from './appContextHarness.mjs';

const household = { household_no: '1', household_address: 'Synthetic', is_active: true, is_social_aid_beneficiary: false };
const login = id => ({ email: String(id), password: 'test-only' });
async function fixture(t, overrides, beforeBoot) {
  const h = await appContextHarness(overrides, beforeBoot);
  t.after(h.close);
  return h;
}

test('AppContext blocks account B before storing credentials, bootstrapping or uploading A drafts', async t => {
  const h = await fixture(t);
  await h.render().signIn(login(1));
  await h.storage.saveHousehold(household, 1);
  await h.render().signOut();
  const callsBefore = h.calls.length;
  await assert.rejects(h.render().signIn(login(2)), /accountSwitchBlocked/);
  assert.equal(h.render().isAuthenticated, false);
  assert.equal(await h.storage.loadToken(), null);
  assert.equal(await h.storage.getDatasetOwnerUserId(), '1');
  assert.equal((await h.storage.getPendingChangeSummary()).total, 1);
  assert.equal(h.calls.slice(callsBefore).some(call => ['mobileBootstrap', 'mobileSync'].includes(call.name)), false);
  assert.ok(h.calls.slice(callsBefore).some(call => call.name === 'mobileLogout' && call.args[1] === 'token-2'));
  await h.render().signIn(login(1));
  assert.equal(h.render().user.id, 1);
  assert.equal(h.render().bootstrapCompleted, true);
  assert.equal((await h.storage.getPendingChangeSummary()).total, 1);
});

test('AppContext allows clean account switching and normal logout/login', async t => {
  const h = await fixture(t);
  await h.render().signIn(login(1));
  await h.render().signOut();
  await h.render().signIn(login(2));
  assert.equal(h.render().user.id, 2);
  assert.equal(h.render().assignment.barangay.id, 2);
  assert.equal(await h.storage.getDatasetOwnerUserId(), '2');
  await h.render().signOut();
  await h.render().signIn(login(2));
  assert.equal(h.render().bootstrapCompleted, true);
});

test('logout completes locally even if the remote logout request never completes', async t => {
  const h = await fixture(t, { mobileLogout: () => new Promise(() => {}) });
  await h.render().signIn(login(1));
  await h.storage.saveHousehold(household, 1);
  await h.render().signOut();
  assert.equal(h.render().isAuthenticated, false);
  assert.equal(await h.storage.loadToken(), null);
  assert.equal((await h.storage.getPendingChangeSummary()).total, 1);
  assert.equal(h.calls.some(call => call.name === 'mobileSync'), false);
});

test('old in-flight bootstrap cannot restore account A after logout and account B login', async t => {
  let resolveA;
  const h = await fixture(t, {
    mobileBootstrap: async (_url, token) => token === 'token-1'
      ? new Promise(resolve => { resolveA = resolve; }) : bootstrap(2),
  });
  const oldLogin = h.render().signIn(login(1));
  while (!resolveA) await new Promise(setImmediate);
  await h.render().signOut();
  await h.render().signIn(login(2));
  resolveA(bootstrap(1));
  await oldLogin;
  assert.equal(h.render().user.id, 2);
  assert.equal(h.render().assignment.barangay.id, 2);
  assert.equal(await h.storage.getDatasetOwnerUserId(), '2');
  assert.equal(await h.storage.loadToken(), 'token-2');
});

test('stored session for the wrong account is not restored over pending work', async t => {
  const h = await fixture(t, {}, async storage => {
    await storage.prepareDatasetForUser(1);
    await storage.replaceBootstrapData(bootstrap(1));
    await storage.saveHousehold(household, 1);
    await storage.storeToken('token-2');
    await storage.setAppState('session_user', JSON.stringify({ id: 2 }));
  });
  assert.equal(h.render().isAuthenticated, false);
  assert.equal(await h.storage.loadToken(), null);
  assert.equal(await h.storage.getDatasetOwnerUserId(), '1');
  assert.equal((await h.storage.getPendingChangeSummary()).total, 1);
});

test('logout during upload preserves pending work and a late acknowledgment cannot clear it', async t => {
  let finishUpload;
  const h = await fixture(t, { mobileSync: async (_url, _token, payload) =>
    new Promise(resolve => { finishUpload = () => resolve({
      status: 'success', synced_at: '2026-10-03', failed_records: [],
      resolved_records: { households: [{ id: 1, mobile_uuid: payload.households[0].mobile_uuid }],
        residents: [], field_visits: [], risk_assessments: [] },
    }); }) });
  await h.render().signIn(login(1));
  await h.storage.saveHousehold(household, 1);
  const upload = h.render().syncNow();
  while (!finishUpload) await new Promise(setImmediate);
  await h.render().signOut();
  await assert.rejects(h.render().signIn(login(2)), /accountSwitchBlocked/);
  finishUpload();
  await upload;
  assert.equal(h.render().isAuthenticated, false);
  assert.equal((await h.storage.getPendingChangeSummary()).total, 1);
  assert.equal(await h.storage.getDatasetOwnerUserId(), '1');
  assert.equal(h.calls.filter(call => call.name === 'mobileSync').length, 1);
  assert.equal(h.calls.find(call => call.name === 'mobileSync').args[1], 'token-1');
});

test('a delayed verification failure for account A cannot log out account B', async t => {
  let rejectA;
  const h = await fixture(t, { mobileVerify: async (_url, token) => token === 'token-1'
    ? new Promise((_resolve, reject) => { rejectA = reject; }) : { valid: true } });
  await h.render().signIn(login(1));
  await h.render().signOut();
  await h.render().signIn(login(2));
  rejectA(new Error('Old token revoked'));
  await new Promise(setImmediate);
  assert.equal(h.render().isAuthenticated, true);
  assert.equal(h.render().user.id, 2);
  assert.equal(await h.storage.loadToken(), 'token-2');
});
