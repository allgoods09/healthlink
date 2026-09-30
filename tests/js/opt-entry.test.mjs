import test from 'node:test';
import assert from 'node:assert/strict';
import { optCaregiverField, optMeasurementForm } from '../../resources/js/opt-entry.js';

test('caregiver supports typed names without automatically linking a suggested resident', () => {
    const field = optCaregiverField({ options: [{ value: 7, label: 'Maria Santos' }], name: 'Maria Santos', residentId: 7 });
    field.init();
    assert.equal(field.residentId, '7');
    field.name = 'Maria';
    field.input();
    assert.equal(field.residentId, '');
    assert.equal(field.highlightedIndex, -1);
    assert.equal(field.matches.length, 1);
    field.move(1);
    field.choose(field.matches[field.highlightedIndex]);
    assert.equal(field.name, 'Maria Santos');
    assert.equal(field.residentId, '7');
    field.name = 'Unregistered Guardian';
    field.input();
    assert.equal(field.matches.length, 0);
    assert.equal(field.residentId, '');
});

test('prefills a saved unregistered name', () => {
    const field = optCaregiverField({ name: 'Elena Santos' });
    field.init();
    assert.equal(field.name, 'Elena Santos');
    assert.equal(field.residentId, '');
});

test('measurement-date age defaults the method but never overrides a chosen or recorded method', () => {
    const form = optMeasurementForm({ dob: '2024-07-02', date: '2026-07-01', posture: 'standing', recorded: false });
    form.updateDefaultMethod();
    assert.equal(form.posture, 'recumbent');
    form.date = '2026-07-02';
    form.updateDefaultMethod();
    assert.equal(form.posture, 'standing');
    form.methodChanged = true;
    form.posture = 'recumbent';
    form.date = '2026-07-03';
    form.updateDefaultMethod();
    assert.equal(form.posture, 'recumbent');
    const recorded = optMeasurementForm({ dob: '2024-07-02', date: '2026-07-03', posture: 'recumbent', recorded: true });
    recorded.updateDefaultMethod();
    assert.equal(recorded.posture, 'recumbent');
});
