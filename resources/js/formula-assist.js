import { explain, parse, FormulaError } from './formula.js';

/**
 * Formula automation for the product designer: builds the area and price
 * formulas from the inputs, so most products need no formula typing at all.
 */

/** Inputs whose value is taken away from the area (doors, windows, gaps). */
const SUBTRACT = /(^|_)(opening|openings|minus|less|deduct|deduction|exclude|excluded|gap|gaps)(_|$)/;

/** Possible singular stems of a counter key: "seats" → seats, seat; "benches" → benche, bench. */
function stems(key) {
    const out = [key];
    if (key.endsWith('es')) out.push(key.slice(0, -2));
    if (key.endsWith('s')) out.push(key.slice(0, -1));
    return out;
}

/** The constant that gives one counted part its size, e.g. seats → seat_sqft. */
function partConstant(counterKey, constants, used) {
    for (const stem of stems(counterKey)) {
        const c = constants.find((c) => !used.has(c.key) && (c.key === stem || c.key.startsWith(stem + '_')));
        if (c) return c;
    }
    return null;
}

/**
 * Area formula from the inputs:
 * - a counter with a matching size constant becomes a part: seats * seat_sqft (parts are added up);
 * - lengths, numbers, choice lists, other counters and leftover constants multiply;
 * - inputs named like "openings" or "deduct_…" are subtracted, never below zero.
 */
export function buildAreaFormula(fields = [], constants = []) {
    const used = new Set();
    const parts = [];
    const factors = [];
    const subtract = [];

    for (const f of fields) {
        if (!f?.key) continue;
        if (f.type === 'counter') {
            const c = partConstant(f.key, constants, used);
            if (c) {
                used.add(c.key);
                parts.push(`${f.key} * ${c.key}`);
                continue;
            }
        }
        if (SUBTRACT.test(f.key)) subtract.push(f.key);
        else factors.push(f.key);
    }
    for (const c of constants) {
        if (c?.key && !used.has(c.key)) factors.push(c.key);
    }

    let expr = parts.join(' + ');
    if (factors.length) {
        const product = factors.join(' * ');
        expr = expr ? (parts.length > 1 ? `(${expr})` : expr) + ' * ' + product : product;
    }
    if (!expr) return subtract.length ? `max(0, 0 - ${subtract.join(' - ')})` : '0';
    return subtract.length ? `max(0, ${expr} - ${subtract.join(' - ')})` : expr;
}

/**
 * Price formula from simple choices:
 * { usesCoating, minArea, discountAbove, discountPct, extra }.
 */
export function buildPriceFormula({ usesCoating = true, minArea = 0, discountAbove = 0, discountPct = 0, extra = 0 } = {}) {
    const n = (x) => Math.max(0, Number(x) || 0);
    const area = n(minArea) > 0 ? `max(area, ${n(minArea)})` : 'area';
    let f = usesCoating ? `${area} * rate * material * design` : `${area} * rate`;
    const pct = Math.min(100, n(discountPct));
    if (n(discountAbove) > 0 && pct > 0) {
        f += ` * if(area > ${n(discountAbove)}, ${+(1 - pct / 100).toFixed(4)}, 1)`;
    }
    if (n(extra) > 0) f += ` + ${n(extra)}`;
    return f;
}

const BUILT_IN = {
    area: 'area', rate: 'rate ₹', material: 'material factor', design: 'design factor',
    coating: 'coating ₹', addon: 'add-on ₹', base_rate: 'fixed rate ₹',
};

/** The formula in words, using the labels of inputs and constants. */
export function readable(src, fields = [], constants = []) {
    if (!src || !String(src).trim()) return '';
    const display = { ...BUILT_IN };
    for (const f of fields) if (f?.key) display[f.key] = f.label || f.key;
    for (const c of constants) if (c?.key) display[c.key] = c.label || c.key;
    try {
        parse(src);
        return explain(src, {}, display);
    } catch (e) {
        if (e instanceof FormulaError) return '';
        throw e;
    }
}

/** Formula name for a label: "Back sections" → back_sections. */
export const slugKey = (s) => String(s).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^(\d)/, 'n$1').slice(0, 30);

/** Same formula ignoring spacing. */
export const sameFormula = (a, b) => String(a ?? '').replace(/\s+/g, '') === String(b ?? '').replace(/\s+/g, '');
