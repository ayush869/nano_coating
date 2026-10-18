{{--
    Inputs for one item, rendered from its product definition. Used inside an
    Alpine x-for over `lines` (variable `line`) in the staff quote builder and
    the customer request form. $customer hides staff-only inputs and rates.
--}}
@php $customer = $customer ?? false; @endphp

<div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
    <template x-for="f in visibleFields(line, {{ $customer ? 'true' : 'false' }})" :key="f.key">
        <div :class="f.type === 'dimension' ? 'col-span-2 sm:col-span-1' : ''">
            <label class="label" x-text="f.label"></label>

            {{-- counter --}}
            <template x-if="f.type === 'counter'">
                <div class="flex items-center gap-1">
                    <button type="button" class="btn h-10 w-10 shrink-0 px-0 text-lg" @click="stepInput(line, f.key, -1, f.min ?? 0)">−</button>
                    <input class="input h-10 text-center" type="number" inputmode="numeric" step="1" :min="f.min ?? 0" :max="f.max ?? null" x-model.number="line.inputs[f.key]">
                    <button type="button" class="btn h-10 w-10 shrink-0 px-0 text-lg" @click="stepInput(line, f.key, 1, f.min ?? 0)">+</button>
                </div>
            </template>

            {{-- dimension: value + unit --}}
            <template x-if="f.type === 'dimension'">
                <div class="flex gap-1">
                    <input class="input" type="number" inputmode="decimal" step="any" :min="f.min ?? 0" x-model.number="line.inputs[f.key]">
                    <select class="input w-20 shrink-0" x-model="line.inputs[f.key + '__unit']">
                        <template x-for="u in units" :key="u.value">
                            <option :value="u.value" x-text="u.label" :selected="u.value === line.inputs[f.key + '__unit']"></option>
                        </template>
                    </select>
                </div>
            </template>

            {{-- select --}}
            <template x-if="f.type === 'select'">
                <select class="input" x-model.number="line.inputs[f.key]">
                    <template x-for="o in f.options" :key="o.label">
                        <option :value="o.value" :selected="Number(o.value) === Number(line.inputs[f.key])" x-text="o.label"></option>
                    </template>
                </select>
            </template>

            {{-- number --}}
            <template x-if="f.type === 'number'">
                <input class="input" type="number" inputmode="decimal" :step="f.step ?? 'any'" :min="f.min ?? 0" :max="f.max ?? null" x-model.number="line.inputs[f.key]">
            </template>

            <p class="mt-0.5 text-[11px] text-slate-500" x-show="f.help" x-text="f.help"></p>
        </div>
    </template>

    <div>
        <label class="label">Quantity</label>
        <input class="input h-10" type="number" inputmode="numeric" min="1" step="1" x-model.number="line.qty">
    </div>
</div>

<template x-if="product(line)?.uses_coating !== false">
    <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div x-show="{{ $customer ? 'coatingsFor(line).length > 1' : 'true' }}">
            <label class="label">{{ $customer ? 'Protection type' : 'Coating' }}</label>
            <select class="input" x-model.number="line.coating_id" @change="onCoatingChange(line)">
                <template x-for="c in coatingsFor(line)" :key="c.id">
                    <option :value="c.id" :selected="c.id == line.coating_id" x-text="c.name{{ $customer ? '' : " + ' (' + money(c.value, false) + ')'" }}"></option>
                </template>
            </select>
        </div>
        <div>
            <label class="label">Material</label>
            <select class="input" x-model.number="line.material_id">
                <template x-for="m in materialsFor(line)" :key="m.id">
                    <option :value="m.id" :selected="m.id == line.material_id" x-text="m.name{{ $customer ? '' : " + ' ×' + num(m.value)" }}"></option>
                </template>
            </select>
        </div>
        <div>
            <label class="label">{{ $customer ? 'Style' : 'Design' }}</label>
            <select class="input" x-model.number="line.design_id">
                <template x-for="d in designsFor(line)" :key="d.id">
                    <option :value="d.id" :selected="d.id == line.design_id" x-text="d.name{{ $customer ? '' : " + ' ×' + num(d.value)" }}"></option>
                </template>
            </select>
        </div>
        <div>
            <label class="label">{{ $customer ? 'Extra protection' : 'Add-on layer' }}</label>
            <select class="input" x-model="line.addon_id">
                <option value="">None</option>
                <template x-for="a in addonsFor(line)" :key="a.id">
                    <option :value="a.id" :selected="a.id == line.addon_id" x-text="a.name{{ $customer ? '' : " + ' (+' + money(a.value, false) + ')'" }}"></option>
                </template>
            </select>
        </div>
    </div>
</template>
