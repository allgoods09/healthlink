import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';
import test from 'node:test';

const source = readFileSync(new URL('../../resources/js/rbi-selection.js', import.meta.url), 'utf8');
const { rbiHouseholdSelector } = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

test('household selector searches number and purok without confusing repeated numbers', () => {
    const selector = rbiHouseholdSelector([
        { id: '1', label: 'Household #1 - Purok Centro' },
        { id: '2', label: 'Household #1 - Purok Ilaya' },
    ]);
    selector.query = '#1 ilaya';
    assert.deepEqual(selector.visibleIds, ['2']);
});

test('pagination and search do not discard selected households', () => {
    const options = Array.from({ length: 30 }, (_, index) => ({ id: String(index), label: `Household #${index} - Purok Centro` }));
    const selector = rbiHouseholdSelector(options, ['0', '29']);
    assert.equal(selector.visibleIds.length, 12);
    assert.equal(selector.pageCount, 3);
    selector.page = 3;
    assert.deepEqual(selector.visibleIds, ['24', '25', '26', '27', '28', '29']);
    selector.query = 'missing';
    assert.deepEqual(selector.visibleIds, []);
    assert.equal(selector.pageCount, 1);
    assert.deepEqual(selector.selected, ['0', '29']);
});

test('review is server-only, read-only, and never submits mutable selection controls', () => {
    const view = readFileSync(new URL('../../resources/views/secretary/documents/index.blade.php', import.meta.url), 'utf8');
    const exportForm = view.slice(view.indexOf('<form method="GET" action="{{ route(\'secretary.documents.export\')'));
    assert.match(exportForm, /name="review"/);
    assert.doesNotMatch(exportForm, /name="(?:document_type|coverage|purok_ids|household_ids|sex|record_status)/);
    assert.match(view, /@if\(\$step < 4\)/);
    assert.match(view, /type="submit" name="step"/);
    assert.match(view, /@selected\(\$state\['document_type'\]/);
    assert.doesNotMatch(view, /fetch\(|formaction=/);
});
