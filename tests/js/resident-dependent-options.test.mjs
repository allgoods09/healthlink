import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const root = new URL('../../resources/views/admin/geometry/residents/', import.meta.url);
const guard = readFileSync(new URL('partials/dependent-options.blade.php', root), 'utf8');
const household = id => ({ id, household_no: String(id), household_address: 'Synthetic address' });
const purok = id => ({ id, purok_number: id, purok_name: 'Synthetic purok' });
const flush = () => new Promise(resolve => setImmediate(resolve));

function form(page, role, initial = ['1', '', '', [purok('A'), purok('B')], []]) {
    const source = readFileSync(new URL(page + '.blade.php', root), 'utf8')
        .match(/<script>([\s\S]*?)<\/script>/)[1]
        .replace("@include('admin.geometry.residents.partials.dependent-options')", guard)
        .replace("@js($routePrefix === 'secretary')", String(role === 'secretary'));
    const requests = [];
    const context = { window: { rankHouseholdOptions: options => options, setTimeout }, fetch: url =>
        new Promise((resolve, reject) => requests.push({ url, reject, resolve,
            success: options => resolve({ ok: true, json: async () => options }),
            fail: () => resolve({ ok: false, json: () => { throw Error('Must not parse failed HTTP'); } }),
        })) };
    vm.runInNewContext(source, context);
    const state = context.residentForm('/puroks', '/households', ...initial);
    state.$refs = { householdSearchInput: { setCustomValidity() {} }, purokSelect: {} };
    state.$nextTick = callback => callback();
    return { state, requests };
}

for (const page of ['create', 'edit']) for (const role of ['secretary', 'admin']) {
    const prefix = role + ' ' + page;
    test(prefix + ': valid preloads/old IDs survive init with no requests; first change clears immediately', () => {
        const { state: s, requests } = form(page, role, ['1', 'A', '10', [purok('A')], [household(10)]]);
        s.init();
        assert.equal(requests.length, 0);
        assert.equal(s.householdId, '10');
        assert.equal(s.householdSearchQuery, '#10 - Synthetic address');
        s.purokId = 'B'; s.loadHouseholds();
        assert.equal(s.householdId, '');
        assert.equal(s.households.length, 0);
        assert.equal(s.householdSearchQuery, '');
        assert.equal(s.householdLoading, true);
        assert.equal(requests.length, 1);
    });
    test(prefix + ': normal success and empty success', async () => {
        const { state: s, requests } = form(page, role);
        s.purokId = 'A'; const done = s.loadHouseholds();
        requests[0].success([household(10)]); await done;
        assert.equal(s.households[0].id, 10);
        assert.equal(s.householdLoading, false);
        assert.equal(s.householdError, '');
        const empty = s.loadHouseholds(); requests[1].success([]); await empty;
        assert.equal(s.households.length, 0); assert.equal(s.householdError, '');
    });
    test(prefix + ': late A success/error/finally cannot overwrite B or stop B loading', async () => {
        for (const fail of [false, true]) {
            const { state: s, requests } = form(page, role);
            s.purokId = 'A'; const a = s.loadHouseholds();
            s.purokId = 'B'; const b = s.loadHouseholds();
            fail ? requests[0].reject(Error('offline')) : requests[0].success([household(10)]);
            await a;
            assert.equal(s.householdLoading, true);
            assert.equal(s.householdError, '');
            requests[1].success([household(20)]); await b;
            assert.equal(s.households[0].id, 20);
            assert.equal(s.householdLoading, false);
        }
    });
    test(prefix + ': B succeeds first, stale A success and failure do nothing', async () => {
        for (const fail of [false, true]) {
            const { state: s, requests } = form(page, role);
            s.purokId = 'A'; const a = s.loadHouseholds();
            s.purokId = 'B'; const b = s.loadHouseholds();
            requests[1].success([household(20)]); await b; s.selectHousehold(s.households[0]);
            fail ? requests[0].reject(Error('offline')) : requests[0].success([household(10)]);
            await a;
            assert.equal(s.households[0].id, 20); assert.equal(s.householdId, '20');
            assert.equal(s.householdError, ''); assert.equal(s.householdLoading, false);
        }
    });
    test(prefix + ': A/B/A requires generation, not just parent equality', async () => {
        const { state: s, requests } = form(page, role);
        s.purokId = 'A'; const a1 = s.loadHouseholds();
        s.purokId = 'B'; const b = s.loadHouseholds();
        s.purokId = 'A'; const a2 = s.loadHouseholds();
        requests[2].success([household(30)]); await a2;
        requests[0].success([household(10)]); requests[1].success([household(20)]); await Promise.all([a1, b]);
        assert.equal(s.households[0].id, 30); assert.equal(s.householdError, '');
    });
    test(prefix + ': clear during flight never repopulates or requests empty parent', async () => {
        const { state: s, requests } = form(page, role);
        s.purokId = 'A'; const a = s.loadHouseholds();
        s.purokId = ''; await s.loadHouseholds();
        requests[0].success([household(10)]); await a;
        assert.equal(requests.length, 1); assert.equal(s.households.length, 0);
        assert.equal(s.householdId, ''); assert.equal(s.householdLoading, false); assert.equal(s.householdError, '');
    });
    test(prefix + ': current HTTP/network/invalid-JSON failures end safely and can retry', async () => {
        for (const failure of ['http', 'network', 'json', 'shape']) {
            const { state: s, requests } = form(page, role, ['1', 'A', '10', [purok('A')], [household(10)]]);
            const done = s.loadHouseholds();
            if (failure === 'http') requests[0].fail();
            if (failure === 'network') requests[0].reject(Error('offline'));
            if (failure === 'json') requests[0].resolve({ ok: true, json: async () => { throw Error('invalid'); } });
            if (failure === 'shape') requests[0].success({ message: 'not options' });
            await done;
            assert.equal(s.households.length, 0); assert.equal(s.householdId, '');
            assert.equal(s.householdLoading, false); assert.match(s.householdError, /Unable to load households/);
            const retry = s.loadHouseholds(); assert.equal(s.householdError, '');
            requests[1].success([household(20)]); await retry; assert.equal(s.households[0].id, 20);
        }
    });
    test(prefix + ': initialization fallback preserves selected IDs until valid options arrive', async () => {
        const { state: s, requests } = form(page, role, ['1', 'A', '10', [], []]);
        s.init(); assert.equal(s.purokId, 'A'); assert.equal(s.householdId, '10');
        requests[0].success([purok('A')]); await flush();
        assert.equal(requests.length, 2); assert.equal(s.householdId, '10');
        requests[1].success([household(10)]); await flush();
        assert.equal(s.householdId, '10'); assert.equal(s.householdSearchQuery, '#10 - Synthetic address');
    });
    test(prefix + ': empty initialization makes no requests; household-only fallback retains old selection', async () => {
        const empty = form(page, role, ['', '', '', [], []]);
        empty.state.init(); assert.equal(empty.requests.length, 0);
        assert.equal(empty.state.purokLoading, false); assert.equal(empty.state.householdLoading, false);
        const { state: s, requests } = form(page, role, ['1', 'A', '10', [purok('A')], []]);
        s.init(); assert.equal(requests.length, 1); assert.equal(s.householdId, '10');
        requests[0].success([household(10)]); await flush();
        assert.equal(s.householdId, '10'); assert.equal(s.householdLoading, false);
    });
    test(prefix + ': late JSON body and parent identity alone cannot mutate current state', async () => {
        const { state: s, requests } = form(page, role);
        let resolveBody;
        s.purokId = 'A'; const a = s.loadHouseholds();
        requests[0].resolve({ ok: true, json: () => new Promise(resolve => { resolveBody = resolve; }) });
        await flush();
        s.purokId = 'B'; const b = s.loadHouseholds();
        requests[1].success([household(20)]); await b;
        resolveBody([household(10)]); await a; assert.equal(s.households[0].id, 20);
        s.purokId = 'A'; const next = s.loadHouseholds(); s.purokId = 'B';
        requests[2].success([household(30)]); await next; assert.equal(s.households.length, 0);
    });
    test(prefix + ': Barangay A/B and A/B/A protect Puroks and invalidate pending Households', async () => {
        const { state: s, requests } = form(page, role, ['1', 'A', '10', [purok('A')], [household(10)]]);
        const hh = s.loadHouseholds();
        s.barangayId = 'A'; const a1 = s.loadPuroks();
        assert.equal(s.purokId, ''); assert.equal(s.householdId, ''); assert.equal(s.households.length, 0);
        s.barangayId = 'B'; const b = s.loadPuroks();
        requests[2].success([purok('B')]); await b;
        requests[1].success([purok('A')]); requests[0].success([household(10)]); await Promise.all([a1, hh]);
        assert.equal(s.puroks[0].id, 'B'); assert.equal(s.households.length, 0);
        s.barangayId = 'A'; const oldA = s.loadPuroks();
        s.barangayId = 'B'; const oldB = s.loadPuroks();
        s.barangayId = 'A'; const newA = s.loadPuroks();
        requests[5].success([purok('new-A')]); await newA;
        requests[3].success([purok('old-A')]); requests[4].success([purok('old-B')]); await Promise.all([oldA, oldB]);
        assert.equal(s.puroks[0].id, 'new-A');
    });
    test(prefix + ': Barangay clear/stale failure/current failure keep hierarchy safe', async () => {
        const { state: s, requests } = form(page, role);
        const a = s.loadPuroks(); s.barangayId = ''; await s.loadPuroks();
        requests[0].success([purok('A')]); await a;
        assert.equal(s.puroks.length, 0); assert.equal(s.households.length, 0); assert.equal(requests.length, 1);
        s.barangayId = 'A'; const old = s.loadPuroks();
        s.barangayId = 'B'; const current = s.loadPuroks();
        requests[2].success([purok('B')]); await current;
        requests[1].reject(Error('old failure')); await old;
        assert.equal(s.puroks[0].id, 'B'); assert.equal(s.purokError, '');
        const bad = s.loadPuroks(); requests[3].fail(); await bad;
        assert.equal(s.puroks.length, 0); assert.equal(s.purokId, ''); assert.equal(s.householdId, '');
        assert.equal(s.purokLoading, false); assert.match(s.purokError, /Unable to load puroks/);
    });
    test(prefix + ': stale Purok errors cannot stop current loading; current network/JSON failures are local', async () => {
        const { state: s, requests } = form(page, role);
        const old = s.loadPuroks(); s.barangayId = 'B'; const current = s.loadPuroks();
        requests[0].reject(Error('stale')); await old;
        assert.equal(s.purokLoading, true); assert.equal(s.purokError, '');
        requests[1].success([purok('B')]); await current;
        for (const failure of ['network', 'json', 'shape']) {
            const index = requests.length, done = s.loadPuroks();
            if (failure === 'network') requests[index].reject(Error('offline'));
            if (failure === 'json') requests[index].resolve({ ok: true, json: async () => { throw Error('invalid'); } });
            if (failure === 'shape') requests[index].success({});
            await done;
            assert.equal(s.puroks.length, 0); assert.equal(s.purokId, ''); assert.equal(s.householdId, '');
            assert.equal(s.purokLoading, false); assert.match(s.purokError, /Unable to load puroks/);
        }
    });
}
