// Run after npm run build. Uses installed Chrome, no frontend/test dependency.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFileSync, mkdtempSync, rmSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';
const root = fileURLToPath(new URL('../../', import.meta.url));
const browserBin = process.env.BROWSER_BIN ?? ['/opt/google/chrome/chrome', '/usr/bin/google-chrome'].find(existsSync);
assert.ok(browserBin, 'Set BROWSER_BIN to an installed Chromium-compatible browser');
const manifest = JSON.parse(readFileSync(`${root}/public/build/manifest.json`));
const css = readFileSync(`${root}/public/build/${manifest['resources/css/app.css'].file}`);
const js = readFileSync(`${root}/public/build/${manifest['resources/js/app.js'].file}`);
const requests = [];
const page = url => `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/app.css"></head><body>
<aside><a id="sidebar" href="/other" data-navigation-skeleton="generic">Residents</a></aside><header>HealthLink</header>
<main class="navigation-loading-region" data-navigation-loading-region><div data-navigation-content>
<form method="GET" action="/lists" data-live-results-form="fixture"><label>Search<input name="search" value="${url.searchParams.get('search') ?? ''}"></label>
<label>Status<select name="status"><option value="">All</option><option value="active" ${url.searchParams.get('status') === 'active' ? 'selected' : ''}>Active</option></select></label>
<button type="submit">Apply Filters</button><a href="/lists">Reset</a></form>
<div data-export-control><button type="button">Export</button><a href="/export?${url.searchParams}">CSV</a></div>
<div data-live-results="fixture"><table><tbody><tr><td id="result">${url.searchParams.get('search') ?? 'all'} / ${url.searchParams.get('status') ?? 'all'} / ${url.searchParams.get('page') ?? '1'}</td><td><a id="edit" href="/edit">Edit</a><form method="POST" action="/save" data-confirm-skip><button>Save</button></form></td></tr></tbody></table></div>
<div data-live-results="fixture"><nav><a id="page-two" href="/lists?${new URLSearchParams([...url.searchParams].filter(([key]) => key !== 'page'))}&page=2">2</a></nav></div>
</div><div hidden class="navigation-skeleton" data-navigation-skeleton-layout="generic" aria-hidden="true">Loading</div><span data-navigation-loading-status></span></main>
<script type="module" src="/app.js"></script></body></html>`;
const server = createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    if (url.pathname === '/app.css') { res.setHeader('Content-Type', 'text/css'); return res.end(css); }
    if (url.pathname === '/app.js') { res.setHeader('Content-Type', 'text/javascript'); return res.end(js); }
    if (url.pathname !== '/lists') return res.end('Ordinary destination');
    requests.push({ query: url.search, async: Boolean(req.headers['x-healthlink-live-results']) });
    const send = () => {
        res.setHeader('Content-Type', 'text/html');
        if (url.searchParams.get('search') === 'error') { res.statusCode = 500; return res.end('private SQL stack trace'); }
        res.end(page(url));
    };
    if (url.searchParams.get('search') === 'slow') setTimeout(send, 800);
    else send();
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;
const profile = mkdtempSync(`${tmpdir()}/healthlink-live-results-`);
const browser = spawn(browserBin, ['--headless=new', '--no-sandbox', '--disable-extensions', '--no-proxy-server', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });
let socket;
try {
    const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
    for (let i = 0; i < 100 && !existsSync(`${profile}/DevToolsActivePort`); i++) await sleep(50);
    const port = readFileSync(`${profile}/DevToolsActivePort`, 'utf8').split('\n')[0];
    const tab = (await (await fetch(`http://127.0.0.1:${port}/json`)).json()).find(tab => tab.type === 'page');
    socket = new WebSocket(tab.webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
    let id = 0; const pending = new Map();
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data); if (!message.id) return;
        const task = pending.get(message.id); pending.delete(message.id);
        message.error ? task.reject(message.error) : task.resolve(message.result);
    });
    const call = (method, params = {}) => new Promise((resolve, reject) => {
        const current = ++id;
        const timeout = setTimeout(() => { pending.delete(current); reject(new Error(`CDP timeout: ${method}`)); }, 10000);
        pending.set(current, { resolve: value => { clearTimeout(timeout); resolve(value); }, reject: value => { clearTimeout(timeout); reject(value); } });
        socket.send(JSON.stringify({ id: current, method, params }));
    });
    const evaluate = async expression => {
        const output = await call('Runtime.evaluate', { expression, returnByValue: true });
        if (output.exceptionDetails) throw new Error(output.exceptionDetails.text);
        return output.result.value;
    };
    const wait = async expression => { for (let i = 0; i < 150; i++) { if (await evaluate(expression)) return; await sleep(30); } throw new Error(`Timeout: ${expression}`); };
    const type = async value => evaluate(`window.searchRef.value=${JSON.stringify(value)};window.searchRef.dispatchEvent(new Event('input',{bubbles:true}));`);
    await call('Page.enable');
    await call('Page.navigate', { url: `${base}/lists` });
    await wait('Boolean(document.querySelector(".live-table-search input[type=search]"))');
    await evaluate('window.searchRef=document.querySelector(".live-table-search input[type=search]");searchRef.focus();');
    for (const term of ['c', 'cr', 'cri', 'cris']) await type(term);
    await evaluate('searchRef.setSelectionRange(2,2)');
    await wait('document.querySelector("#result").textContent.startsWith("cris / ")');
    assert.equal(requests.filter(request => !request.async).length, 1, 'typing must not cause document requests');
    assert.equal(requests.filter(request => request.async).length, 1, 'rapid typing is debounced');
    assert.equal(await evaluate('document.activeElement===searchRef && searchRef.value==="cris" && searchRef.selectionStart===2'), true);
    assert.equal(await evaluate('document.querySelector("main").classList.contains("is-navigation-loading")'), false);
    await evaluate('document.querySelector(".filter-modal-trigger").click()');
    await wait('getComputedStyle(document.querySelector("select[name=status]")).visibility === "visible"');
    await evaluate('const select=document.querySelector("select[name=status]");select.focus();select.value="active";select.dispatchEvent(new Event("change",{bubbles:true}));');
    await wait('document.querySelector("#result").textContent==="cris / active / 1"');
    assert.equal(await evaluate('document.activeElement===document.querySelector("select[name=status]")'), true, 'filters remain focused and mounted');
    await evaluate('document.querySelector(".filter-modal-close").click()');
    assert.equal(await evaluate('new URL(location.href).searchParams.get("status")'), 'active');
    assert.equal(await evaluate('document.querySelector("[data-export-control] a").href.includes("status=active")'), true);
    await evaluate('document.querySelector("#page-two").focus();document.querySelector("#page-two").click()');
    await wait('document.querySelector("#result").textContent==="cris / active / 2"');
    assert.equal(await evaluate('document.activeElement.dataset.liveResults'), 'fixture', 'pagination leaves keyboard focus in updated results');
    await evaluate('history.back()');
    await wait('document.querySelector("#result").textContent==="cris / active / 1"');
    await evaluate('history.forward()');
    await wait('document.querySelector("#result").textContent==="cris / active / 2"');
    await type('slow');
    await wait('document.querySelector("[data-live-results]").getAttribute("aria-busy")==="true"');
    await type('latest');
    await wait('document.querySelector("#result").textContent.startsWith("latest / active")');
    await sleep(850);
    assert.equal(await evaluate('document.querySelector("#result").textContent'), 'latest / active / 1');
    await type('error');
    await wait('document.querySelector(".live-results-status").classList.contains("is-error")');
    assert.equal(await evaluate('document.querySelector("#result").textContent'), 'latest / active / 1');
    assert.equal(await evaluate('searchRef.value'), 'error');
    assert.equal(await evaluate('document.body.textContent.includes("SQL stack")'), false);
    await type('recovered');
    await wait('document.querySelector("#result").textContent.startsWith("recovered / active")');
    await call('Page.reload');
    await wait('Boolean(document.querySelector(".live-table-search input[type=search]"))');
    assert.equal(await evaluate('document.querySelector("input[type=search]").value'), 'recovered');
    assert.equal(await evaluate('document.querySelector("#result").textContent'), 'recovered / active / 1');
    assert.equal(await evaluate('(()=>{const event=new Event("submit",{bubbles:true,cancelable:true});document.querySelector("form[method=POST]").dispatchEvent(event);return event.defaultPrevented;})()'), false, 'POST submit is not captured by live results');
    const beforeOrdinary = requests.length;
    await evaluate('document.querySelector("#edit").click()');
    await wait('location.pathname==="/edit"');
    assert.equal(requests.length, beforeOrdinary, 'edit links stay ordinary navigation');
    await call('Page.navigate', { url: `${base}/lists?search=recovered&status=active` });
    await wait('Boolean(document.querySelector(".live-table-search"))');
    await evaluate('window.addEventListener("click",event=>event.preventDefault(),{once:true});document.querySelector("#sidebar").click()');
    assert.equal(await evaluate('document.querySelector("main").classList.contains("is-navigation-loading")'), true, 'navigation skeleton remains independent');
    await evaluate('window.dispatchEvent(new Event("pageshow"))');
    assert.equal(await evaluate('document.querySelector("main").classList.contains("is-navigation-loading")'), false);
    await call('Emulation.setScriptExecutionDisabled', { value: true });
    await call('Page.reload');
    await wait('Boolean(document.querySelector("form input[name=search]")) && !document.querySelector(".live-table-search")');
    await evaluate('document.querySelector("input[name=search]").value="fallback"');
    const point = await evaluate('(()=>{const r=document.querySelector("form button").getBoundingClientRect();return {x:r.x+5,y:r.y+5};})()');
    await call('Input.dispatchMouseEvent', { type: 'mousePressed', button: 'left', clickCount: 1, ...point });
    await call('Input.dispatchMouseEvent', { type: 'mouseReleased', button: 'left', clickCount: 1, ...point });
    await wait('location.search.includes("search=fallback")');
    assert.equal(requests.at(-1).async, false);
    console.log('PASS: Chromium actual bundled app: debounce, focus/cursor/value preservation, filter-only updates, pagination/history, race protection, export parity, error/retry, refresh parity, JS-disabled GET fallback, independent navigation skeleton.');
} finally {
    socket?.close(); browser.kill(); server.closeAllConnections(); server.close();
    await new Promise(resolve => browser.once('exit', resolve));
    rmSync(profile, { recursive: true, force: true, maxRetries: 10, retryDelay: 100 });
}
