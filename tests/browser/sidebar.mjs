// Rendered portal shell; hold the app module to verify the pre-Alpine desktop layout.
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
let assetGate = Promise.resolve(), releaseAssets = () => {};
const holdAssets = () => { assetGate = new Promise(resolve => { releaseAssets = resolve; }); };
const server = createServer(async (req, res) => {
    if (req.url.endsWith(manifest['resources/css/app.css'].file)) { res.setHeader('Content-Type', 'text/css'); return res.end(css); }
    if (req.url.endsWith(manifest['resources/js/app.js'].file)) {
        await assetGate;
        res.setHeader('Content-Type', 'text/javascript'); return res.end(js);
    }
    res.setHeader('Content-Type', 'text/html');
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
        throw new Error(`Timeout: ${expression}; ${JSON.stringify(await evaluate('({open:window.Alpine?.$data(document.body).sidebarOpen,stored:sessionStorage.getItem("healthlink.sidebar.scroll.portal-secretary"),y:document.querySelector("aside nav").scrollTop,display:getComputedStyle(document.querySelector("aside")).display})'))}`);
    };


    await call('Page.enable');
    await call('Emulation.setDeviceMetricsOverride', { width: 1280, height: 600, deviceScaleFactor: 1, mobile: false });
    const state = 'window.Alpine.$data(document.body)';
    const nav = 'document.querySelector("aside nav[x-ref=sidebarScroll]")';
    const snapshot = () => evaluate(`({
        display:getComputedStyle(document.querySelector("[data-sidebar]")).display,
        margin:getComputedStyle(document.querySelector("[data-sidebar-content]")).marginLeft,
        context:document.body.getAttribute("x-data"), ready:document.body.hasAttribute("data-sidebar-ready"),
        scroll:${nav}.scrollTop, windowY:window.scrollY
    })`);
    const navigate = async () => {
        holdAssets();
        await call('Page.navigate', { url: base + '/secretary/certificates/create' });
        await wait('document.querySelector("[data-sidebar]") && document.styleSheets.length > 0 && getComputedStyle(document.querySelector("[data-sidebar]")).width === "288px"');
    };
    for (const preference of [null, '1', '0', 'unavailable']) {
        const seed = await call('Page.addScriptToEvaluateOnNewDocument', { source:
            `localStorage.clear();sessionStorage.clear();${preference === 'unavailable'
                ? 'Object.defineProperty(window,"localStorage",{get(){throw Error("blocked")}});'
                : preference === null ? '' : 'localStorage.setItem("healthlink.sidebar.desktop.open",' + JSON.stringify(preference) + ');'}` });
        await navigate();
        const before = await snapshot();
        assert.equal(before.ready, false);
        assert.equal(before.display, preference === '0' ? 'none' : 'flex');
        assert.equal(before.margin, preference === '0' ? '0px' : '288px');
        assert.equal(before.context, "sidebarLayout('portal-secretary')");
        assert.equal(await evaluate('Boolean(window.Alpine)'), false);
        releaseAssets();
        await wait('document.body.hasAttribute("data-sidebar-ready")');
        const after = await snapshot();
        assert.equal(after.display, before.display); assert.equal(after.margin, before.margin);
        assert.equal(await evaluate(`${state}.sidebarOpen`), preference !== '0');
        await call('Page.removeScriptToEvaluateOnNewDocument', { identifier: seed.identifier });
    }
    // A full document replacement uses the same saved state; only the results below are fixture routes.
    await navigate(); releaseAssets(); await wait('document.body.hasAttribute("data-sidebar-ready")');
    await evaluate(`if (!${state}.sidebarOpen) document.querySelector('[aria-label="Toggle sidebar"]').click();`);
    await wait(`${state}.sidebarOpen`);
    await evaluate(`${nav}.scrollTop=150;`);
    await wait('sessionStorage.getItem("healthlink.sidebar.scroll.portal-secretary")==="150"');
    await navigate(); releaseAssets(); await wait('document.body.hasAttribute("data-sidebar-ready")');
    assert.equal(await evaluate(`${nav}.scrollTop`), 150);
    await evaluate(`${nav}.scrollTop=180;`);
    await wait('sessionStorage.getItem("healthlink.sidebar.scroll.portal-secretary")==="180"');
    await navigate();
    assert.equal((await snapshot()).display, 'flex');
    releaseAssets(); await wait('document.body.hasAttribute("data-sidebar-ready")');
    assert.equal(await evaluate(`${nav}.scrollTop`), 180);
    assert.equal(await evaluate('window.scrollY'), 0);
    await evaluate('document.querySelector("[aria-label=\\\"Toggle sidebar\\\"]").click()');
    await wait(`!${state}.sidebarOpen`);
    assert.equal(await evaluate('sessionStorage.getItem("healthlink.sidebar.scroll.portal-secretary")'), '180');
    await navigate();
    assert.equal((await snapshot()).display, 'none'); assert.equal((await snapshot()).margin, '0px');
    releaseAssets(); await wait('document.body.hasAttribute("data-sidebar-ready")');
    assert.equal(await evaluate('sessionStorage.getItem("healthlink.sidebar.scroll.portal-secretary")'), '180');
    await evaluate('document.querySelector("[aria-label=\\\"Toggle sidebar\\\"]").click()');
    await wait(`${state}.sidebarOpen && ${nav}.scrollTop === 180`);
    // Offscreen active items are revealed without touching document scroll.
    const oversized = await call('Page.addScriptToEvaluateOnNewDocument', { source: 'sessionStorage.setItem("healthlink.sidebar.scroll.portal-secretary","99999");' });
    await navigate(); releaseAssets(); await wait('document.body.hasAttribute("data-sidebar-ready")');
    await call('Page.removeScriptToEvaluateOnNewDocument', { identifier: oversized.identifier });
    assert.equal(await evaluate(`(() => { const n=${nav}, a=n.querySelector('[aria-current="page"]').getBoundingClientRect(), v=n.getBoundingClientRect();return a.top>=v.top-1 && a.bottom<=v.bottom+1 && n.scrollTop<=n.scrollHeight-n.clientHeight; })()`), true);
    await wait(`sessionStorage.getItem("healthlink.sidebar.scroll.portal-secretary") === String(${nav}.scrollTop)`);
    const saved = await evaluate('sessionStorage.getItem("healthlink.sidebar.scroll.portal-secretary")');
    await call('Emulation.setDeviceMetricsOverride', { width: 375, height: 812, deviceScaleFactor: 1, mobile: true });
    await navigate();
    assert.equal((await snapshot()).display, 'none'); assert.equal((await snapshot()).margin, '0px');
    releaseAssets(); await wait('document.body.hasAttribute("data-sidebar-ready")');
    assert.equal(await evaluate(`${state}.sidebarOpen`), false);
    await evaluate('document.querySelector("[aria-label=\\\"Toggle sidebar\\\"]").click()');
    await wait(`${state}.sidebarOpen && ${nav}.clientHeight > 0 && ${nav}.getClientRects().length > 0`);
    await evaluate(`${nav}.scrollTop=20;`);
    await wait(`${nav}.scrollTop === 20`);
    assert.equal(await evaluate('sessionStorage.getItem("healthlink.sidebar.scroll.portal-secretary")'), saved);
    // Prevent navigation in this assertion only, to inspect the existing mobile close-on-click handler.
    await evaluate(`{ const link=${nav}.querySelector('a');link.addEventListener('click',e=>e.preventDefault(),{once:true});link.click(); }`);
    await wait(`!${state}.sidebarOpen`);
    assert.equal(await evaluate('sessionStorage.getItem("healthlink.sidebar.scroll.portal-secretary")'), saved);
    assert.equal(await evaluate('document.documentElement.scrollWidth <= window.innerWidth'), true);
    console.log('Sidebar browser fixture passed: delayed-JS pre-paint default/open/collapsed/storage failure, matching Alpine handoff, real Certificate portal context, full-document scroll restoration, collapse/navigate/reopen, clamping and active visibility, unchanged window scroll, mobile isolation.');

} finally {
    socket?.close();
    browser.kill('SIGTERM');
    await new Promise(resolve => server.close(resolve));
    await sleep(100);
    rmSync(profile, { recursive: true, force: true });
}
