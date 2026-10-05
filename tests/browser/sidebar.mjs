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
// Generate the Blade fixture with PORTAL_PROFILE_BROWSER_HTML on PortalSidebarTest, or the existing wizard fixture.
const rendered = readFileSync(process.env.PORTAL_PROFILE_BROWSER_HTML || process.env.CERTIFICATE_BROWSER_HTML || '/tmp/healthlink-certificate-wizard.html', 'utf8');
const notificationFixtures = process.env.NOTIFICATION_BROWSER_DIR ? {
    portal: readFileSync(process.env.NOTIFICATION_BROWSER_DIR + '/secretary.html', 'utf8'),
    admin: readFileSync(process.env.NOTIFICATION_BROWSER_DIR + '/admin.html', 'utf8'),
} : null;
let assetGate = Promise.resolve(), releaseAssets = () => {};
const holdAssets = () => { assetGate = new Promise(resolve => { releaseAssets = resolve; }); };
const server = createServer(async (req, res) => {
    if (req.url.endsWith(manifest['resources/css/app.css'].file)) { res.setHeader('Content-Type', 'text/css'); return res.end(css); }
    if (req.url.endsWith(manifest['resources/js/app.js'].file)) {
        await assetGate;
        res.setHeader('Content-Type', 'text/javascript'); return res.end(js);
    }
    res.setHeader('Content-Type', 'text/html');
    const fixture = notificationFixtures?.[req.url === '/notification-admin' ? 'admin' : req.url === '/notification-portal' ? 'portal' : ''] ?? rendered;
    res.end(fixture.replaceAll(/https?:\/\/(?:localhost|127\.0\.0\.1)(?::\d+)?/g, base));
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

    if (process.env.PORTAL_PROFILE_BROWSER_HTML) {
        for (const width of [320, 375, 640, 1280]) {
            await call('Emulation.setDeviceMetricsOverride', { width, height: 812, deviceScaleFactor: 1, mobile: width < 640 });
            await navigate(); releaseAssets(); await wait('document.body.hasAttribute("data-sidebar-ready")');
            const mobileLink = 'document.querySelector("[data-mobile-profile-link]")';
            const headerLink = `document.querySelector('[data-sidebar-content] > nav a[href$="/profile"]')`;
            assert.equal(await evaluate(`getComputedStyle(${mobileLink}).display === 'none'`), width >= 640);
            assert.equal(await evaluate(`getComputedStyle(${headerLink}).display === 'none'`), width < 640);
            // Compare against the same shell without the new link, including any existing narrow-header overflow.
            assert.equal(await evaluate(`(() => {
                const link=${mobileLink}, original=document.documentElement.scrollWidth;
                link.hidden=true; link.style.display='none';
                const baseline=document.documentElement.scrollWidth;
                link.hidden=false; link.style.removeProperty('display');
                return original === baseline;
            })()`), true, `Profile must not introduce overflow at ${width}px`);
            if (width >= 640) continue;
            await evaluate(`if (!${state}.sidebarOpen) document.querySelector('[aria-label="Toggle sidebar"]').click();`);
            await wait(`${state}.sidebarOpen && ${mobileLink}.getClientRects().length > 0`);
            assert.equal(await evaluate(`(() => {
                const r=${mobileLink}.getBoundingClientRect();
                return r.left>=0 && r.right<=innerWidth && r.top>=0 && r.bottom<=innerHeight;
            })()`), true);
            await evaluate(`document.querySelector('[aria-label="Close sidebar"]').focus()`);
            await call('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
            await call('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
            assert.equal(await evaluate(`document.activeElement === ${mobileLink}`), true);
            assert.equal(await evaluate(`getComputedStyle(${mobileLink}).outlineStyle !== 'none'`), true);
            // Keep the fixture on this document while exercising native keyboard activation and existing close-on-nav.
            await evaluate(`${mobileLink}.addEventListener('click',e=>e.preventDefault(),{once:true})`);
            await call('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
            await call('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
            await wait(`!${state}.sidebarOpen`);
        }
        console.log('Profile browser checks passed: 320/375px sidebar entry, 640/1280px header-only entry, no added overflow, visible footer, native Tab/focus/Enter, preserved close-on-navigation.');
    }

    if (notificationFixtures) {
        const key = async (key, modifiers = 0) => {
            const code = { Enter: 13, ' ': 32, Tab: 9, Escape: 27 }[key];
            await call('Input.dispatchKeyEvent', { type: 'keyDown', key, code: key === ' ' ? 'Space' : key, text: key === 'Enter' ? '\r' : key === ' ' ? ' ' : '', modifiers, windowsVirtualKeyCode: code });
            await call('Input.dispatchKeyEvent', { type: 'keyUp', key, code: key === ' ' ? 'Space' : key, modifiers, windowsVirtualKeyCode: code });
        };
        const trigger = `document.querySelector('[aria-label="Open notifications"]')`;
        const panel = `document.getElementById(${trigger}.getAttribute('aria-controls'))`;
        const closed = `${trigger}.getAttribute('aria-expanded')==='false' && getComputedStyle(${panel}).display==='none'`;
        const opened = `${trigger}.getAttribute('aria-expanded')==='true' && getComputedStyle(${panel}).display!=='none' && getComputedStyle(${panel}).opacity==='1'`;
        for (const theme of ['portal', 'admin']) {
            for (const [width, height] of [[320, 812], [360, 812], [375, 812], [390, 812], [1280, 812], [390, 320]]) {
                await call('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 640 });
                await call('Page.navigate', { url: base + '/notification-' + theme });
                await wait(`window.Alpine && ${trigger}?.parentElement._x_dataStack`);
                await wait(closed);
                const panelId = await evaluate(`${trigger}.getAttribute('aria-controls')`);
                const baselineWidth = await evaluate('document.documentElement.scrollWidth');
                assert.equal(await evaluate(`${panel}.getAttribute('aria-labelledby')===${panel}.id+'-title'`), true);
                assert.equal(await evaluate(`${trigger}.getBoundingClientRect().right<=innerWidth`), true);
                await evaluate(`${trigger}.focus()`); await key('Enter'); await wait(opened);
                assert.equal(await evaluate(`${trigger}.getAttribute('aria-controls')`), panelId);
                const bounds = await evaluate(`(() => {
                    const r=${panel}.getBoundingClientRect();
                    return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:r.width};
                })()`);
                assert.ok(bounds.left >= 0 && bounds.right <= width, theme + ' ' + width + 'px horizontal bounds');
                assert.ok(bounds.top >= 0 && bounds.bottom <= height, theme + ' ' + height + 'px vertical bounds');
                if (width === 1280) assert.equal(Math.round(bounds.width), 352);
                assert.equal(await evaluate('document.documentElement.scrollWidth'), baselineWidth, 'No added document overflow');
                const list = `${panel}.querySelector('.overflow-y-auto')`;
                assert.equal(await evaluate(`${list}.scrollHeight>${list}.clientHeight`), true);
                await evaluate(`${list}.scrollTop=${list}.scrollHeight`);
                assert.equal(await evaluate(`(() => {
                    const last=${list}.querySelector('form:last-child button').getBoundingClientRect();
                    const footer=${panel}.querySelector('a').getBoundingClientRect();
                    return last.bottom<=${panel}.getBoundingClientRect().bottom && footer.bottom<=innerHeight;
                })()`), true);
                await evaluate(`${trigger}.focus()`); await key('Tab');
                assert.equal(await evaluate(`document.activeElement===${panel}.querySelector('button')`), true);
                await key('Tab', 8);
                assert.equal(await evaluate(`document.activeElement===${trigger}`), true);
                // Normal Tab must leave the disclosure after its seven buttons and View All link.
                for (let i = 0; i < 9; i++) await key('Tab');
                assert.equal(await evaluate(`${trigger}.parentElement.contains(document.activeElement)`), false);
                await key('Escape'); await wait(closed);
                assert.equal(await evaluate(`document.activeElement===${trigger}`), true);
                await key(' '); await wait(opened);
                await key(' '); await wait(closed);
                await evaluate(`${trigger}.click()`); await wait(opened);
                await evaluate(`document.body.insertAdjacentHTML('beforeend','<button id="notification-fixture-outside" style="position:fixed;bottom:0;left:0;z-index:100">Outside</button>')`);
                const outside = await evaluate(`(() => { const r=document.getElementById('notification-fixture-outside').getBoundingClientRect();return {x:r.left+r.width/2,y:r.top+r.height/2}; })()`);
                await call('Input.dispatchMouseEvent', { type: 'mousePressed', ...outside, button: 'left', clickCount: 1 });
                await call('Input.dispatchMouseEvent', { type: 'mouseReleased', ...outside, button: 'left', clickCount: 1 });
                await wait(closed);
                assert.equal(await evaluate('document.activeElement.id'), 'notification-fixture-outside');
                await key('Escape');
                assert.equal(await evaluate('document.activeElement.id'), 'notification-fixture-outside', 'Closed Escape must not steal focus');
            }
            await call('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
            await evaluate(`${trigger}.focus()`); await key('Enter'); await wait(opened);
            assert.equal(await evaluate(`getComputedStyle(${panel}).transitionDuration`), '0s');
            await key('Escape'); await wait(closed);
            await call('Emulation.setEmulatedMedia', { features: [] });
        }
        console.log('Notification browser checks passed: portal/Admin at 320/360/375/390/1280px and 320px height; stable controls, Enter/Space, Escape/focus, Tab exit, outside click, bounds, no added overflow, internal scrolling and reduced motion.');
    }

} finally {
    socket?.close();
    browser.kill('SIGTERM');
    await new Promise(resolve => server.close(resolve));
    await sleep(100);
    rmSync(profile, { recursive: true, force: true });
}
