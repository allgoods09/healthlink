import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { normalizeSearchText, prepareRecordOptions, rankRecordOptions, rankHouseholdOptions } from '../../resources/js/record-ranking.js';

const household = (number, id = number, purok = 'Purok 1') => ({
    value: String(id), label: `Household #${number}`, description: purok, search: `${number} ${purok}`,
    ranking: { kind: 'household', primaryIdentifier: number, primaryName: `Household #${number}`, purok, secondaryFields: [purok] },
});
const resident = (name, code, id = code) => ({
    value: id, label: name, search: `${name} ${code}`,
    ranking: { kind: 'resident', primaryIdentifier: code, primaryName: name },
});
const rank = (options, query) => rankRecordOptions(prepareRecordOptions(options), query);

test('household exact identifier, prefix and contains matches are ordered before limiting', () => {
    const options = [household('15'), household('50'), household('5')];
    assert.deepEqual(rank(options, '5').map(option => option.value), ['5', '50', '15']);
    const crowded = [...Array.from({ length: 20 }, (_, i) => household(`${i + 10}5`)), household('5')];
    assert.equal(rank(crowded, '5').slice(0, 12)[0].value, '5');
    assert.equal(rank(crowded, '5').length, 21);
});

test('repeated household numbers retain Purok context and deterministic ID ties', () => {
    const options = [household('5', '20', 'Purok 2'), household('5', '10', 'Purok 1'), household('5', '2', 'Purok 1')];
    const expected = ['2', '10', '20'];
    assert.deepEqual(rank(options, '5').map(option => option.value), expected);
    assert.deepEqual(rank([...options].reverse(), '5').map(option => option.value), expected);
    assert.deepEqual(rank(options, '5').map(option => option.description), ['Purok 1', 'Purok 1', 'Purok 2']);
});

test('identifiers remain strings: 05 is not an exact match for 5', () => {
    assert.deepEqual(rank([household('05'), household('50'), household('5')], '5').map(option => option.value), ['5', '50', '05']);
    assert.equal(rank([household('05'), household('5')], '05')[0].value, '05');
});

test('exact resident code outranks names and code substrings', () => {
    assert.equal(rank([resident('Other', 'XABC'), resident('ABC', 'OTHER'), resident('Santos, Juan', 'ABC')], 'abc')[0].value, 'ABC');
});

test('exact names, name prefixes, exact words, word prefixes and substrings have distinct tiers', () => {
    const options = [resident('San Juanico Reyes', '5'), resident('Xjuanito', '6'), resident('Pedro Juan Santos', '4'),
        resident('Juan Dela Cruz', '3'), resident('Juan', '2')];
    assert.deepEqual(rank(options, 'juan').map(option => option.value), ['2', '3', '4', '5', '6']);
});

test('formal-name aliases and secondary fields are explicit and normalized', () => {
    const person = resident('Santos, Juan   Miguel Jr.', 'PS-5');
    person.ranking.nameAliases = ['Juan Miguel Santos Jr.'];
    person.ranking.secondaryFields = ['Purok 2'];
    assert.equal(rank([person], ' JUAN    MIGUEL ')[0], person);
    assert.equal(rank([person], 'purok 2')[0], person);
    assert.equal(normalizeSearchText('  JUAN   Miguel '), 'juan miguel');
    assert.deepEqual(rank([resident('Cañete, Ana', '1')], 'canete'), []);
});

test('resident ties use formal name, code and ID rather than input order', () => {
    const options = [resident('Santos, Juan', 'CODE-2', '9'), resident('Santos, Juan', 'CODE-1', '8')];
    assert.deepEqual(rank(options, 'juan').map(option => option.value), ['8', '9']);
    assert.deepEqual(rank([...options].reverse(), 'juan').map(option => option.value), ['8', '9']);
});

test('unranked consumers retain their incoming matching order; empty and no-match stay empty', () => {
    const options = ['15', '25', '5'].map(value => ({ value, label: value }));
    assert.deepEqual(rank(options, '5').map(option => option.value), ['15', '25', '5']);
    assert.deepEqual(rank(options, ' '), []);
    assert.deepEqual(rank(options, 'missing'), []);
    assert.deepEqual(rank([{ value: '1', label: 'Juan  Miguel' }], 'juan miguel'), []);
});

function sharedSelector(options, selected = '') {
    const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    const context = { window: {}, prepareRecordOptions, rankRecordOptions, Event };
    vm.runInNewContext(source.slice(source.indexOf('window.searchableRecordSelect ='), source.indexOf("Alpine.data('sidebarLayout'")), context);
    const selector = context.window.searchableRecordSelect({ options, selected });
    selector.$refs = {};
    selector.$el = { closest: () => null };
    selector.$nextTick = callback => callback();
    selector.setOptions(options);
    return selector;
}

test('shared selector ranks before its cap and Enter/mouse selection returns the ranked ID', () => {
    const selector = sharedSelector([...Array.from({ length: 14 }, (_, i) => household(`${i + 10}5`)), household('5'), household('50')]);
    selector.query = '5'; selector.handleInput();
    assert.equal(selector.filteredOptions.length, 12);
    assert.equal(selector.filteredOptions[0].value, '5');
    selector.selectHighlighted();
    assert.equal(selector.selectedValue, '5');
    selector.query = '5'; selector.handleInput(); selector.move(1); selector.selectHighlighted();
    assert.equal(selector.selectedValue, '50');
    selector.query = '5'; selector.handleInput(); selector.selectOption(selector.filteredOptions[0]);
    assert.equal(selector.selectedValue, '5');
    assert.equal(selector.isOpen, false);
});

test('shared selector resets highlight, retains valid refresh selections and clears missing IDs', () => {
    const options = [household('5'), household('50')];
    const selector = sharedSelector(options, '5');
    assert.equal(selector.selectedValue, '5');
    selector.query = '5'; selector.handleInput(); selector.move(1);
    selector.query = '50'; selector.handleInput();
    assert.equal(selector.highlightedIndex, 0);
    assert.equal(selector.filteredOptions[0].value, '50');
    selector.selectHighlighted(); selector.setOptions([...options].reverse());
    assert.equal(selector.selectedValue, '50');
    selector.setOptions([household('5')]);
    assert.equal(selector.selectedValue, '');
    selector.query = ''; selector.handleInput();
    assert.equal(selector.filteredOptions.length, 0);
    assert.equal(selector.isOpen, false);
});

test('shared selector still notifies live filters on selection and clearing', () => {
    const selector = sharedSelector([household('5')]);
    const events = [];
    selector.$el = { closest: () => ({ dispatchEvent: event => events.push(event.type) }) };
    selector.query = '5'; selector.handleInput(); selector.selectHighlighted();
    selector.query = ''; selector.handleInput();
    assert.deepEqual(events, ['live-results:filter-change', 'live-results:filter-change']);
});

for (const page of ['create', 'edit', 'relocate']) {
    test(`${page} bespoke Household selector ranks before cap and selects the right ID`, () => {
        const path = page === 'relocate' ? 'secretary/residents/relocate' : `admin/geometry/residents/${page}`;
        const source = readFileSync(new URL(`../../resources/views/${path}.blade.php`, import.meta.url), 'utf8');
        const script = source.match(/<script>([\s\S]*?)<\/script>/)[1].replace("@js($routePrefix === 'secretary')", 'true');
        const context = { window: { rankHouseholdOptions }, console };
        vm.runInNewContext(script, context);
        const selector = page === 'relocate' ? context.residentRelocation('', 'existing_household', '1', '', []) : context.residentForm('', '', '1', '1', '', [], []);
        selector.$refs = {};
        selector.households = [...Array.from({ length: 30 }, (_, i) => ({ id: i + 1, household_no: String(i + 10), household_address: 'Purok 5' })),
            { id: 99, household_no: '5', household_address: 'Purok 5' }, { id: 100, household_no: '50', household_address: 'Purok 5' }];
        if (page === 'relocate') {
            selector.targetHouseholdSearchQuery = '5'; selector.refreshTargetHouseholdResults();
            selector.selectHighlightedTargetHousehold(); assert.equal(selector.targetHouseholdId, '99');
        } else {
            selector.householdSearchQuery = '5'; selector.refreshHouseholdResults();
            selector.selectHighlightedHousehold(); assert.equal(selector.householdId, '99');
        }
        assert.equal(selector.filteredHouseholds.length, 12);
        assert.equal(selector.filteredHouseholds[0].id, 99);
        assert.equal(selector.highlightedHouseholdIndex, 0);
    });
}

test('Household adapter only matches existing number/address fields and keeps IDs', () => {
    const options = [{ id: 1, household_no: '15', household_address: 'Road' }, { id: 2, household_no: '5', household_address: 'Road' }];
    assert.deepEqual(rankHouseholdOptions(options, '5').map(option => option.id), [2, 1]);
    assert.deepEqual(rankHouseholdOptions(options, 'purok'), []);
});
