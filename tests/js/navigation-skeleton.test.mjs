import test from 'node:test';
import assert from 'node:assert/strict';
import { eligibleNavigationLink, initializeNavigationSkeleton } from '../../resources/js/navigation-skeleton.js';

const location = { href: 'https://healthlink.test/secretary/dashboard', origin: 'https://healthlink.test' };
function anchor(overrides = {}, inForm = false) {
    const values = { href: '/secretary/residents', 'data-navigation-skeleton': 'generic', ...overrides };
    const link = {
        attributes: Object.keys(values).map(name => ({ name })),
        getAttribute: name => values[name] ?? null,
        hasAttribute: name => Object.hasOwn(values, name),
        closest: selector => selector === 'form' ? (inForm ? {} : null) : values['data-navigation-skeleton'] ? link : null,
    };
    return link;
}
function eventFor(attributes = {}, options = {}, inForm = false) {
    const link = anchor(attributes, inForm);
    return { button: 0, target: link, defaultPrevented: false, ...options };
}

test('ordinary opted-in internal navigation is eligible and is not intercepted', () => {
    const event = eventFor();
    event.preventDefault = () => assert.fail('must not intercept navigation');
    assert.equal(eligibleNavigationLink(event, location), event.target);
    assert.equal(event.defaultPrevented, false);
});

for (const [name, attributes, options, inForm] of [
    ['non-opted-in link', { 'data-navigation-skeleton': null }],
    ['external URL', { href: 'https://other.test/residents' }],
    ['email', { href: 'mailto:worker@example.test' }],
    ['telephone', { href: 'tel:123' }],
    ['hash', { href: '/secretary/residents#table' }],
    ['download', { download: '' }],
    ['new tab', { target: '_blank' }],
    ['dropdown', { 'aria-haspopup': 'true' }],
    ['modal', { 'data-modal-target': 'dialog' }],
    ['Alpine control', { '@click': 'open = true' }],
    ['button control', { role: 'button' }],
    ['destructive action', { 'data-method': 'DELETE' }],
    ['logout', { href: '/logout' }],
    ['form action', {}, {}, true],
    ['prevented event', {}, { defaultPrevented: true }],
    ['modified click', {}, { ctrlKey: true }],
    ['middle click', {}, { button: 1 }],
]) {
    test(`${name} does not activate feedback`, () => {
        assert.equal(eligibleNavigationLink(eventFor(attributes, options, inForm), location), null);
    });
}

function harness(cssReady = true) {
    const listeners = new Map();
    const classes = new Set();
    const attributes = new Map();
    const status = { textContent: '' };
    const region = {
        classList: { add: name => classes.add(name), remove: name => classes.delete(name) },
        setAttribute: (name, value) => attributes.set(name, value),
        removeAttribute: name => attributes.delete(name),
        querySelector: () => status,
        querySelectorAll: () => [{ getAttribute: () => 'generic' }],
    };
    const window = {
        location,
        addEventListener: (name, handler) => listeners.set(name, handler),
        getComputedStyle: () => ({ getPropertyValue: () => cssReady ? '1' : '' }),
    };
    initializeNavigationSkeleton(window, { querySelector: () => region });
    return { listeners, classes, attributes, status };
}

test('feedback starts synchronously; repeated clicks stay idempotent; Back/Forward restores content', () => {
    const state = harness();
    const event = eventFor();
    event.preventDefault = () => assert.fail('must not prevent normal navigation');
    state.listeners.get('click')(event);
    state.listeners.get('click')(event);
    assert.deepEqual([...state.classes], ['is-navigation-loading']);
    assert.equal(state.attributes.get('aria-busy'), 'true');
    assert.equal(state.status.textContent, 'Loading page.');
    state.listeners.get('pageshow')({ persisted: true });
    assert.equal(state.classes.size, 0);
    assert.equal(state.attributes.size, 0);
    assert.equal(state.status.textContent, '');
    state.listeners.get('click')(event);
    state.listeners.get('pagehide')();
    assert.equal(state.classes.size, 0);
});

test('failed CSS and unknown variants leave real content visible', () => {
    const failed = harness(false);
    failed.listeners.get('click')(eventFor());
    assert.equal(failed.classes.size, 0);
    const unknown = harness();
    unknown.listeners.get('click')(eventFor({ 'data-navigation-skeleton': 'future-table' }));
    assert.equal(unknown.classes.size, 0);
});
