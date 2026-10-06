import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { evaluate, explain, variables, FormulaError } from '../../resources/js/formula.js';

const fx = JSON.parse(readFileSync(new URL('../fixtures/formula-cases.json', import.meta.url), 'utf8'));

for (const c of fx.eval) {
    test(`eval: ${c.f}`, () => {
        assert.ok(Math.abs(evaluate(c.f, c.vars) - c.result) < 1e-6, `${evaluate(c.f, c.vars)} != ${c.result}`);
        assert.equal(explain(c.f, c.vars), c.explain);
    });
}

for (const c of fx.errors) {
    test(`error: ${c.f}`, () => {
        assert.throws(() => evaluate(c.f, c.vars ?? {}), (e) => e instanceof FormulaError && e.message === c.error);
    });
}

test('explain with display overrides and variables()', () => {
    for (const c of fx.explain_display) assert.equal(explain(c.f, c.vars, c.display), c.explain);
    for (const c of fx.variables) assert.deepEqual(variables(c.f), c.vars);
});
