import { variables, FormulaError, parse, FUNCTIONS } from './formula.js';
import { priceLine, money, num, RESERVED, FIELD_TYPES } from './pricing.js';
import { UNITS, clone } from './lines.js';

const KEY = /^[a-z][a-z0-9_]{0,30}$/;
const slugKey = (s) => String(s).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^(\d)/, 'n$1').slice(0, 30);

/**
 * Admin product designer: inputs, constants, formulas and a live test panel.
 * Validation mirrors App\Services\Catalog\ProductDefinition; the server
 * checks again on save.
 */
export default (config) => ({
    def: clone(config.definition),
    rates: config.rates,
    minItem: config.minItem,
    units: UNITS,
    types: FIELD_TYPES,
    typeLabels: { number: 'Number', counter: 'Counter (+ / −)', dimension: 'Length with unit', select: 'Choice list' },
    test: { inputs: {}, qty: 1, coating: null, material: null, design: null, addon: null },
    money,
    num,

    init() {
        this.def.fields = (this.def.fields || []).map((f) => ({ options: [], help: '', customer: true, ...f }));
        this.def.constants = this.def.constants || [];
        this.test.coating = config.defaults.coating ?? this.rates.coatings[0]?.id ?? null;
        this.test.material = config.defaults.material ?? this.rates.materials[0]?.id ?? null;
        this.test.design = config.defaults.design ?? this.rates.designs[0]?.id ?? null;
        this.resetTestInputs();
    },

    resetTestInputs() {
        const inputs = {};
        for (const f of this.def.fields) {
            inputs[f.key] = f.default ?? (f.type === 'select' ? f.options?.[0]?.value ?? 0 : 0);
            if (f.type === 'dimension') inputs[f.key + '__unit'] = f.unit || 'ft';
        }
        this.test.inputs = inputs;
    },

    // ---------- editing ----------
    addField(type = 'number') {
        const n = this.def.fields.length + 1;
        const f = { key: `input_${n}`, label: `Input ${n}`, type, default: type === 'counter' ? 1 : 0, help: '', customer: true, options: [] };
        if (type === 'dimension') f.unit = 'ft';
        if (type === 'select') f.options = [{ label: 'Option A', value: 1 }, { label: 'Option B', value: 2 }];
        this.def.fields.push(f);
        this.resetTestInputs();
    },

    onLabel(f) {
        if (!f._keyTouched) f.key = slugKey(f.label) || f.key;
    },

    onTypeChange(f) {
        if (f.type === 'select' && !f.options?.length) f.options = [{ label: 'Option A', value: 1 }];
        if (f.type === 'dimension' && !f.unit) f.unit = 'ft';
        this.resetTestInputs();
    },

    moveField(i, d) {
        const j = i + d;
        if (j < 0 || j >= this.def.fields.length) return;
        const [f] = this.def.fields.splice(i, 1);
        this.def.fields.splice(j, 0, f);
    },

    removeField(i) {
        this.def.fields.splice(i, 1);
        this.resetTestInputs();
    },

    addConstant() {
        const n = this.def.constants.length + 1;
        this.def.constants.push({ key: `value_${n}`, label: `Value ${n}`, value: 1 });
    },

    insert(which, name) {
        const k = which === 'area' ? 'area_formula' : 'price_formula';
        const cur = (this.def[k] || '').trimEnd();
        this.def[k] = cur ? `${cur} ${/[\d)a-z_]$/i.test(cur) ? '* ' : ''}${name}` : name;
    },

    // ---------- validation ----------
    get names() {
        return [...this.def.fields.map((f) => f.key), ...this.def.constants.map((c) => c.key)];
    },

    keyError(key, idx, list) {
        if (!KEY.test(key)) return 'Use a–z, 0–9 and _ , starting with a letter.';
        if (RESERVED.includes(key) || FUNCTIONS.includes(key)) return `"${key}" is reserved.`;
        if (this.names.filter((n) => n === key).length > 1) return `"${key}" is used twice.`;
        return null;
    },

    formulaError(which) {
        const src = which === 'area' ? this.def.area_formula : (this.def.price_formula || 'area * rate * material * design');
        if (!src || !src.trim()) return 'Required.';
        try {
            parse(src);
            const allowed = which === 'area' ? this.names : [...this.names, ...RESERVED.filter((r) => r !== 'qty')];
            const unknown = variables(src).filter((v) => !allowed.includes(v));
            return unknown.length ? `Unknown name(s): ${unknown.join(', ')}` : null;
        } catch (e) {
            if (e instanceof FormulaError) return e.message;
            throw e;
        }
    },

    get hasErrors() {
        return !!(this.formulaError('area') || this.formulaError('price')
            || this.def.fields.some((f, i) => this.keyError(f.key, i) || !f.label)
            || this.def.constants.some((c, i) => this.keyError(c.key, i)));
    },

    // ---------- live test ----------
    get preview() {
        const rate = (list, id) => list.find((x) => x.id === Number(id))?.value;
        return priceLine({
            product: this.def,
            qty: this.test.qty,
            inputs: this.test.inputs,
            coating_rate: rate(this.rates.coatings, this.test.coating) ?? 0,
            addon_rate: this.test.addon ? rate(this.rates.addons, this.test.addon) ?? 0 : 0,
            material_factor: rate(this.rates.materials, this.test.material) ?? 1,
            design_factor: rate(this.rates.designs, this.test.design) ?? 1,
        }, { min_item: this.minItem });
    },

    submit(event) {
        if (this.hasErrors) {
            alert('Please fix the highlighted problems first.');
            return;
        }
        const def = clone(this.def);
        def.fields = def.fields.map(({ _keyTouched, ...f }) => f);
        event.target.querySelector('input[name=definition]').value = JSON.stringify(def);
        event.target.submit();
    },
});
