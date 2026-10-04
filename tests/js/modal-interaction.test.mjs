import test from 'node:test';
import assert from 'node:assert/strict';
import { registerModalInteraction } from '../../resources/js/modal-interaction.js';

test('Alpine directive uses shared open/close and releases locks on destruction', () => {
    let directive;
    let effect;
    let cleanup;
    let open = false;
    const calls = [];
    const element = {};
    registerModalInteraction({ nextTick: callback => callback(), directive: (name, handler) => {
        assert.equal(name, 'modal-layer'); directive = handler;
    } }, { open: el => calls.push(['open', el]), close: el => calls.push(['close', el]), focus: () => {} });
    directive(element, { expression: 'show' }, {
        evaluateLater: expression => { assert.equal(expression, 'show'); return callback => callback(open); },
        effect: callback => { effect = callback; callback(); },
        cleanup: callback => { cleanup = callback; },
    });
    open = true; effect();
    open = false; effect();
    open = true; effect(); cleanup();
    assert.deepEqual(calls.map(([action]) => action), ['close', 'open', 'close', 'open', 'close']);
    assert.ok(calls.every(([, el]) => el === element));
});
