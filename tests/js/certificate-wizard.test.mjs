import test from 'node:test';
import assert from 'node:assert/strict';
import { certificateWizard } from '../../resources/js/certificate-wizard.js';

const fixture = () => certificateWizard({ certificateType: '', issuedAt: '2026-10-04T12:49', purpose: '', remarks: '', overrideName: '',
    officialSecretary: 'Maria Secretary', residents: [{ value: '1', label: 'Child', printedName: 'Child Name' }],
    households: [{ value: '2', label: 'Household #56', printedName: 'Household Head' }] });

test('four-step progression requires fields, preserves Back state, and displays Manila time independently of browser timezone', () => {
    const wizard = fixture();
    wizard.go(2); assert.equal(wizard.step, 1);
    wizard.certificateType = 'barangay_clearance'; wizard.go(2); assert.equal(wizard.step, 2);
    wizard.go(3); assert.equal(wizard.step, 2);
    wizard.selectRecipient({ type: 'resident', id: '1' }); wizard.go(3); assert.equal(wizard.step, 3);
    wizard.purpose = 'Employment'; wizard.go(4); assert.equal(wizard.step, 4);
    wizard.go(3); assert.equal(wizard.purpose, 'Employment'); assert.equal(wizard.residentId, '1');
    assert.match(wizard.localTimeLabel, /October 4, 2026/); assert.match(wizard.localTimeLabel, /12:49/);
});

test('switching modes clears both IDs and inactive branch events cannot replace the selected recipient', () => {
    const wizard = fixture(); wizard.residentId = '1';
    let reset = ''; wizard.$dispatch = event => { reset = event; };
    wizard.changeRecipientType('household');
    assert.equal(wizard.residentId, ''); assert.equal(wizard.householdId, ''); assert.equal(reset, 'certificate-recipient-reset');
    wizard.selectRecipient({ type: 'resident', id: '1' }); assert.equal(wizard.residentId, '');
    wizard.selectRecipient({ type: 'household', id: '2' }); assert.equal(wizard.printedName, 'Household Head');
});

test('stale overrides are ignored unless explicitly enabled; purpose and enabled name are bounded', () => {
    const wizard = fixture(); wizard.residentId = '1'; wizard.overrideName = 'Stale';
    assert.equal(wizard.printedName, 'Child Name'); wizard.usePrintedName = true; assert.equal(wizard.printedName, 'Stale');
    wizard.purpose = 'P'.repeat(255); assert.equal(wizard.validStep(3), true);
    wizard.purpose += 'P'; assert.equal(wizard.validStep(3), false);
});

test('implicit Enter submits and all earlier-step submissions are blocked; only explicit final Issue proceeds', () => {
    const wizard = fixture(); wizard.certificateType = 'barangay_clearance'; wizard.residentId = '1'; wizard.purpose = 'Employment';
    let blocked = 0; const implicit = { preventDefault: () => blocked++ };
    wizard.submit(implicit); assert.equal(blocked, 1);
    wizard.step = 4; wizard.submit(implicit); assert.equal(blocked, 2);
    wizard.submit({ submitter: { hasAttribute: name => name === 'data-certificate-issue' }, preventDefault: () => blocked++ });
    assert.equal(blocked, 2);
});
