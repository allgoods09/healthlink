// Rendered-Blade Chromium fixture; PHP feature tests verify the real POST and PDF endpoints.
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
// Generate the actual Blade fixture with CERTIFICATE_BROWSER_HTML set on the wizard render test.
const rendered = readFileSync(process.env.CERTIFICATE_BROWSER_HTML || '/tmp/healthlink-certificate-wizard.html', 'utf8');
const submissions = [];
let pdfRequests = 0;
const server = createServer(async (req, res) => {
    if (req.url.endsWith(manifest['resources/css/app.css'].file)) { res.setHeader('Content-Type', 'text/css'); return res.end(css); }
    if (req.url.endsWith(manifest['resources/js/app.js'].file)) { res.setHeader('Content-Type', 'text/javascript'); return res.end(js); }
    if (req.method === 'POST') {
        let body = '';
        for await (const chunk of req) body += chunk;
        submissions.push(Object.fromEntries(new URLSearchParams(body)));
        res.setHeader('Content-Type', 'text/html');
        return res.end('<main id="details">Certificate Details <a href="/certificate/pdf">Download PDF</a></main>');
    }
    if (req.url === '/certificate/pdf') { pdfRequests++; return res.end('PDF endpoint fixture'); }
    res.setHeader('Content-Type', 'text/html');
    if (req.url === '/log') return res.end('<a href="/secretary/certificates/create">Issue Certificate</a>');
    res.end(rendered.replaceAll(/https?:\/\/(?:localhost|127\.0\.0\.1)(?::\d+)?/g, base));
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;
const profile = mkdtempSync(`${tmpdir()}/healthlink-certificate-`);
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
        const output = await call('Runtime.evaluate', { expression, returnByValue: true });
        if (output.exceptionDetails) throw new Error(output.exceptionDetails.exception?.description || output.exceptionDetails.text);
        return output.result.value;
    };
    const wait = async expression => {
        for (let i = 0; i < 150; i++) { if (await evaluate(expression)) return; await sleep(30); }
        throw new Error(`Timeout: ${expression}`);
    };

    await call('Page.enable');
    const state = 'window.Alpine.$data(document.querySelector("[data-certificate-wizard]"))';
    const clickNext = async () => {
        await evaluate('Array.from(document.querySelectorAll("[data-certificate-wizard] button")).find(button => button.getAttribute("@click") === "go(step + 1)").click()');
    };
    const choose = async type => {
        await evaluate(`{ const input=document.querySelector("#${type}_id");input.focus();input.value="${type === 'resident' ? 'Gavin' : '56'}";input.dispatchEvent(new Event("input",{bubbles:true})); }`);
        await wait(`Array.from(document.querySelector("#${type}_id").parentElement.querySelectorAll("button")).some(button => getComputedStyle(button.parentElement.parentElement).display !== "none")`);
        await evaluate(`document.querySelector("#${type}_id").parentElement.querySelector("button").dispatchEvent(new MouseEvent("mousedown",{bubbles:true,cancelable:true}));`);
        await wait(`${state}.recipient !== null`);
    };
    for (const type of ['resident', 'household']) {
        await call('Page.navigate', { url: base + '/log' });
        await wait('Boolean(document.querySelector("a"))');
        await evaluate('document.querySelector("a").click()');
        await wait(`window.Alpine && document.querySelector("[data-certificate-wizard]") && ${state}.step === 1`);
        await evaluate('const select=document.querySelector("#certificate_type");select.value="barangay_clearance";select.dispatchEvent(new Event("change",{bubbles:true}));');
        await clickNext();
        await wait(`${state}.step === 2`);
        // The global confirmation handler must not bypass the early-submit guard.
        await evaluate('document.querySelector("[data-certificate-wizard] form").requestSubmit(document.querySelector("[data-certificate-issue]"))');
        assert.equal(submissions.length, type === 'resident' ? 0 : 1);
        if (type === 'household') await evaluate('document.querySelector("input[type=radio][value=household]").click()');
        await choose(type);
        if (type === 'resident') {
            await evaluate('document.querySelector("input[type=radio][value=household]").click()');
            await wait(`${state}.residentId === "" && ${state}.householdId === ""`);
            assert.equal(await evaluate('new FormData(document.querySelector("[data-certificate-wizard] form")).has("resident_id")'), false);
            await evaluate('document.querySelector("input[type=radio][value=resident]").click()');
            await choose(type);
        }
        await clickNext();
        await wait(`${state}.step === 3`);
        await evaluate('const purpose=document.querySelector("#purpose");purpose.value="Employment";purpose.dispatchEvent(new Event("input",{bubbles:true}));');
        await evaluate('Array.from(document.querySelectorAll("[data-certificate-wizard] button")).find(button => button.getAttribute("@click") === "go(step - 1)").click()');
        await wait(`${state}.step === 2`);
        await clickNext();
        await wait(`${state}.step === 3`);
        assert.equal(await evaluate('document.querySelector("#purpose").value'), 'Employment');
        await clickNext();
        await wait(`${state}.step === 4`);
        assert.match(await evaluate(`${state}.localTimeLabel`), /October 5, 2026/);
        await evaluate('document.querySelector("[data-certificate-wizard] form").requestSubmit()');
        assert.equal(submissions.length, type === 'resident' ? 0 : 1);
        await call('Emulation.setDeviceMetricsOverride', { width: 375, height: 812, deviceScaleFactor: 1, mobile: true });
        assert.equal(await evaluate('document.documentElement.scrollWidth <= window.innerWidth'), true);
        await evaluate('document.querySelector("[data-certificate-issue]").click()');
        await wait('Boolean(document.querySelector("#details"))');
        const submitted = submissions.at(-1);
        assert.ok(submitted[type + '_id']);
        assert.equal(submitted[type === 'resident' ? 'household_id' : 'resident_id'], undefined);
        assert.equal(submitted.issued_to_name, undefined);
        assert.equal(submitted.issued_at, '2026-10-05T00:49');
        assert.ok(submitted.review_token);
        await evaluate('document.querySelector("a").click()');
        await wait('document.body.textContent === "PDF endpoint fixture"');
    }
    assert.equal(pdfRequests, 2);
    console.log('Certificate browser fixture passed: rendered Blade, both recipient flows, cleared inactive IDs, Back retention, early/implicit submit prevention, local-time review, normal final POST, Details/PDF navigation, mobile width.');

} finally {
    socket?.close();
    browser.kill('SIGTERM');
    await new Promise(resolve => server.close(resolve));
    await sleep(100);
    rmSync(profile, { recursive: true, force: true });
}
