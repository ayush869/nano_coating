import test from 'node:test';
import assert from 'node:assert/strict';
import { buildAreaFormula, buildPriceFormula, readable } from '../../resources/js/formula-assist.js';
import { evaluate } from '../../resources/js/formula.js';

const counter = (key) => ({ key, label: key, type: 'counter' });
const dim = (key) => ({ key, label: key, type: 'dimension' });

test('parts with a size constant are added up', () => {
    const f = buildAreaFormula(
        [counter('seats'), counter('backs'), counter('arms'), counter('cushions')],
        [{ key: 'seat_sqft' }, { key: 'back_sqft' }, { key: 'arm_sqft' }, { key: 'cushion_sqft' }],
    );
    assert.equal(f, 'seats * seat_sqft + backs * back_sqft + arms * arm_sqft + cushions * cushion_sqft');
});

test('lengths, choices and leftover constants multiply', () => {
    assert.equal(buildAreaFormula([dim('length'), dim('width'), { key: 'sides', type: 'select' }], []), 'length * width * sides');
    assert.equal(buildAreaFormula([counter('panels'), dim('panel_width'), dim('drop')], [{ key: 'faces' }]), 'panels * panel_width * drop * faces');
    assert.equal(buildAreaFormula([counter('seats')], [{ key: 'recliner_sqft' }]), 'seats * recliner_sqft');
    assert.equal(buildAreaFormula([], [{ key: 'piece_sqft' }]), 'piece_sqft');
    assert.equal(buildAreaFormula([], []), '0');
});

test('openings are subtracted and never go below zero', () => {
    const f = buildAreaFormula([dim('height'), dim('width'), { key: 'openings', type: 'number' }], []);
    assert.equal(f, 'max(0, height * width - openings)');
    assert.equal(evaluate(f, { height: 2, width: 2, openings: 10 }), 0);
});

test('parts and factors combine', () => {
    const f = buildAreaFormula([counter('seats'), counter('arms'), { key: 'sides', type: 'select' }], [{ key: 'seat_size' }, { key: 'arm_size' }]);
    assert.equal(f, '(seats * seat_size + arms * arm_size) * sides');
    assert.equal(evaluate(f, { seats: 2, seat_size: 3, arms: 1, arm_size: 4, sides: 2 }), 20);
});

test('price builder', () => {
    assert.equal(buildPriceFormula(), 'area * rate * material * design');
    assert.equal(buildPriceFormula({ usesCoating: false }), 'area * rate');
    const f = buildPriceFormula({ minArea: 10, discountAbove: 500, discountPct: 10, extra: 100 });
    assert.equal(f, 'max(area, 10) * rate * material * design * if(area > 500, 0.9, 1) + 100');
    assert.equal(evaluate(f, { area: 5, rate: 2, material: 1, design: 1 }), 120);
    assert.equal(buildPriceFormula({ discountAbove: 500, discountPct: '' }), 'area * rate * material * design');
});

test('readable uses labels', () => {
    assert.equal(readable('seats * seat_sqft', [{ key: 'seats', label: 'Seats' }], [{ key: 'seat_sqft', label: 'Seat size' }]), 'Seats × Seat size');
    assert.equal(readable('area * (', [], []), '');
});
