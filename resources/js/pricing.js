/**
 * Pure pricing engine for live estimates — line-for-line port of
 * app/Services/Pricing/PricingService.php. The server re-prices on save and
 * never trusts these numbers; both are tested against
 * tests/fixtures/pricing-cases.json.
 */
import { evaluate, explain, fmt, roundTo, FormulaError } from './formula.js';

export const UNIT_TO_FT = { ft: 1, in: 1 / 12, m: 1 / 0.3048, cm: 1 / 30.48 };
export const FIELD_TYPES = ['number', 'counter', 'dimension', 'select'];
export const RESERVED = ['area', 'rate', 'coating', 'addon', 'material', 'design', 'base_rate', 'qty'];
export const DEFAULT_PRICE_FORMULA = 'area * rate * material * design';

const isNumeric = (v) => v !== null && v !== undefined && v !== '' && typeof v !== 'boolean' && !Number.isNaN(Number(v)) && String(v).trim() !== '';
const toFloat = (v, d) => (isNumeric(v) ? Number(v) : d);

export const round2 = (x) => roundTo(x, 2);

export function num(x) {
    let s = round2(x).toFixed(2);
    s = s.includes('.') ? s.replace(/0+$/, '').replace(/\.$/, '') : s;
    return s === '-0' ? '0' : s;
}

export function money(x, fixed = true) {
    x = round2(x);
    const neg = x < 0;
    let [int, dec] = Math.abs(x).toFixed(2).split('.');
    if (int.length > 3) {
        int = int.slice(0, -3).replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + int.slice(-3);
    }
    let out = '₹' + int;
    if (fixed || dec !== '00') out += '.' + dec;
    return (neg ? '-' : '') + out;
}

export function fieldVars(product, inputs) {
    const vars = {};
    const display = {};
    for (const field of product.fields || []) {
        const key = field.key;
        const raw = inputs?.[key];
        const def = field.default ?? null;
        switch (field.type || 'number') {
            case 'dimension': {
                let unit = inputs?.[key + '__unit'] ?? field.unit ?? 'ft';
                if (!(unit in UNIT_TO_FT)) unit = 'ft';
                const value = toFloat(raw, Number(def ?? 0));
                vars[key] = value * UNIT_TO_FT[unit];
                display[key] = fmt(value) + ' ' + unit;
                break;
            }
            case 'select': {
                const options = field.options || [];
                const fallback = def ?? options[0]?.value ?? 0;
                vars[key] = toFloat(raw, Number(fallback));
                break;
            }
            default:
                vars[key] = toFloat(raw, Number(def ?? 0));
        }
    }
    for (const c of product.constants || []) vars[c.key] = Number(c.value);
    return [vars, display];
}

export function priceLine(line, rules) {
    const product = line.product;
    const qty = toFloat(line.qty, 1);
    const unit = product.measure_unit ?? 'sq ft';

    const coating = Number(line.coating_rate ?? 0) || 0;
    const addon = Number(line.addon_rate ?? 0) || 0;
    const material = Number(line.material_factor ?? 1);
    const design = Number(line.design_factor ?? 1);
    const baseRate = Number(product.base_rate ?? 0) || 0;
    const usesCoating = product.uses_coating ?? true;
    const rate = usesCoating ? coating + addon : baseRate;

    let area, areaText, unitPrice, priceExplain;
    try {
        const [vars, display] = fieldVars(product, line.inputs || {});
        const areaFormula = String(product.area_formula ?? '0');
        area = Math.max(0, evaluate(areaFormula, vars));
        const areaExplain = explain(areaFormula, vars, display);
        areaText = (areaExplain === fmt(area) ? '' : areaExplain + ' = ') + num(area) + ' ' + unit;

        const priceVars = { ...vars, area, rate, coating, addon, material, design, base_rate: baseRate };
        const priceFormula = String(product.price_formula || DEFAULT_PRICE_FORMULA);
        unitPrice = evaluate(priceFormula, priceVars);
        if (usesCoating && addon > 0) display.rate = '(' + fmt(coating) + ' + ' + fmt(addon) + ')';
        priceExplain = explain(priceFormula, priceVars, display);
    } catch (e) {
        if (!(e instanceof FormulaError)) throw e;
        return { area: 0, rate, unit_price: 0, raw_amount: 0, min_applied: false, amount: 0, area_text: '', formula: '', error: e.message };
    }

    const raw = round2(unitPrice * qty);
    const hasMin = product.min_charge !== undefined && product.min_charge !== null && product.min_charge !== '';
    const min = hasMin ? Number(product.min_charge) : Number(rules.min_item ?? 0);
    const minApplied = (product.apply_min ?? true) && raw > 0 && raw < min;
    const amount = minApplied ? round2(min) : raw;

    let formula = priceExplain + ' × ' + num(qty) + ' = ' + money(raw);
    if (minApplied) formula += ', raised to minimum item charge ' + money(amount);

    return { area, rate, unit_price: unitPrice, raw_amount: raw, min_applied: !!minApplied, amount, area_text: areaText, formula, error: null };
}

export function priceQuote(lines, options, rules) {
    const results = lines.map((l) => priceLine(l, rules));
    const subtotal = round2(results.reduce((s, r) => s + r.amount, 0));

    const dv = Number(options.discount_value ?? 0) || 0;
    let discount = 0;
    if (options.discount_type === 'percent') discount = round2((subtotal * dv) / 100);
    else if (options.discount_type === 'flat') discount = round2(dv);
    discount = Math.max(0, Math.min(discount, subtotal));
    const discountPct = subtotal > 0 ? (discount / subtotal) * 100 : 0;

    const visit = round2(Number(options.visit_charge ?? 0) || 0);
    const taxableBeforeMin = round2(subtotal - discount + visit);

    const minJob = Number(rules.min_job ?? 0);
    const minJobApplied = taxableBeforeMin > 0 && taxableBeforeMin < minJob;
    const taxable = minJobApplied ? round2(minJob) : taxableBeforeMin;

    const gstPct = Number(rules.gst_pct ?? 0);
    const intra = options.intra_state === undefined ? true : !!options.intra_state;
    let cgst = 0, sgst = 0, igst = 0;
    if (intra) {
        cgst = round2((taxable * gstPct) / 2 / 100);
        sgst = cgst;
    } else {
        igst = round2((taxable * gstPct) / 100);
    }
    const gstTotal = round2(cgst + sgst + igst);

    return {
        lines: results,
        subtotal,
        discount_amount: discount,
        discount_pct: discountPct,
        visit_charge: visit,
        taxable_before_min: taxableBeforeMin,
        min_job_applied: minJobApplied,
        min_job_adjustment: round2(taxable - taxableBeforeMin),
        taxable,
        intra_state: intra,
        cgst,
        sgst,
        igst,
        gst_total: gstTotal,
        total: round2(taxable + gstTotal),
    };
}

export const advance = (total, pct) => round2((total * pct) / 100);
