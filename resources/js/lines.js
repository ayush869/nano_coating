/**
 * Shared item ("line") logic for the staff quote builder and the customer
 * request form: which product definition and rates a line uses, its default
 * inputs, and the pricing-engine input.
 *
 * A saved line carries `snap` (its stored product definition, names and
 * rates). While a selection is unchanged the snapshot is used, exactly like
 * the server (App\Services\LineResolver).
 */
import { priceLine, money, num } from './pricing.js';

let seq = 0;
export const uid = () => `l${Date.now().toString(36)}${seq++}`;
export const clone = (o) => JSON.parse(JSON.stringify(o));

export const UNITS = [
    { value: 'ft', label: 'ft' },
    { value: 'in', label: 'in' },
    { value: 'm', label: 'm' },
    { value: 'cm', label: 'cm' },
];

export function lineHelpers(card) {
    const byId = (list, id) => list.find((x) => x.id === Number(id)) || null;

    const h = {
        money,
        num,
        units: UNITS,

        find(table, id) {
            return byId(card[table], id);
        },

        product(line) {
            const snap = line.snap?.product;
            if (snap && snap.id === Number(line.product_id)) return snap;
            return byId(card.products, line.product_id);
        },

        productsFor(serviceId) {
            return card.products.filter((p) => !serviceId || p.service_id === Number(serviceId));
        },

        resolve(line, kind, table) {
            const id = Number(line[`${kind}_id`]);
            if (!id) return null;
            const snap = line.snap?.[kind];
            if (snap && snap.id === id) return snap;
            return byId(card[table], id);
        },

        withSnap(line, kind, list) {
            const snap = line.snap?.[kind];
            if (snap && !list.some((m) => m.id === snap.id)) return [snap, ...list];
            return list;
        },

        coatingsFor(line) {
            const p = h.product(line);
            const allowed = p?.coatings ?? [];
            const list = allowed.length ? card.coatings.filter((c) => allowed.includes(c.id)) : card.coatings;
            return h.withSnap(line, 'coating', list);
        },

        materialsFor(line) {
            const coating = byId(card.coatings, line.coating_id);
            const allowed = coating?.materials ?? [];
            const list = allowed.length ? card.materials.filter((m) => allowed.includes(m.id)) : card.materials;
            return h.withSnap(line, 'material', list);
        },

        designsFor(line) {
            return h.withSnap(line, 'design', card.designs);
        },

        addonsFor(line) {
            return h.withSnap(line, 'addon', card.addons);
        },

        visibleFields(line, customer = false) {
            return (h.product(line)?.fields ?? []).filter((f) => !customer || f.customer !== false);
        },

        defaultInputs(product) {
            const inputs = {};
            for (const f of product.fields ?? []) {
                inputs[f.key] = f.default ?? (f.type === 'select' ? f.options?.[0]?.value ?? 0 : 0);
                if (f.type === 'dimension') inputs[f.key + '__unit'] = f.unit ?? 'ft';
            }
            return inputs;
        },

        /** Fill any missing inputs with defaults (e.g. a product gained a field). */
        hydrateInputs(line) {
            const p = h.product(line);
            if (!p) return;
            line.inputs = { ...h.defaultInputs(p), ...(line.inputs || {}) };
        },

        newLine(productId) {
            const line = { uid: uid(), id: null, product_id: Number(productId), name: '', qty: 1, inputs: {}, photos: [], photo_urls: [], snap: {} };
            h.applyProductDefaults(line);
            return line;
        },

        applyProductDefaults(line) {
            const p = h.product(line);
            line.inputs = p ? h.defaultInputs(p) : {};
            line.coating_id = line.addon_id = line.material_id = line.design_id = null;
            line.addon_id = '';
            if (!p || p.uses_coating === false) return;
            const coatings = h.coatingsFor(line);
            line.coating_id = coatings.some((c) => c.id === p.default_coating_id) ? p.default_coating_id : coatings[0]?.id ?? null;
            const materials = h.materialsFor(line);
            line.material_id = materials.some((m) => m.id === p.default_material_id) ? p.default_material_id : materials[0]?.id ?? null;
            line.design_id = p.default_design_id ?? card.designs[0]?.id ?? null;
        },

        onProductChange(line) {
            line.snap = {};
            line.id = null;
            h.applyProductDefaults(line);
        },

        onCoatingChange(line) {
            const ok = h.materialsFor(line);
            if (!ok.some((m) => m.id === Number(line.material_id))) line.material_id = ok[0]?.id ?? null;
        },

        toPricing(line) {
            const p = h.product(line);
            if (!p) return { product: { fields: [], constants: [], area_formula: '0', price_formula: '0' }, qty: 0, inputs: {} };
            const base = { product: p, qty: line.qty, inputs: line.inputs };
            if (p.uses_coating === false) return base;
            return {
                ...base,
                coating_rate: h.resolve(line, 'coating', 'coatings')?.value ?? 0,
                addon_rate: line.addon_id ? h.resolve(line, 'addon', 'addons')?.value ?? 0 : 0,
                material_factor: h.resolve(line, 'material', 'materials')?.value ?? 1,
                design_factor: h.resolve(line, 'design', 'designs')?.value ?? 1,
            };
        },

        price(line, rules) {
            return priceLine(h.toPricing(line), rules);
        },

        optionLabel(field, value) {
            return (field.options || []).find((o) => Number(o.value) === Number(value))?.label ?? value;
        },

        stepInput(line, key, delta, min = 0) {
            line.inputs[key] = Math.max(min, (Number(line.inputs[key]) || 0) + delta);
        },

        /** Strip UI-only keys before posting. */
        payloadLine(line) {
            const { uid: _u, photo_urls: _p, ...rest } = line;
            return rest;
        },
    };
    return h;
}

/** Upload one photo; returns {path, url}. */
export async function uploadPhoto(url, csrf, file) {
    const body = new FormData();
    body.append('photo', file);
    const res = await fetch(url, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || data.errors?.photo?.[0] || 'Upload failed');
    return data;
}
