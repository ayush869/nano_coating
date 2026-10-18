@extends('layouts.staff')
@section('title', $product->exists ? 'Edit '.$product->name : 'New product')

@section('content')
@if ($templates)
    <h1 class="mb-1 text-xl font-semibold">New product</h1>
    <p class="mb-4 text-sm text-slate-500">Pick a starting point. You can change every input and formula afterwards.</p>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($templates as $key => $t)
            <a href="{{ route('admin.products.create', ['template' => $key, 'service' => request('service')]) }}" class="card card-body transition hover:-translate-y-0.5 hover:border-brand-600 hover:shadow-md">
                <div class="font-semibold text-slate-900">{{ $t['label'] }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ $t['hint'] }}</div>
                <div class="mt-2 font-mono text-[11px] text-slate-500">{{ $t['measure_unit'] }} = {{ $t['area_formula'] }}</div>
            </a>
        @endforeach
    </div>
@else
@php
    $config = [
        'definition' => old('definition') ? json_decode(old('definition'), true) : $definition,
        'rates' => [
            'coatings' => $coatings->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'value' => $c->value()])->values(),
            'materials' => $materials->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'value' => $c->value()])->values(),
            'designs' => $designs->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'value' => $c->value()])->values(),
            'addons' => \App\Models\Addon::active()->ordered()->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'value' => $c->value()])->values(),
        ],
        'defaults' => ['coating' => $product->default_coating_id, 'material' => $product->default_material_id, 'design' => $product->default_design_id],
        'minItem' => $minItem,
    ];
@endphp
<form method="post" enctype="multipart/form-data"
      action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
      x-data="productDesigner(@js($config))" x-effect="syncArea()" @submit.prevent="submit($event)" x-cloak>
    @csrf
    @if ($product->exists) @method('PUT') @endif
    <input type="hidden" name="definition">

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="text-xl font-semibold">{{ $product->exists ? $product->name : 'New product' }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('admin.products.index') }}" class="btn">Back</a>
            <button class="btn btn-primary" :class="hasErrors && 'opacity-60'">Save product</button>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Basics --}}
            <section class="card card-body">
                <h2 class="mb-3 font-semibold">1. Basics</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><label class="label">Product name *</label><input class="input" name="name" value="{{ old('name', $product->name) }}" required placeholder="e.g. Curved accent chair"></div>
                    <div>
                        <label class="label">Service category *</label>
                        <select class="input" name="service_id">
                            @foreach ($services as $s)<option value="{{ $s->id }}" @selected(old('service_id', $product->service_id) == $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2"><label class="label">Short description (shown to customers)</label><input class="input" name="description" maxlength="255" value="{{ old('description', $product->description) }}"></div>
                    <div>
                        <label class="label">Measured in</label>
                        <input class="input" x-model="def.measure_unit" list="units" placeholder="sq ft">
                        <datalist id="units"><option value="sq ft"><option value="rft"><option value="piece"><option value="sq m"></datalist>
                    </div>
                    <div><label class="label">Sort order</label><input class="input" type="number" min="0" name="sort" value="{{ old('sort', $product->sort) }}"></div>
                    <div class="sm:col-span-2">
                        <label class="label">Image</label>
                        <div class="flex items-center gap-3">
                            @if ($imageUrl)<img src="{{ $imageUrl }}" class="h-14 rounded border" alt="">@endif
                            <input type="file" name="image" accept="image/*" class="text-sm">
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked(old('active', $product->active))> Active</label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="customer_visible" value="1" @checked(old('customer_visible', $product->customer_visible))> Customers can pick it on the website</label>
                </div>
            </section>

            {{-- Inputs --}}
            <section class="card card-body">
                <h2 class="font-semibold">2. Inputs (what is measured or counted)</h2>
                <p class="mb-3 text-xs text-slate-500">Each input gets a <b>formula name</b> you use in the formulas below. Lengths are converted to feet automatically.</p>
                <div class="space-y-3">
                    <template x-for="(f, i) in def.fields" :key="i">
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="grid gap-2 sm:grid-cols-12">
                                <div class="sm:col-span-4"><label class="label">Label</label><input class="input" x-model="f.label" @input="onLabel(f)"></div>
                                <div class="sm:col-span-3">
                                    <label class="label">Formula name</label>
                                    <input class="input font-mono" x-model="f.key" @input="f._keyTouched = true" :class="keyError(f.key, i) && 'border-red-400'">
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="label">Type</label>
                                    <select class="input" x-model="f.type" @change="onTypeChange(f)">
                                        <template x-for="t in types" :key="t"><option :value="t" x-text="typeLabels[t]" :selected="t === f.type"></option></template>
                                    </select>
                                </div>
                                <div class="sm:col-span-2"><label class="label">Default</label><input class="input" type="number" step="any" x-model.number="f.default" @change="resetTestInputs()"></div>
                            </div>
                            <p class="mt-1 text-xs text-red-700" x-show="keyError(f.key, i)" x-text="keyError(f.key, i)"></p>

                            <div class="mt-2 grid gap-2 sm:grid-cols-12">
                                <template x-if="f.type === 'dimension'">
                                    <div class="sm:col-span-3">
                                        <label class="label">Default unit</label>
                                        <select class="input" x-model="f.unit"><template x-for="u in units" :key="u.value"><option :value="u.value" x-text="u.label" :selected="u.value === f.unit"></option></template></select>
                                    </div>
                                </template>
                                <template x-if="f.type !== 'select'">
                                    <div class="grid grid-cols-2 gap-2 sm:col-span-3">
                                        <div><label class="label">Min</label><input class="input" type="number" step="any" x-model.number="f.min"></div>
                                        <div><label class="label">Max</label><input class="input" type="number" step="any" x-model.number="f.max"></div>
                                    </div>
                                </template>
                                <div class="sm:col-span-6"><label class="label">Help text</label><input class="input" x-model="f.help" placeholder="Shown under the input"></div>
                            </div>

                            <template x-if="f.type === 'select'">
                                <div class="mt-2">
                                    <label class="label">Choices (label → number used in the formula)</label>
                                    <div class="space-y-1">
                                        <template x-for="(o, k) in f.options" :key="k">
                                            <div class="flex gap-2">
                                                <input class="input" x-model="o.label" placeholder="Label">
                                                <input class="input w-28" type="number" step="any" x-model.number="o.value" placeholder="Value">
                                                <button type="button" class="btn btn-sm btn-danger" @click="f.options.splice(k, 1)">✕</button>
                                            </div>
                                        </template>
                                    </div>
                                    <button type="button" class="link mt-1 text-xs" @click="f.options.push({ label: '', value: 0 })">+ Add choice</button>
                                </div>
                            </template>

                            <div class="mt-2 flex flex-wrap items-center gap-3 text-xs">
                                <label class="flex items-center gap-1"><input type="checkbox" x-model="f.customer"> Customer fills this in</label>
                                <span class="text-slate-400" x-show="!f.customer">(customers get the default)</span>
                                <div class="ml-auto flex gap-1">
                                    <button type="button" class="btn btn-sm" @click="moveField(i, -1)">↑</button>
                                    <button type="button" class="btn btn-sm" @click="moveField(i, 1)">↓</button>
                                    <button type="button" class="btn btn-sm btn-danger" @click="removeField(i)">Remove</button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm" @click="addField('counter')">+ Counter</button>
                    <button type="button" class="btn btn-sm" @click="addField('dimension')">+ Length with unit</button>
                    <button type="button" class="btn btn-sm" @click="addField('select')">+ Choice list</button>
                    <button type="button" class="btn btn-sm" @click="addField('number')">+ Number</button>
                </div>

                <div class="mt-4 rounded-lg border border-dashed border-brand-600/40 bg-brand-50/60 p-3">
                    <div class="text-sm font-medium text-brand-800">Quick add a counted part</div>
                    <p class="mb-2 text-xs text-slate-600">E.g. “Ottoman”, 6 <span x-text="def.measure_unit || 'sq ft'"></span> each. Creates the counter, its size and the formula term in one go.</p>
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="min-w-40 flex-1"><label class="label">Part name</label><input class="input" x-model="part.label" placeholder="e.g. Ottoman" @keydown.enter.prevent="addPart()"></div>
                        <div class="w-32"><label class="label">Size of one (<span x-text="def.measure_unit || 'sq ft'"></span>)</label><input class="input" type="number" step="any" min="0" x-model="part.size" @keydown.enter.prevent="addPart()"></div>
                        <div class="w-24"><label class="label">Default count</label><input class="input" type="number" min="0" x-model="part.count"></div>
                        <button type="button" class="btn btn-primary" :disabled="!part.label.trim() || !(Number(part.size) > 0)" @click="addPart()">Add part</button>
                    </div>
                </div>
            </section>

            {{-- Constants --}}
            <section class="card card-body">
                <h2 class="font-semibold">3. Constants (standard sizes you can tune)</h2>
                <p class="mb-3 text-xs text-slate-500">E.g. “seat = 4.5 sq ft”. Change them here without touching the formula.</p>
                <div class="space-y-2">
                    <template x-for="(c, i) in def.constants" :key="i">
                        <div>
                            <div class="flex gap-2">
                                <input class="input" x-model="c.label" placeholder="Label">
                                <input class="input w-40 font-mono" x-model="c.key" placeholder="formula_name" :class="keyError(c.key, i) && 'border-red-400'">
                                <input class="input w-28" type="number" step="any" x-model.number="c.value">
                                <button type="button" class="btn btn-sm btn-danger" @click="def.constants.splice(i, 1)">✕</button>
                            </div>
                            <p class="text-xs text-red-700" x-show="keyError(c.key, i)" x-text="keyError(c.key, i)"></p>
                        </div>
                    </template>
                </div>
                <button type="button" class="btn btn-sm mt-3" @click="addConstant()">+ Constant</button>
            </section>

            {{-- Formulas --}}
            <section class="card card-body space-y-4">
                <div>
                    <h2 class="font-semibold">4. Formulas</h2>
                    <p class="text-xs text-slate-500">Use + − * / ^, brackets, comparisons (&lt; &gt; == …) and min(), max(), round(x, 2), ceil(), floor(), abs(), sqrt(), if(condition, then, else). Click a name to insert it.</p>
                </div>
                <div>
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <label class="label mb-0">Area formula → result in <span x-text="def.measure_unit || 'units'"></span></label>
                        <label class="flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium"
                               :class="autoArea ? 'border-brand-600 bg-brand-50 text-brand-800' : 'border-slate-300 text-slate-600'">
                            <input type="checkbox" class="sr-only" :checked="autoArea" @change="$event.target.checked ? useAutoArea() : (autoArea = false)">
                            <span class="relative inline-block h-4 w-7 rounded-full transition" :class="autoArea ? 'bg-brand-600' : 'bg-slate-300'">
                                <span class="absolute top-0.5 h-3 w-3 rounded-full bg-white transition-all" :class="autoArea ? 'left-3.5' : 'left-0.5'"></span>
                            </span>
                            Build automatically from inputs
                        </label>
                    </div>
                    <div x-show="autoArea" class="mb-2 rounded-lg bg-brand-50 px-3 py-2 text-xs text-brand-800">
                        Automatic: parts with a matching size are added up (seats × seat size + …), lengths, choices and other values multiply, and inputs named “openings” or “deduct…” are subtracted. Edit the formula to take over by hand.
                    </div>
                    <div x-show="!autoArea && !sameAsAuto()" class="mb-2 flex flex-wrap items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        <span>Suggested from your inputs: <code class="font-mono" x-text="autoAreaFormula"></code></span>
                        <button type="button" class="link" @click="useAutoArea()">Use it</button>
                    </div>
                    <textarea class="input font-mono text-sm" rows="2" x-model="def.area_formula" @input="autoArea = false" :class="formulaError('area') && 'border-red-400'"></textarea>
                    <p class="mt-1 text-xs text-red-700" x-show="formulaError('area')" x-text="formulaError('area')"></p>
                    <p class="mt-1 text-xs text-slate-600" x-show="!formulaError('area') && readable('area')"><span class="font-medium">In words:</span> <span x-text="readable('area')"></span></p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <template x-for="op in keypad" :key="op[0]"><button type="button" class="rounded border border-slate-200 bg-white px-2 py-0.5 font-mono text-xs hover:border-brand-600" @click="typeOp('area', op[1])" x-text="op[0]"></button></template>
                    </div>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <template x-for="n in names" :key="n"><button type="button" class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] hover:bg-brand-100" @click="insert('area', n)" x-text="n"></button></template>
                    </div>
                </div>
                <div>
                    <label class="label">Price formula → price of one item, before quantity</label>
                    <div class="mb-2 flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm" @click="applyPricePreset('standard')">Rate card: area × rate × material × design</button>
                        <button type="button" class="btn btn-sm" @click="applyPricePreset('fixed')">Fixed ₹ per unit: area × rate</button>
                    </div>
                    <textarea class="input font-mono text-sm" rows="2" x-model="def.price_formula" :class="formulaError('price') && 'border-red-400'"></textarea>
                    <p class="mt-1 text-xs text-red-700" x-show="formulaError('price')" x-text="formulaError('price')"></p>
                    <p class="mt-1 text-xs text-slate-600" x-show="!formulaError('price') && readable('price')"><span class="font-medium">In words:</span> <span x-text="readable('price')"></span></p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <template x-for="op in keypad" :key="op[0]"><button type="button" class="rounded border border-slate-200 bg-white px-2 py-0.5 font-mono text-xs hover:border-brand-600" @click="typeOp('price', op[1])" x-text="op[0]"></button></template>
                    </div>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <template x-for="n in ['area', 'rate', 'material', 'design', 'coating', 'addon', 'base_rate', ...names]" :key="n">
                            <button type="button" class="rounded px-1.5 py-0.5 font-mono text-[11px]" :class="['area','rate','material','design','coating','addon','base_rate'].includes(n) ? 'bg-brand-100 text-brand-800' : 'bg-slate-100'" @click="insert('price', n)" x-text="n"></button>
                        </template>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500"><b>area</b> = result above · <b>rate</b> = coating + add-on ₹ (or the fixed rate) · <b>material</b>, <b>design</b> = factors · <b>coating</b>, <b>addon</b> = their ₹ rates separately.</p>

                    <div class="mt-3 rounded-lg border border-dashed border-brand-600/40 bg-brand-50/60 p-3">
                        <div class="text-sm font-medium text-brand-800">Price rules, no formula typing</div>
                        <p class="mb-2 text-xs text-slate-600">Fill in what you need, leave the rest blank, then build. Uses <span x-text="def.uses_coating ? 'the rate card (coating, material, design)' : 'the fixed rate'"></span>.</p>
                        <div class="grid gap-2 sm:grid-cols-4">
                            <div><label class="label">Bill at least (<span x-text="def.measure_unit || 'units'"></span>)</label><input class="input" type="number" min="0" step="any" x-model="priceHelper.minArea" placeholder="e.g. 10"></div>
                            <div><label class="label">Discount above (<span x-text="def.measure_unit || 'units'"></span>)</label><input class="input" type="number" min="0" step="any" x-model="priceHelper.discountAbove" placeholder="e.g. 500"></div>
                            <div><label class="label">Discount %</label><input class="input" type="number" min="0" max="100" step="any" x-model="priceHelper.discountPct"></div>
                            <div><label class="label">Extra ₹ per item</label><input class="input" type="number" min="0" step="any" x-model="priceHelper.extra" placeholder="e.g. 100"></div>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm mt-2" @click="buildPrice()">Build price formula</button>
                    </div>
                </div>
            </section>

            {{-- Pricing --}}
            <section class="card card-body space-y-3">
                <h2 class="font-semibold">5. Rates &amp; minimum charge</h2>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="def.uses_coating"> Priced with a coating from the rate card (coating, material, design, add-on)</label>

                <template x-if="def.uses_coating">
                    <div class="space-y-3">
                        <div>
                            <label class="label">Coatings offered (none ticked = all)</label>
                            <div class="flex flex-wrap gap-3 text-sm">
                                @foreach ($coatings as $c)
                                    <label class="flex items-center gap-1"><input type="checkbox" name="coating_ids[]" value="{{ $c->id }}" @checked(in_array($c->id, old('coating_ids', $coatingIds)))> {{ $c->name }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach (['coating' => $coatings, 'material' => $materials, 'design' => $designs] as $kind => $list)
                                <div>
                                    <label class="label">Default {{ $kind }}</label>
                                    <select class="input" name="default_{{ $kind }}_id">
                                        <option value="">—</option>
                                        @foreach ($list as $m)<option value="{{ $m->id }}" @selected(old('default_'.$kind.'_id', $product->{'default_'.$kind.'_id'}) == $m->id)>{{ $m->name }}</option>@endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </template>
                <template x-if="!def.uses_coating">
                    <div class="max-w-xs">
                        <label class="label">Fixed rate (₹ per <span x-text="def.measure_unit"></span>) → <code>rate</code></label>
                        <input class="input" type="number" step="0.01" min="0" x-model.number="def.base_rate">
                    </div>
                </template>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" x-model="def.apply_min"> Apply a minimum charge per item</label>
                    <div x-show="def.apply_min">
                        <label class="label">Minimum for this product (₹, blank = global {{ (int) $minItem }})</label>
                        <input class="input" type="number" step="0.01" min="0" x-model="def.min_charge">
                    </div>
                </div>
            </section>
        </div>

        {{-- Live test --}}
        <aside class="lg:sticky lg:top-20 lg:self-start">
            <section class="card card-body space-y-3">
                <h2 class="font-semibold">Try it</h2>
                <template x-for="f in def.fields" :key="f.key">
                    <div>
                        <label class="label" x-text="f.label + (f.customer ? '' : ' (staff only)')"></label>
                        <template x-if="f.type === 'select'">
                            <select class="input" x-model.number="test.inputs[f.key]">
                                <template x-for="o in f.options" :key="o.label"><option :value="o.value" x-text="o.label" :selected="Number(o.value) === Number(test.inputs[f.key])"></option></template>
                            </select>
                        </template>
                        <template x-if="f.type === 'dimension'">
                            <div class="flex gap-1">
                                <input class="input" type="number" step="any" x-model.number="test.inputs[f.key]">
                                <select class="input w-20" x-model="test.inputs[f.key + '__unit']"><template x-for="u in units" :key="u.value"><option :value="u.value" x-text="u.label" :selected="u.value === test.inputs[f.key + '__unit']"></option></template></select>
                            </div>
                        </template>
                        <template x-if="f.type === 'number' || f.type === 'counter'">
                            <input class="input" type="number" step="any" x-model.number="test.inputs[f.key]">
                        </template>
                    </div>
                </template>
                <div><label class="label">Quantity</label><input class="input" type="number" min="1" x-model.number="test.qty"></div>
                <template x-if="def.uses_coating">
                    <div class="grid grid-cols-2 gap-2">
                        <select class="input" x-model.number="test.coating"><template x-for="c in rates.coatings" :key="c.id"><option :value="c.id" x-text="c.name" :selected="c.id === test.coating"></option></template></select>
                        <select class="input" x-model.number="test.material"><template x-for="c in rates.materials" :key="c.id"><option :value="c.id" x-text="c.name" :selected="c.id === test.material"></option></template></select>
                        <select class="input" x-model.number="test.design"><template x-for="c in rates.designs" :key="c.id"><option :value="c.id" x-text="c.name" :selected="c.id === test.design"></option></template></select>
                        <select class="input" x-model.number="test.addon"><option value="">No add-on</option><template x-for="c in rates.addons" :key="c.id"><option :value="c.id" x-text="c.name"></option></template></select>
                    </div>
                </template>

                <div class="rounded-lg bg-slate-50 p-3 text-sm">
                    <template x-if="preview.error">
                        <div class="font-medium text-red-700" x-text="'Error: ' + preview.error"></div>
                    </template>
                    <template x-if="!preview.error">
                        <div>
                            <div class="text-xs text-slate-600" x-text="preview.area_text"></div>
                            <div class="mt-1 font-mono text-[11px] break-words text-slate-500" x-text="preview.formula"></div>
                            <div class="mt-2 text-xl font-semibold text-brand-800" x-text="money(preview.amount)"></div>
                            <div class="text-xs text-slate-500">before GST, visit charge and discount</div>
                        </div>
                    </template>
                </div>
                <button type="button" class="link text-xs" @click="resetTestInputs()">Reset to defaults</button>
            </section>
        </aside>
    </div>
</form>
@endif
@endsection
