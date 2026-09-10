// SPDX-License-Identifier: GPL-3.0-or-later

'use strict';

const assert = require('node:assert/strict');
const test = require('node:test');
const {filterAndSortRules, paginate} = require('../js/inspection.js');

function rule(id, values = {}) {
    return {
        dataset: {
            adherenceDenominator: '1',
            adherenceNumerator: '0',
            condition: 'onadd',
            entityId: '0',
            evaluation: 'match',
            evaluationOrder: '0',
            id: String(id),
            indeterminate: '0',
            name: `rule ${id}`,
            ranking: String(id),
            search: `rule ${id}`,
            ...values,
        },
    };
}

function state(overrides = {}) {
    return {
        conditions: new Set(['onadd', 'onupdate']),
        entities: new Set(['0', '1', '2']),
        minimumAdherence: 0,
        query: '',
        results: new Set(['match', 'no_match', 'indeterminate']),
        ...overrides,
    };
}

test('combines result OR with adherence, condition, entity, and search filters using AND', () => {
    const rules = [
        rule(1, {adherenceNumerator: '4', adherenceDenominator: '5', entityId: '2'}),
        rule(2, {adherenceNumerator: '5', adherenceDenominator: '5', entityId: '1'}),
        rule(3, {adherenceNumerator: '5', adherenceDenominator: '5', entityId: '2', evaluation: 'no_match', evaluationOrder: '1'}),
        rule(4, {adherenceNumerator: '5', adherenceDenominator: '5', entityId: '2', condition: 'onupdate'}),
    ];

    const visible = filterAndSortRules(rules, state({
        conditions: new Set(['onadd']),
        entities: new Set(['2']),
        minimumAdherence: 80,
        results: new Set(['match', 'indeterminate']),
    }), []);

    assert.deepEqual(visible.map((item) => item.dataset.id), ['1']);
});

test('uses the exact adherence ratio for filtering and multi-level sorting', () => {
    const rules = [
        rule(1, {adherenceNumerator: '79', adherenceDenominator: '99', ranking: '1'}),
        rule(2, {adherenceNumerator: '4', adherenceDenominator: '5', indeterminate: '2', ranking: '3'}),
        rule(3, {adherenceNumerator: '4', adherenceDenominator: '5', indeterminate: '1', ranking: '4'}),
        rule(4, {adherenceNumerator: '5', adherenceDenominator: '5', ranking: '9'}),
    ];

    const filtered = filterAndSortRules(rules, state({minimumAdherence: 80}), []);
    assert.deepEqual(filtered.map((item) => item.dataset.id), ['2', '3', '4']);

    const sorted = filterAndSortRules(rules, state(), [
        {field: 'adherence', direction: 'desc'},
        {field: 'indeterminate', direction: 'asc'},
        {field: 'ranking', direction: 'asc'},
    ]);
    assert.deepEqual(sorted.map((item) => item.dataset.id), ['4', '3', '2', '1']);
});

test('uses deterministic ranking and ID fallbacks and paginates only after filtering and sorting', () => {
    const rules = [
        rule(8, {evaluation: 'no_match', evaluationOrder: '1', ranking: '2'}),
        rule(3, {evaluation: 'match', ranking: '1'}),
        rule(2, {evaluation: 'match', ranking: '1'}),
        rule(1, {evaluation: 'match', ranking: '3'}),
    ];

    const filtered = filterAndSortRules(rules, state({results: new Set(['match'])}), []);
    assert.deepEqual(filtered.map((item) => item.dataset.id), ['2', '3', '1']);

    const page = paginate(filtered, 2, 2);
    assert.equal(page.pageCount, 2);
    assert.deepEqual(page.rules.map((item) => item.dataset.id), ['1']);
});
