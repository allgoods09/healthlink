import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { sidebarLayout } from '../../resources/js/sidebar-layout.js';

const openKey = 'healthlink.sidebar.desktop.open';
const scrollKey = 'healthlink.sidebar.scroll.portal-secretary';
const bootstrap = readFileSync(new URL('../../resources/views/components/sidebar-bootstrap.blade.php', import.meta.url), 'utf8').replace(/<\/?script>/g, '');
function fixture({ width = 1280, open, saved = 300, context = 'portal-secretary', unavailable = false } = {}) {
    const local = new Map(open === undefined ? [] : [[openKey, open]]);
    const session = new Map([[`healthlink.sidebar.scroll.${context}`, String(saved)]]);
    let writes = 0;
    const storage = (map, count = false) => ({
        getItem(key) { if (unavailable) throw Error('blocked'); return map.get(key) ?? null; },
        setItem(key, value) { if (unavailable) throw Error('blocked'); map.set(key, value); if (count) writes++; },
    });
    const events = new Map(), frames = new Map(), ticks = [];
    const win = { innerWidth: width, localStorage: storage(local), sessionStorage: storage(session, true),
        addEventListener: (name, cb) => events.set(name, cb), removeEventListener: name => events.delete(name),
        requestAnimationFrame: cb => { const id = frames.size + 1; frames.set(id, cb); return id; }, cancelAnimationFrame: id => frames.delete(id),
    };
    const document = { documentElement: { dataset: {} } };
    vm.runInNewContext(bootstrap, { window: win, document });
    const handlers = new Map();
    const nav = { scrollTop: 0, scrollHeight: 1000, clientHeight: 200, visible: true, active: null,
        getClientRects() { return this.visible ? [{}] : []; },
        getBoundingClientRect: () => ({ top: 100, bottom: 300 }),
        querySelector() { return this.active; },
        addEventListener: (name, cb) => handlers.set(name, cb), removeEventListener: name => handlers.delete(name),
    };
    const state = sidebarLayout(win, context);
    state.$refs = { sidebarScroll: nav };
    state.$el = { setAttribute() {} };
    state.$nextTick = cb => ticks.push(cb);
    let watch, current = state.sidebarOpen;
    Object.defineProperty(state, 'sidebarOpen', { get: () => current, set: value => { const changed = value !== current; current = value; if (changed) watch?.(value); } });
    state.$watch = (key, cb) => { watch = cb; };
    const flush = () => { while (ticks.length) ticks.shift()(); for (const [id, cb] of [...frames]) { frames.delete(id); cb(); } };
    return { state, win, nav, session, local, events, handlers, flush, document, writes: () => writes };
}

for (const open of [undefined, '1', '0']) {
    test(`bootstrap matches Alpine for stored preference ${open ?? 'absent'}`, () => {
        const f = fixture({ open }); f.state.init(); f.flush();
        assert.equal(f.document.documentElement.dataset.sidebarDesktopOpen, open === '0' ? '0' : '1');
        assert.equal(f.state.sidebarOpen, open !== '0');
    });
}
test('unavailable storage defaults desktop open without throwing', () => {
    const f = fixture({ unavailable: true }); f.state.init(); f.flush(); f.state.toggleSidebar(); f.flush();
    assert.equal(f.document.documentElement.dataset.sidebarDesktopOpen, '1');
    assert.equal(f.state.sidebarOpen, false);
});
test('desktop restores and batches scroll writes, with navigation and pagehide final saves', () => {
    const f = fixture(); f.state.init(); f.flush(); assert.equal(f.nav.scrollTop, 300);
    f.nav.scrollTop = 420; f.handlers.get('scroll')(); f.handlers.get('scroll')(); assert.equal(f.writes(), 0);
    f.flush(); assert.equal(f.writes(), 1); assert.equal(f.session.get(scrollKey), '420');
    f.nav.scrollTop = 500; f.state.handleNavClick({ target: { closest: () => ({}) } }); assert.equal(f.session.get(scrollKey), '500');
    f.nav.scrollTop = 550; f.events.get('pagehide')(); assert.equal(f.session.get(scrollKey), '550');
});
test('collapse, navigation while hidden, and reopen retain expanded position', () => {
    const f = fixture(); f.state.init(); f.flush(); f.nav.scrollTop = 650;
    f.state.toggleSidebar(); f.flush(); f.nav.visible = false; f.nav.scrollTop = 0;
    f.events.get('pagehide')(); assert.equal(f.session.get(scrollKey), '650'); assert.equal(f.local.get(openKey), '0');
    const next = fixture({ saved: 650, open: '0' }); next.nav.visible = false; next.state.init(); next.flush(); next.events.get('pagehide')();
    assert.equal(next.session.get(scrollKey), '650'); next.nav.visible = true; next.state.toggleSidebar(); next.flush();
    assert.equal(next.nav.scrollTop, 650); assert.equal(next.local.get(openKey), '1');
});
test('mobile starts closed and cannot overwrite desktop state even with an open drawer', () => {
    const f = fixture({ width: 375 }); f.state.init(); f.flush(); assert.equal(f.state.sidebarOpen, false);
    f.state.toggleSidebar(); f.nav.scrollTop = 10; f.handlers.get('scroll')(); f.flush(); f.events.get('pagehide')();
    assert.equal(f.session.get(scrollKey), '300'); assert.equal(f.local.has(openKey), false);
    f.state.handleNavClick({ target: { closest: () => ({}) } }); assert.equal(f.state.sidebarOpen, false);
});
test('desktop-to-mobile transition preserves position and desktop return restores it', () => {
    const f = fixture(); f.state.init(); f.flush(); f.nav.scrollTop = 500;
    f.handlers.get('scroll')();
    f.win.innerWidth = 375; f.nav.scrollTop = 0; f.events.get('resize')(); f.flush();
    assert.equal(f.state.sidebarOpen, false); assert.equal(f.session.get(scrollKey), '500');
    f.win.innerWidth = 1280; f.events.get('resize')(); f.flush(); assert.equal(f.nav.scrollTop, 500);
});
test('saved positions are clamped and invalid values ignored', () => {
    for (const [saved, expected] of [[5000, 800], [-100, 0], ['bad', 0]]) {
        const f = fixture({ saved }); f.state.init(); f.flush(); assert.equal(f.nav.scrollTop, expected);
    }
});
test('active item preserves visible position and minimally reveals offscreen items without window scrolling', () => {
    for (const [top, bottom, expected] of [[150, 190, 300], [80, 120, 280], [290, 330, 330]]) {
        const f = fixture(); f.win.scrollTo = () => assert.fail('window scrolling is forbidden');
        f.nav.active = { getBoundingClientRect: () => ({ top, bottom, height: bottom - top }) };
        f.state.init(); f.flush(); assert.equal(f.nav.scrollTop, expected);
    }
});
test('Admin and other portal contexts never consume Secretary or portal-default scroll', () => {
    for (const context of ['admin', 'portal-bhw']) {
        const f = fixture({ context, saved: 90 }); f.session.set(scrollKey, '700'); f.session.set('healthlink.sidebar.scroll.portal-default', '800');
        f.state.init(); f.flush(); assert.equal(f.nav.scrollTop, 90); assert.equal(f.state.scrollStorageKey(), `healthlink.sidebar.scroll.${context}`);
    }
});
test('rapid close cancels queued scroll writes and destruction removes listeners', () => {
    const f = fixture(); f.state.init(); f.flush(); f.nav.scrollTop = 650; f.handlers.get('scroll')();
    f.state.toggleSidebar(); f.nav.visible = false; f.nav.scrollTop = 0; f.flush(); assert.equal(f.session.get(scrollKey), '650');
    f.state.destroy(); f.flush(); assert.equal(f.events.size, 0); assert.equal(f.handlers.size, 0);
});
