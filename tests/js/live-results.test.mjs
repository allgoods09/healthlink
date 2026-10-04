import test from 'node:test';
import assert from 'node:assert/strict';
import { createLatestResults, ordinaryResultsLink, resultsUrl } from '../../resources/js/live-results.js';

const location = { href: 'https://healthlink.test/residents?page=3', origin: 'https://healthlink.test', pathname: '/residents' };
const response = html => ({ ok: true, redirected: false, headers: { get: () => 'text/html; charset=UTF-8' }, text: async () => html });
const deferred = () => { let resolve; const promise = new Promise(done => { resolve = done; }); return { resolve, promise }; };
function harness() {
    const requests = [], rows = [], urls = [], messages = [], loading = [], pending = [], timers = new Map();
    let timerId = 0;
    const controller = createLatestResults({
        fetch: (url, options) => { const task = deferred(); requests.push({ url, options, ...task }); return task.promise; },
        render: html => rows.push(html), commit: (url, mode) => urls.push([url.toString(), mode]),
        error: message => messages.push(message), busy: value => loading.push(value), pending: value => pending.push(value),
        timers: { setTimeout: fn => { timers.set(++timerId, fn); return timerId; }, clearTimeout: id => timers.delete(id) },
    });
    return { ...controller, requests, rows, urls, messages, loading, pending, timers };
}

test('rapid typing debounces to one GET with same-origin credentials', async () => {
    const state = harness();
    for (const term of ['C', 'Cr', 'Cri', 'Cris']) state.schedule(new URL(`https://healthlink.test/residents?search=${term}`), 300);
    assert.equal(state.requests.length, 0);
    assert.equal(state.timers.size, 1);
    const run = [...state.timers.values()][0]();
    assert.equal(state.requests.length, 1);
    assert.equal(state.requests[0].options.credentials, 'same-origin');
    assert.equal(state.requests[0].options.method, undefined); // fetch defaults to GET
    state.requests[0].resolve(response('Cris'));
    await run;
    assert.deepEqual(state.rows, ['Cris']);
    assert.equal(state.urls[0][1], 'replace');
});

test('out-of-order responses cannot overwrite the latest query even if abort is ignored', async () => {
    const state = harness();
    const old = state.schedule(new URL('https://healthlink.test/residents?search=C'));
    const latest = state.schedule(new URL('https://healthlink.test/residents?search=Cris'));
    assert.equal(state.requests[0].options.signal.aborted, true);
    state.requests[1].resolve(response('latest'));
    await latest;
    state.requests[0].resolve(response('stale'));
    await old;
    assert.deepEqual(state.rows, ['latest']);
    assert.equal(state.urls.length, 1);
});

test('typing a newer query invalidates an old response before the debounce expires', async () => {
    const state = harness();
    const old = state.schedule(new URL(location.href));
    state.schedule(new URL('https://healthlink.test/residents?search=new'), 300);
    state.requests[0].resolve(response('stale during debounce'));
    await old;
    assert.deepEqual(state.rows, []);
    assert.deepEqual(state.urls, []);
    assert.equal(state.pending.at(-1), true);
});

test('failed request keeps rendered results and URL intact and supports retry', async () => {
    const state = harness();
    const run = state.schedule(new URL(location.href));
    state.requests[0].resolve({ ...response('SQL private error'), ok: false });
    await run;
    assert.deepEqual(state.rows, []);
    assert.deepEqual(state.urls, []);
    assert.match(state.messages.at(-1), /Try searching/);
    assert.doesNotMatch(state.messages.at(-1), /SQL|private/);
    assert.equal(state.loading.at(-1), false);
    assert.equal(state.pending.at(-1), false);
    const retry = state.schedule(new URL(location.href));
    state.requests[1].resolve(response('success'));
    await retry;
    assert.deepEqual(state.rows, ['success']);
});

test('login redirects and non-HTML responses are not rendered', async () => {
    for (const bad of [{ ...response('login'), redirected: true }, { ...response('json'), headers: { get: () => 'application/json' } }]) {
        const state = harness();
        const run = state.schedule(new URL(location.href)); state.requests[0].resolve(bad); await run;
        assert.equal(state.rows.length, 0);
    }
});

test('pagination can push history while popstate refresh does not add entries', async () => {
    const state = harness();
    for (const mode of ['push', 'none']) {
        const run = state.schedule(new URL(location.href), 0, mode);
        state.requests.at(-1).resolve(response(mode)); await run;
    }
    assert.deepEqual(state.urls.map(([, mode]) => mode), ['push', 'none']);
});

test('URL serialization keeps filters and sort but resets pagination', () => {
    const url = resultsUrl('/residents', [['search', 'cris & rose'], ['purok', '2'], ['status', 'active'], ['sort', 'name'], ['page', '7']], location);
    assert.equal(url.searchParams.get('search'), 'cris & rose');
    assert.equal(url.searchParams.get('purok'), '2');
    assert.equal(url.searchParams.get('sort'), 'name');
    assert.equal(url.searchParams.has('page'), false);
    assert.equal(resultsUrl('https://other.test/residents', [], location), null);
    assert.equal(resultsUrl('/logout', [], location), null);
});

test('ordinary and destructive navigation are not claimed by result link eligibility', () => {
    const anchor = (href, attributes = {}) => ({ href, hasAttribute: name => Object.hasOwn(attributes, name), getAttribute: name => attributes[name] });
    assert.ok(ordinaryResultsLink({ button: 0 }, anchor('https://healthlink.test/residents?page=2'), location));
    for (const [event, link] of [
        [{ button: 0 }, anchor('https://healthlink.test/residents/1/edit')],
        [{ button: 0 }, anchor('https://other.test/residents')],
        [{ button: 0 }, anchor('https://healthlink.test/residents', { target: '_blank' })],
        [{ button: 0 }, anchor('https://healthlink.test/residents', { download: '' })],
        [{ button: 0, ctrlKey: true }, anchor(location.href)],
    ]) assert.equal(ordinaryResultsLink(event, link, location), null);
});

test('cancelled page departure never commits a late response', async () => {
    const state = harness(); const run = state.schedule(new URL(location.href));
    state.cancel(); state.requests[0].resolve(response('late')); await run;
    assert.equal(state.rows.length, 0);
});
