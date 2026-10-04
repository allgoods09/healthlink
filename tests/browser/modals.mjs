// Real bundled Alpine/CSS and actual Blade components, using installed Chromium.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFileSync, mkdtempSync, existsSync } from 'node:fs';
import { rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const browserBin = process.env.BROWSER_BIN ?? ['/opt/google/chrome/chrome', '/usr/bin/google-chrome'].find(existsSync);
assert.ok(browserBin, 'Set BROWSER_BIN to an installed Chromium browser');
const rendered = spawnSync('php', ['-r', `require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); echo Illuminate\\Support\\Facades\\Blade::render('<x-action-confirmation-modal /><x-modal name="fixture"><h2>Profile modal</h2><input id="modal-input"><button id="modal-cancel" x-on:click="$dispatch(\\\'close\\\')">Cancel</button><div style="height:1600px">Long content</div><button id="modal-last">Last</button></x-modal>');`], { cwd: root, encoding: 'utf8' });
assert.equal(rendered.status, 0, rendered.stderr);
const manifest = JSON.parse(readFileSync(`${root}/public/build/manifest.json`));
const assets = Object.fromEntries(['css', 'js'].map(type => [type, readFileSync(`${root}/public/build/${manifest[`resources/${type}/app.${type}`].file}`)]));
const html = `<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/app.css"></head><body>
<main id="background" style="height:2500px;padding:30px"><button id="open" x-data @click="$dispatch('open-modal','fixture')">Open</button><input id="background-input"><button id="background-button" onclick="window.backgroundClicks=(window.backgroundClicks||0)+1">Background</button>
<form method="POST" action="/never-submit"><button id="confirm-trigger">Deactivate</button></form>
<form method="GET" action="/"><label>Status<select name="status"><option value="">All</option><option value="active">Active</option></select></label><button>Apply Filters</button></form></main>
${rendered.stdout}<script type="module" src="/app.js"></script></body></html>`;
let submissions = 0;
const server = createServer((req, res) => {
    if (req.method === 'POST') submissions++;
    if (req.url === '/app.css') { res.setHeader('Content-Type', 'text/css'); return res.end(assets.css); }
    if (req.url === '/app.js') { res.setHeader('Content-Type', 'text/javascript'); return res.end(assets.js); }
    res.setHeader('Content-Type', 'text/html'); res.end(html);
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const profile = mkdtempSync(`${tmpdir()}/healthlink-modals-`);
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
        const timeout = setTimeout(() => reject(new Error(`CDP timeout: ${method}`)), 10000);
        pending.set(current, { resolve: value => { clearTimeout(timeout); resolve(value); }, reject });
        socket.send(JSON.stringify({ id: current, method, params }));
    });
    const evaluate = async expression => {
        const output = await call('Runtime.evaluate', { expression, returnByValue: true });
        if (output.exceptionDetails) throw new Error(JSON.stringify(output.exceptionDetails));
        return output.result.value;
    };
    const wait = async expression => {
        for (let i = 0; i < 150; i++) { if (await evaluate(expression)) return; await sleep(30); }
        throw new Error(`Timeout: ${expression}`);
    };
    const key = async (key, code, modifiers = 0) => {
        await call('Input.dispatchKeyEvent', { type: 'keyDown', key, code, modifiers });
        await call('Input.dispatchKeyEvent', { type: 'keyUp', key, code, modifiers });
    };
    const click = async (x, y) => {
        await call('Input.dispatchMouseEvent', { type: 'mousePressed', button: 'left', clickCount: 1, x, y });
        await call('Input.dispatchMouseEvent', { type: 'mouseReleased', button: 'left', clickCount: 1, x, y });
    };
    await call('Page.enable');
    await call('Emulation.setDeviceMetricsOverride', { width: 1200, height: 800, deviceScaleFactor: 1, mobile: false });
    await call('Page.navigate', { url: `http://127.0.0.1:${server.address().port}/` });
    await wait('Boolean(window.Alpine && document.querySelector(".filter-modal-trigger"))');
    await evaluate('window.profilePanel=document.querySelector("[x-modal-layer=show] [data-modal-panel]");window.before={width:document.querySelector("#background").getBoundingClientRect().width,style:document.documentElement.getAttribute("style"),body:document.body.getAttribute("style")};document.querySelector("#open").focus();document.querySelector("#open").click()');
    await wait('document.activeElement.id === "modal-input"');
    assert.equal(await evaluate('document.querySelector("#background").getBoundingClientRect().width'), await evaluate('before.width'));
    assert.equal(await evaluate('getComputedStyle(document.documentElement).overflowY'), 'hidden');
    assert.equal(await evaluate('document.querySelector("#background").inert'), true);
    await evaluate('document.querySelector("#background-input").focus()');
    assert.equal(await evaluate('document.activeElement.id'), 'modal-input');
    await click(30, 10); // Real backdrop click, not programmatic dispatch.
    await wait('!document.querySelector("#background").inert');
    assert.equal(await evaluate('window.backgroundClicks || 0'), 0, 'backdrop prevents background button activation');
    assert.equal(await evaluate('document.activeElement.id'), 'open');
    await sleep(250);
    await evaluate('document.querySelector("#open").click()');
    await wait('document.activeElement.id === "modal-input"');
    await key('Tab', 'Tab', 8);
    assert.equal(await evaluate('document.activeElement.id'), 'modal-last');
    await key('Tab', 'Tab');
    assert.equal(await evaluate('document.activeElement.id'), 'modal-input');
    await evaluate('document.querySelector("#modal-input").click()');
    assert.equal(await evaluate('document.querySelector("#background").inert'), true, 'inside click must not close');
    const scrollBefore = await evaluate('window.scrollY');
    await call('Input.dispatchMouseEvent', { type: 'mouseWheel', x: 20, y: 400, deltaX: 0, deltaY: 500 });
    await key('PageDown', 'PageDown'); await sleep(150);
    assert.equal(await evaluate('window.scrollY'), scrollBefore, 'wheel/keyboard cannot scroll background');
    await call('Input.dispatchMouseEvent', { type: 'mouseWheel', x: 600, y: 400, deltaX: 0, deltaY: 500 });
    await wait('profilePanel.scrollTop > 0');
    assert.equal(await evaluate('window.scrollY'), scrollBefore, 'panel scroll is contained');
    await evaluate('profilePanel.insertAdjacentHTML("beforeend",\'<form method="POST" action="/never-submit"><button id="nested-confirm">Delete</button></form>\');document.querySelector("#nested-confirm").focus();document.querySelector("#nested-confirm").click()');
    await wait('Boolean(document.activeElement.closest("[role=alertdialog]"))');
    await click(10, 10);
    await wait('document.activeElement.id === "nested-confirm"');
    assert.equal(await evaluate('getComputedStyle(document.documentElement).overflowY'), 'hidden', 'closing top modal leaves the underlying modal locked');
    assert.equal(await evaluate('document.querySelector("#background").inert'), true);
    await key('Escape', 'Escape');
    await wait('!document.querySelector("#background").inert');
    assert.equal(await evaluate('document.activeElement.id'), 'open');
    await sleep(250);
    await call('Input.dispatchMouseEvent', { type: 'mouseWheel', x: 20, y: 400, deltaX: 0, deltaY: 300 });
    await wait(`window.scrollY > ${scrollBefore}`);
    await evaluate('window.scrollTo(0,0)');

    for (let i = 0; i < 5; i++) {
        await evaluate('document.querySelector("#open").focus();document.querySelector("#open").click();window.dispatchEvent(new CustomEvent("close-modal",{detail:"fixture"}))');
        await wait('!document.querySelector("#background").inert');
    }
    assert.equal(await evaluate('document.documentElement.getAttribute("style") || ""'), await evaluate('before.style || ""'));
    assert.equal(await evaluate('document.body.getAttribute("style") || ""'), await evaluate('before.body || ""'));
    await sleep(350);
    await evaluate('document.querySelector("#confirm-trigger").focus();document.querySelector("#confirm-trigger").click()');
    await wait('Boolean(document.activeElement.closest("[role=alertdialog]"))');
    assert.equal(await evaluate('Alpine.$data(document.querySelector("[x-data=\\"actionConfirmationModal()\\"]")).submitter.id'), 'confirm-trigger');
    await key('Escape', 'Escape');
    assert.equal(await evaluate('document.querySelector("#background").inert'), true, 'confirmation retains Escape-disabled rule');
    await click(10, 10);
    await wait('!document.querySelector("#background").inert');
    assert.equal(await evaluate('document.activeElement.id'), 'confirm-trigger');

    // Simulate a browser without stable-gutter support and verify measured compensation.
    await evaluate('document.documentElement.style.scrollbarGutter="auto";window.fallbackWidth=document.querySelector("#background").getBoundingClientRect().width;document.querySelector("#open").focus();document.querySelector("#open").click()');
    await wait('document.activeElement.id === "modal-input"');
    assert.equal(await evaluate('document.querySelector("#background").getBoundingClientRect().width'), await evaluate('fallbackWidth'));
    await key('Escape', 'Escape');
    await wait('!document.querySelector("#background").inert');
    assert.equal(await evaluate('document.documentElement.style.paddingRight'), '', 'fallback padding is removed on close');
    await evaluate('document.documentElement.style.removeProperty("scrollbar-gutter")');
    assert.equal(submissions, 0, 'backdrop cancellation must not submit');
    await sleep(250);
    await evaluate('document.querySelector(".filter-modal-trigger").focus();document.querySelector(".filter-modal-trigger").click()');
    await wait('document.activeElement.tagName === "SELECT"');
    await evaluate('document.querySelector("select[name=status]").value="active"');
    await key('Escape', 'Escape');
    await wait('!document.querySelector("#background").inert');
    assert.equal(await evaluate('document.activeElement.classList.contains("filter-modal-trigger")'), true);
    assert.equal(await evaluate('document.querySelector("select[name=status]").value'), 'active', 'cancel preserves existing unapplied field values');
    await evaluate('document.querySelector(".filter-modal-trigger").click()');
    await wait('document.querySelector(".filter-modal-shell").classList.contains("is-open")');
    await click(10, 10);
    await wait('!document.querySelector("#background").inert');

    await call('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true });
    await call('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    await evaluate('document.querySelector("#open").focus();document.querySelector("#open").click()');
    await wait('document.activeElement.id === "modal-input"');
    assert.equal(await evaluate('profilePanel.getBoundingClientRect().width <= innerWidth'), true);
    assert.equal(await evaluate('getComputedStyle(profilePanel).transitionDuration'), '0s');
    const touchBefore = await evaluate('window.scrollY');
    await call('Input.synthesizeScrollGesture', { x: 10, y: 400, yDistance: -250, gestureSourceType: 'touch' });
    assert.equal(await evaluate('window.scrollY'), touchBefore, 'touch gesture cannot scroll the background');
    await key('Escape', 'Escape');
    await wait('!document.querySelector("#background").inert');
    console.log('PASS: bundled Alpine + rendered Blade: stable width, root scroll lock, wheel/keyboard isolation, internal scroll, inert/focus protection, Tab/Shift+Tab, backdrop vs panel, focus restore, rapid reopen, exact style restoration, confirmation Escape guard/no submission, filter cancel, responsive/reduced motion.');
} finally {
    socket?.close(); browser.kill(); server.closeAllConnections(); server.close();
    await new Promise(resolve => browser.once('exit', resolve));
    await rm(profile, { recursive: true, force: true, maxRetries: 10, retryDelay: 100 });
}
