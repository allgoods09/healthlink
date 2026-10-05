// Dependency-free Chromium fixture for the Secretary selection enhancement.
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
const options = Array.from({ length: 721 }, (_, i) => ({ id: String(i + 1), label: `Household #${i + 1} - Purok Centro` }));
const requests = [];
const html = `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/app.css"></head><body>
<main class="p-4"><form method="GET" action="/review" data-filter-panel="false" x-data="{ coverage: 'households' }">
<input type="hidden" name="step" value="4"><input type="hidden" name="document_type" value="household_rbi">
<label>Coverage<select id="coverage" name="coverage" x-model="coverage"><option value="barangay">Entire Barangay</option><option value="households" selected>Selected Households</option></select></label>
<fieldset id="households" x-show="coverage === 'households'" :disabled="coverage !== 'households'" x-data='rbiHouseholdSelector(${JSON.stringify(options)}, ["1"])'>
<label>Search<input id="search" type="search" x-model="query" @input="updateSearch()"></label>
<p id="count" x-text="selected.length + ' households selected'">1 households selected</p>
<div class="max-h-80 overflow-y-auto">${options.map(option => `<label class="block p-2" x-show="visibleIds.includes('${option.id}')"><input type="checkbox" name="household_ids[]" value="${option.id}" x-model="selected" ${option.id === '1' ? 'checked' : ''}>${option.label}</label>`).join('')}</div>
<p x-show="matches.length === 0">No households match your search.</p>
<button id="previous" type="button" @click="changePage(-1)" :disabled="page <= 1">Previous</button>
<span id="page" x-text="'Page ' + page + ' of ' + pageCount"></span>
<button id="next" type="button" @click="changePage(1)" :disabled="page >= pageCount">Next households</button></fieldset>
<button id="back" type="submit" name="step" value="1" formnovalidate>Back</button><button id="continue" type="submit">Review</button>
</form></main><script type="module" src="/app.js"></script></body></html>`;
const server = createServer((req, res) => {
    if (req.url === '/app.css') { res.setHeader('Content-Type', 'text/css'); return res.end(css); }
    if (req.url === '/app.js') { res.setHeader('Content-Type', 'text/javascript'); return res.end(js); }
    requests.push(req.url);
    res.setHeader('Content-Type', 'text/html');
    res.end(req.url.startsWith('/review') ? 'Server review' : html);
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;
const profile = mkdtempSync(`${tmpdir()}/healthlink-rbi-`);
const browser = spawn(browserBin, ['--headless=new', '--no-sandbox', '--disable-gpu', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
let socket;
try {
    const portFile = `${profile}/DevToolsActivePort`;
    for (let i = 0; i < 150 && !existsSync(portFile); i++) await sleep(30);
    const port = readFileSync(portFile, 'utf8').split('\n')[0];
    const tabs = await fetch(`http://127.0.0.1:${port}/json/list`).then(response => response.json());
    socket = new WebSocket(tabs.find(tab => tab.type === 'page').webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));
    let id = 0;
    const pending = new Map();
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (!message.id) return;
        const task = pending.get(message.id);
        pending.delete(message.id);
        message.error ? task.reject(message.error) : task.resolve(message.result);
    });
    const call = (method, params = {}) => new Promise((resolve, reject) => {
        const current = ++id;
        const timeout = setTimeout(() => { pending.delete(current); reject(new Error(`CDP timeout: ${method}`)); }, 10000);
        pending.set(current, { resolve: value => { clearTimeout(timeout); resolve(value); }, reject: value => { clearTimeout(timeout); reject(value); } });
        socket.send(JSON.stringify({ id: current, method, params }));
    });
    const evaluate = async expression => {
        const output = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (output.exceptionDetails) throw new Error(output.exceptionDetails.text);
        return output.result.value;
    };
    const wait = async expression => {
        for (let i = 0; i < 150; i++) { if (await evaluate(expression)) return; await sleep(30); }
        throw new Error(`Timeout: ${expression}`);
    };
    await call('Page.enable');
    await call('Page.navigate', { url: base });
    await wait('window.Alpine && Array.from(document.querySelectorAll("#households label.block")).filter(label => getComputedStyle(label).display !== "none").length === 12');
    assert.equal(await evaluate('document.querySelector("input[value=\\"1\\"]").checked'), true);
    const timings = await evaluate(`(async()=>{
        const state=Alpine.$data(document.querySelector('#households'));
        const input=document.querySelector('#search');input.focus();const measurements=[];
        for(const query of ['5','50','']) {
            const start=performance.now();input.value=query;input.dispatchEvent(new Event('input',{bubbles:true}));
            const inputMs=performance.now()-start;
            const immediateValue=input.value;const immediateQuery=state.query;
            await Promise.resolve();const updateMs=performance.now()-start;
            await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
            measurements.push({query,inputMs,updateMs,paintReadyMs:performance.now()-start,immediateValue,immediateQuery});
        }
        return measurements;
    })()`);
    for (const timing of timings) {
        assert.equal(timing.immediateValue, timing.query);
        assert.equal(timing.immediateQuery, timing.query);
        assert.ok(timing.paintReadyMs < 500, `Input paint-ready work must not retain the old freeze: ${JSON.stringify(timing)}`);
    }
    assert.equal(await evaluate('document.querySelector("#page").textContent'), 'Page 1 of 61');
    await evaluate('document.querySelector("#next").click()');
    await wait('getComputedStyle(document.querySelector("input[value=\\"13\\"]").parentElement).display !== "none"');
    await evaluate('document.querySelector("input[value=\\"13\\"]").click()');
    await wait('document.querySelector("#count").textContent === "2 households selected"');
    await evaluate('document.querySelector("#previous").click()');
    await wait('document.querySelector("#page").textContent === "Page 1 of 61"');
    await evaluate('document.querySelector("#next").click()');
    await evaluate('const search=document.querySelector("#search");search.value="#30 centro";search.dispatchEvent(new Event("input",{bubbles:true}));');
    await wait('getComputedStyle(document.querySelector("input[value=\\"30\\"]").parentElement).display !== "none"');
    const expectedMatches = options.filter(option => ['#30', 'centro'].every(word => option.label.toLocaleLowerCase().includes(word)));
    assert.equal(await evaluate('Array.from(document.querySelectorAll("#households label.block")).filter(label => getComputedStyle(label).display !== "none").length'), expectedMatches.length);
    assert.deepEqual(await evaluate('new FormData(document.querySelector("form")).getAll("household_ids[]")'), ['1', '13']);
    assert.equal(await evaluate('document.querySelector("#page").textContent'), 'Page 1 of 1');
    await evaluate(`{const input=document.querySelector('#search');for(const query of ['5','50','missing','']){input.value=query;input.dispatchEvent(new Event('input',{bubbles:true}));}}`);
    await wait('document.querySelector("#page").textContent === "Page 1 of 61"');
    assert.equal(await evaluate('document.querySelector("#search").value'), '');
    assert.equal(await evaluate('document.querySelector("input[value=\\"1\\"]").checked'), true);
    assert.equal(await evaluate('document.querySelector("input[value=\\"13\\"]").checked'), true);
    assert.deepEqual(await evaluate('new FormData(document.querySelector("form")).getAll("household_ids[]")'), ['1', '13']);
    await evaluate('const select=document.querySelector("#coverage");select.value="barangay";select.dispatchEvent(new Event("change",{bubbles:true}));');
    await wait('document.querySelector("#households").disabled');
    assert.deepEqual(await evaluate('new FormData(document.querySelector("form")).getAll("household_ids[]")'), []);
    await call('Emulation.setDeviceMetricsOverride', { width: 375, height: 812, deviceScaleFactor: 1, mobile: true });
    assert.equal(await evaluate('document.documentElement.scrollWidth <= window.innerWidth'), true);
    await evaluate('document.querySelector("#back").click()');
    await wait('document.body.textContent === "Server review"');
    assert.equal(new URL(requests.at(-1), base).searchParams.getAll('step').at(-1), '1');
    await call('Emulation.setScriptExecutionDisabled', { value: true });
    await call('Page.navigate', { url: base });
    await wait('Boolean(document.querySelector("#continue"))');
    await evaluate('document.querySelector("#continue").click()');
    await wait('document.body.textContent === "Server review"');
    assert.equal(new URL(requests.at(-1), base).searchParams.get('document_type'), 'household_rbi');
    assert.deepEqual(new URL(requests.at(-1), base).searchParams.getAll('household_ids[]'), ['1']);
    console.log(JSON.stringify({ result: 'passed', households: options.length, timings,
        coverage: 'responsive input, search parity, paging, retained selection, rapid typing, exclusive coverage, Back GET, no-JS GET, responsive layout' }));
} finally {
    socket?.close();
    browser.kill('SIGTERM');
    await new Promise(resolve => server.close(resolve));
    await sleep(100);
    rmSync(profile, { recursive: true, force: true });
}
