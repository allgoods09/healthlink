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
    selector.init();
    selector.query = '#1 ilaya';
    selector.updateSearch();
    assert.deepEqual(selector.visibleIds, ['2']);
});

test('pagination and search do not discard selected households', () => {
    const options = Array.from({ length: 30 }, (_, index) => ({ id: String(index), label: `Household #${index} - Purok Centro` }));
    const selector = rbiHouseholdSelector(options, ['0', '29']);
    selector.init();
    assert.equal(selector.visibleIds.length, 12);
    assert.equal(selector.pageCount, 3);
    selector.changePage(2);
    assert.deepEqual(selector.visibleIds, ['24', '25', '26', '27', '28', '29']);
    selector.query = 'missing';
    selector.updateSearch();
    assert.deepEqual(selector.visibleIds, []);
    assert.equal(selector.pageCount, 1);
    assert.deepEqual(selector.selected, ['0', '29']);
});

test('cached matching retains the original token, substring, locale and ordering rules', () => {
    const options = Array.from({ length: 721 }, (_, index) => ({ id: String(index), label: `Household #${index % 103 + 1} - Purok ${Math.floor(index / 103) + 1} Cañete` }));
    const selector = rbiHouseholdSelector(options, ['0', '720']);
    selector.init();
    for (const query of ['5', '50', '#5 purok 2', '  PUROK   3 ', 'cañete', 'canete', 'missing', '']) {
        selector.query = query;
        selector.updateSearch();
        const words = query.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
        const expected = options.filter(option => words.every(word => option.label.toLocaleLowerCase().includes(word)));
        assert.deepEqual(selector.matches, expected);
        assert.equal(selector.page, 1);
        assert.equal(selector.pageCount, Math.max(1, Math.ceil(expected.length / 12)));
        assert.deepEqual(selector.visibleIds, expected.slice(0, 12).map(option => option.id));
        selector.changePage(1);
        selector.changePage(-1);
        assert.deepEqual(selector.selected, ['0', '720']);
    }
});

test('row reads, pagination and checkbox changes reuse state instead of filtering again', () => {
    let normalizations = 0;
    const selector = rbiHouseholdSelector(Array.from({ length: 721 }, (_, index) => ({ id: String(index),
        label: { toLocaleLowerCase: () => { normalizations++; return `household #${index}`; } },
    })));
    assert.equal(normalizations, 721);
    selector.init();
    selector.query = '5';
    let filters = 0;
    const originalFilter = Array.prototype.filter;
    Array.prototype.filter = function (...args) { filters++; return originalFilter.apply(this, args); };
    try {
        selector.updateSearch();
        // One token cleanup and one household pass, regardless of rendered row count.
        assert.equal(filters, 2);
        const cached = selector.matches;
        for (let index = 0; index < 721; index++) {
            selector.visibleIds.includes(String(index));
            assert.equal(selector.matches, cached);
        }
        selector.changePage(1);
        selector.selected.push('720');
        selector.changePage(-1);
        assert.equal(filters, 2);
        assert.equal(normalizations, 721);
    } finally {
        Array.prototype.filter = originalFilter;
    }
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
