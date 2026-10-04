const naturalOrder = new Intl.Collator('en', { numeric: true, sensitivity: 'variant' });

export const normalizeSearchText = value => String(value ?? '').trim().toLowerCase().replace(/\s+/g, ' ');

export function prepareRecordOptions(options = []) {
    return options.map((option, index) => {
        const metadata = option.ranking;
        const names = metadata ? [metadata.primaryName ?? option.label, ...(metadata.nameAliases ?? [])]
            .map(normalizeSearchText).filter(Boolean) : [];

        return {
            option, index,
            label: metadata ? normalizeSearchText(option.label) : String(option.label ?? '').toLowerCase(),
            search: metadata ? normalizeSearchText(option.search ?? option.label) : String(option.search ?? option.label ?? '').toLowerCase(),
            ranking: metadata ? {
                kind: metadata.kind,
                identifier: normalizeSearchText(metadata.primaryIdentifier),
                names,
                words: names.flatMap(name => name.split(/[\s,]+/).filter(Boolean)),
                secondary: (metadata.secondaryFields ?? []).map(normalizeSearchText).filter(Boolean),
                name: normalizeSearchText(metadata.primaryName ?? option.label),
                purok: normalizeSearchText(metadata.purok),
                id: String(option.value ?? option.id ?? ''),
            } : null,
        };
    });
}

function relevance(record, query) {
    const metadata = record.ranking;
    if (!metadata) return record.label.includes(query) || record.search.includes(query) ? 8 : null;
    if (metadata.identifier && metadata.identifier === query) return 1;
    if (metadata.names.some(name => name === query)) return 2;
    if (metadata.identifier && metadata.identifier.startsWith(query)) return 3;
    if (metadata.names.some(name => name.startsWith(query))) return 4;
    if (metadata.words.includes(query)) return 5;
    if (metadata.words.some(word => word.startsWith(query))) return 6;
    if (metadata.secondary.some(field => field === query || field.startsWith(query))) return 7;
    return [record.label, record.search, metadata.identifier, ...metadata.names, ...metadata.secondary]
        .some(field => field.includes(query)) ? 8 : null;
}

function compareText(a, b) {
    // Preserve distinct string identifiers even when natural comparison considers 05 and 5 equal.
    return naturalOrder.compare(a, b) || (a < b ? -1 : a > b ? 1 : 0);
}

function compareTies(a, b) {
    if (!a.ranking || !b.ranking) {
        if (!a.ranking && !b.ranking) return a.index - b.index;
        return a.ranking ? -1 : 1;
    }
    const left = a.ranking;
    const right = b.ranking;
    const fields = left.kind === 'household' && right.kind === 'household'
        ? ['identifier', 'name', 'purok', 'id'] : ['name', 'identifier', 'id'];
    for (const field of fields) {
        const comparison = compareText(left[field], right[field]);
        if (comparison) return comparison;
    }
    return a.index - b.index;
}

export function rankRecordOptions(prepared, query) {
    const term = normalizeSearchText(query);
    if (!term) return [];
    const legacyTerm = String(query ?? '').trim().toLowerCase();
    return prepared.map(record => ({ record, tier: relevance(record, record.ranking ? term : legacyTerm) }))
        .filter(match => match.tier !== null)
        .sort((a, b) => a.tier - b.tier || compareTies(a.record, b.record))
        .map(match => match.record.option);
}

export function rankHouseholdOptions(households, query) {
    const options = households.map(household => ({
        ...household,
        label: `#${household.household_no} - ${household.household_address}`,
        search: [household.household_no, household.household_address].join(' '),
        ranking: {
            kind: 'household', primaryIdentifier: household.household_no,
            primaryName: `Household #${household.household_no}`,
            secondaryFields: [household.household_address],
        },
    }));
    return rankRecordOptions(prepareRecordOptions(options), query);
}
