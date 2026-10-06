// Run with: npm run test:js — same fixtures as tests/Unit/PricingServiceTest.php.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { priceLine, priceQuote, money, advance } from '../../resources/js/pricing.js';

const fx = JSON.parse(readFileSync(new URL('../fixtures/pricing-cases.json', import.meta.url), 'utf8'));
const lineInput = (name) => {
    const c = fx.line_cases.find((x) => x.name === name);
    return { ...structuredClone(c.line), product: fx.products[c.product] };
};

for (const c of fx.line_cases) {
    test(`line: ${c.name}`, () => {
        const r = priceLine(lineInput(c.name), c.rules ?? fx.rules);
        const e = c.expected;
        assert.ok(Math.abs(r.area - e.area) < 1e-6, `area ${r.area} != ${e.area}`);
        assert.equal(r.raw_amount, e.raw_amount);
        assert.equal(r.min_applied, e.min_applied);
        assert.equal(r.amount, e.amount);
        assert.equal(r.area_text, e.area_text);
        assert.equal(r.formula, e.formula);
        assert.equal(r.error, e.error);
    });
}

for (const c of fx.quote_cases) {
    test(`quote: ${c.name}`, () => {
        const lines = c.lines.map((name, i) => {
            const l = lineInput(name);
            if (c.line_qty_override?.[i] !== undefined) l.qty = c.line_qty_override[i];
            return l;
        });
        const q = priceQuote(lines, c.options, c.rules ?? fx.rules);
        const e = c.expected;
        assert.deepEqual(q.lines.map((l) => l.amount), e.line_amounts);
        for (const key of ['subtotal', 'discount_amount', 'visit_charge', 'taxable_before_min', 'min_job_adjustment',
            'taxable', 'cgst', 'sgst', 'igst', 'gst_total', 'total', 'min_job_applied']) {
            assert.equal(q[key], e[key], key);
        }
        if (e.money_total) assert.equal(money(q.total), e.money_total);
    });
}

test('worked sofa example equals ₹1,422.00 and advances match PHP', () => {
    assert.equal(money(priceLine(lineInput('worked sofa example'), fx.rules).amount), '₹1,422.00');
    assert.equal(advance(3072.3, 50), 1536.15);
    assert.equal(advance(3072.3, 30), 921.69);
    assert.equal(money(12345678.9), '₹1,23,45,678.90');
});
