<?php

namespace Database\Seeders;

use App\Models\Coating;
use App\Models\Design;
use App\Models\Material;
use App\Models\Product;
use App\Models\Service;
use App\Services\Catalog\ProductDefinition;
use App\Services\Catalog\ProductTemplates as T;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A starter catalog. Every product is an ordinary admin-defined product:
 * change its inputs, sizes or formulas in Admin → Products.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $coating = fn ($n) => Coating::where('name', $n)->value('id');
        $material = fn ($n) => Material::where('name', $n)->value('id');
        $design = fn ($n) => Design::where('name', $n)->value('id');
        $areaPrice = 'area * rate * material * design';

        $catalog = [
            ['Sofa & upholstery', '🛋️', 'Sofas, sectionals, recliners and chairs in fabric or leather.', [
                [
                    'name' => 'Sofa (build by parts)',
                    'description' => 'Any sofa, sectional or L-shape: count seats, backs, arms and extras.',
                    'fields' => [
                        T::counter('seats', 'Seats', 3), T::counter('backs', 'Back sections', 3), T::counter('arms', 'Armrests', 2),
                        T::counter('cushions', 'Loose cushions', 0), T::counter('chaise', 'Chaise', 0), T::counter('ottomans', 'Ottomans', 0),
                    ],
                    'constants' => [
                        ['key' => 'seat_sqft', 'label' => 'Seat (sq ft)', 'value' => 4.5],
                        ['key' => 'back_sqft', 'label' => 'Back section (sq ft)', 'value' => 5],
                        ['key' => 'arm_sqft', 'label' => 'Armrest (sq ft)', 'value' => 6],
                        ['key' => 'cushion_sqft', 'label' => 'Cushion (sq ft)', 'value' => 2],
                        ['key' => 'chaise_sqft', 'label' => 'Chaise (sq ft)', 'value' => 10],
                        ['key' => 'ottoman_sqft', 'label' => 'Ottoman (sq ft)', 'value' => 6],
                    ],
                    'area_formula' => 'seats * seat_sqft + backs * back_sqft + arms * arm_sqft + cushions * cushion_sqft + chaise * chaise_sqft + ottomans * ottoman_sqft',
                    'coatings' => ['Fabric protection', 'Leather protection'], 'default' => ['Fabric protection', 'Cotton / linen', 'Plain'],
                ],
                [
                    'name' => 'Recliner sofa (per seat)',
                    'description' => 'Recliners priced per seat, including the mechanism folds.',
                    'fields' => [T::counter('seats', 'Recliner seats', 3)],
                    'constants' => [['key' => 'recliner_sqft', 'label' => 'One recliner seat (sq ft)', 'value' => 9]],
                    'area_formula' => 'seats * recliner_sqft',
                    'coatings' => ['Fabric protection', 'Leather protection'], 'default' => ['Leather protection', 'Leather', 'Recliner'],
                ],
                [
                    'name' => 'Dining chair', 'description' => 'Seat and back of one dining chair.',
                    'fields' => [], 'constants' => [['key' => 'chair_sqft', 'label' => 'One chair (sq ft)', 'value' => 6]],
                    'area_formula' => 'chair_sqft',
                    'coatings' => ['Fabric protection', 'Leather protection'], 'default' => ['Fabric protection', 'Cotton / linen', 'Plain'],
                ],
                [
                    'name' => 'Curved accent chair', 'description' => 'Tub, barrel and wingback chairs. Pick the size.',
                    'fields' => [['key' => 'size', 'label' => 'Size', 'type' => 'select', 'default' => 12, 'customer' => true,
                        'options' => [['label' => 'Small (tub chair)', 'value' => 8], ['label' => 'Medium (barrel chair)', 'value' => 12], ['label' => 'Large (wingback)', 'value' => 16]]]],
                    'constants' => [], 'area_formula' => 'size',
                    'coatings' => ['Fabric protection', 'Leather protection'], 'default' => ['Fabric protection', 'Velvet', 'Curved'],
                ],
                [
                    'name' => 'Office chair', 'description' => 'Seat, back and arms.',
                    'fields' => [], 'constants' => [['key' => 'chair_sqft', 'label' => 'One chair (sq ft)', 'value' => 8]],
                    'area_formula' => 'chair_sqft',
                    'coatings' => ['Fabric protection', 'Leather protection'], 'default' => ['Fabric protection', 'Polyester / blend', 'Plain'],
                ],
                [
                    'name' => 'Armchair', 'description' => 'Single-seat upholstered armchair.',
                    'fields' => [], 'constants' => [['key' => 'chair_sqft', 'label' => 'One armchair (sq ft)', 'value' => 18]],
                    'area_formula' => 'chair_sqft',
                    'coatings' => ['Fabric protection', 'Leather protection'], 'default' => ['Fabric protection', 'Cotton / linen', 'Plain'],
                ],
            ]],
            ['Mattress & bedding', '🛏️', 'Mattresses, headboards and pillows: anti-microbial and spill-proof.', [
                [
                    'name' => 'Mattress', 'description' => 'Top and sides of the mattress.',
                    'fields' => [
                        ['key' => 'size', 'label' => 'Mattress size', 'type' => 'select', 'default' => 40, 'customer' => true,
                            'options' => [['label' => 'Single', 'value' => 24], ['label' => 'Double', 'value' => 32], ['label' => 'Queen', 'value' => 40], ['label' => 'King', 'value' => 48]]],
                        ['key' => 'cover', 'label' => 'Coverage', 'type' => 'select', 'default' => 1, 'customer' => true,
                            'options' => [['label' => 'Top and sides', 'value' => 1], ['label' => 'All sides incl. bottom', 'value' => 1.6]]],
                    ],
                    'constants' => [], 'area_formula' => 'size * cover',
                    'coatings' => ['Fabric protection'], 'default' => ['Fabric protection', 'Polyester / blend', 'Plain'],
                ],
                [
                    'name' => 'Headboard', 'description' => 'Upholstered headboard, front face.',
                    'fields' => [T::dimension('width', 'Width', 5), T::dimension('height', 'Height', 3)],
                    'constants' => [], 'area_formula' => 'width * height',
                    'coatings' => ['Fabric protection', 'Leather protection'], 'default' => ['Fabric protection', 'Velvet', 'Tufted / buttoned'],
                ],
                [
                    'name' => 'Pillows & cushions', 'description' => 'Per pillow or cushion. Use quantity for several.',
                    'fields' => [], 'constants' => [['key' => 'pillow_sqft', 'label' => 'One pillow (sq ft)', 'value' => 3]],
                    'area_formula' => 'pillow_sqft', 'min_charge' => 150,
                    'coatings' => ['Fabric protection'], 'default' => ['Fabric protection', 'Cotton / linen', 'Plain'],
                ],
            ]],
            ['Glass', '🪟', 'Table tops, windows, shower panels and partitions.', [
                [
                    'name' => 'Glass table top', 'description' => 'Measure length and width.',
                    'fields' => [T::dimension('length', 'Length'), T::dimension('width', 'Width'),
                        ['key' => 'sides', 'label' => 'Sides', 'type' => 'select', 'default' => 1, 'customer' => true,
                            'options' => [['label' => 'Top only', 'value' => 1], ['label' => 'Top and bottom', 'value' => 2]]]],
                    'constants' => [], 'area_formula' => 'length * width * sides',
                    'coatings' => ['Glass protection'], 'default' => ['Glass protection', 'Glass', 'Plain'],
                ],
                [
                    'name' => 'Windows & glass partitions', 'description' => 'Height × width of one pane, times the number of panes.',
                    'fields' => [T::dimension('height', 'Pane height', 4), T::dimension('width', 'Pane width', 3), T::counter('panes', 'Number of panes', 1),
                        ['key' => 'sides', 'label' => 'Sides', 'type' => 'select', 'default' => 2, 'customer' => true,
                            'options' => [['label' => 'Inside only', 'value' => 1], ['label' => 'Both sides', 'value' => 2]]]],
                    'constants' => [], 'area_formula' => 'height * width * panes * sides',
                    'coatings' => ['Glass protection'], 'default' => ['Glass protection', 'Glass', 'Plain'],
                ],
                [
                    'name' => 'Glass edge sealing', 'description' => 'Exposed glass edges, per running foot.', 'measure_unit' => 'rft',
                    'fields' => [T::number('length', 'Edge length (running feet)')],
                    'constants' => [], 'area_formula' => 'length', 'price_formula' => 'area * rate',
                    'uses_coating' => false, 'base_rate' => 15, 'apply_min' => false,
                ],
            ]],
            ['Wood', '🪵', 'Tables, doors and frames: water rings and stains wipe off.', [
                [
                    'name' => 'Wooden table top', 'description' => 'Dining, coffee or study table.',
                    'fields' => [T::dimension('length', 'Length'), T::dimension('width', 'Width')],
                    'constants' => [], 'area_formula' => 'length * width',
                    'coatings' => ['Wood protection'], 'default' => ['Wood protection', 'Wood', 'Plain'],
                ],
                [
                    'name' => 'Wooden door', 'description' => 'Both faces of one door.',
                    'fields' => [T::dimension('height', 'Height', 7), T::dimension('width', 'Width', 3)],
                    'constants' => [['key' => 'faces', 'label' => 'Faces coated', 'value' => 2]], 'area_formula' => 'height * width * faces',
                    'coatings' => ['Wood protection'], 'default' => ['Wood protection', 'Wood', 'Plain'],
                ],
                [
                    'name' => 'Wooden frame / border', 'description' => 'Frames, beading and borders per running foot.', 'measure_unit' => 'rft',
                    'fields' => [T::number('length', 'Length (running feet)')],
                    'constants' => [], 'area_formula' => 'length', 'price_formula' => 'area * rate',
                    'uses_coating' => false, 'base_rate' => 20, 'apply_min' => false,
                ],
            ]],
            ['Stone & marble', '🪨', 'Countertops, table tops and floors sealed against stains.', [
                [
                    'name' => 'Countertop / stone table top', 'description' => 'Kitchen counters, vanity and table tops.',
                    'fields' => [T::dimension('length', 'Length'), T::dimension('width', 'Width')],
                    'constants' => [], 'area_formula' => 'length * width',
                    'coatings' => ['Stone protection'], 'default' => ['Stone protection', 'Marble', 'Plain'],
                ],
                [
                    'name' => 'Stone floor', 'description' => 'Floor area. 15% off above 1,000 sq ft.',
                    'fields' => [T::dimension('length', 'Room length', 12), T::dimension('width', 'Room width', 10)],
                    'constants' => [], 'area_formula' => 'length * width',
                    'price_formula' => 'area * rate * material * design * if(area > 1000, 0.85, 1)',
                    'coatings' => ['Stone protection'], 'default' => ['Stone protection', 'Granite / quartz', 'Plain'],
                ],
            ]],
            ['Walls', '🧱', 'Washable, stain-proof walls for kids’ rooms, hallways and offices.', [
                [
                    'name' => 'Wall coating', 'description' => 'Height × width minus doors and windows. 10% off above 500 sq ft.',
                    'fields' => [T::dimension('height', 'Wall height', 10), T::dimension('width', 'Wall width', 12),
                        ['key' => 'openings', 'label' => 'Doors & windows (sq ft)', 'type' => 'number', 'default' => 0, 'min' => 0, 'customer' => true,
                            'help' => 'Total area to leave out, e.g. a door is about 21 sq ft.']],
                    'constants' => [], 'area_formula' => 'max(0, height * width - openings)',
                    'price_formula' => 'area * rate * material * design * if(area > 500, 0.9, 1)',
                    'coatings' => ['Wall protection'], 'default' => ['Wall protection', 'Painted wall', 'Plain'],
                ],
            ]],
            ['Curtains & rugs', '🧶', 'Rugs, carpets and curtains that shrug off spills and dust.', [
                [
                    'name' => 'Rug / carpet', 'description' => 'Top surface of a rug or carpet.',
                    'fields' => [T::dimension('length', 'Length', 6), T::dimension('width', 'Width', 4)],
                    'constants' => [], 'area_formula' => 'length * width',
                    'coatings' => ['Fabric protection'], 'default' => ['Fabric protection', 'Polyester / blend', 'Plain'],
                ],
                [
                    'name' => 'Curtains', 'description' => 'Both faces of each panel.',
                    'fields' => [T::counter('panels', 'Panels', 2), T::dimension('panel_width', 'Panel width', 4), T::dimension('drop', 'Drop (height)', 8)],
                    'constants' => [['key' => 'faces', 'label' => 'Faces coated', 'value' => 2]],
                    'area_formula' => 'panels * panel_width * drop * faces',
                    'coatings' => ['Fabric protection'], 'default' => ['Fabric protection', 'Polyester / blend', 'Plain'],
                ],
            ]],
        ];

        $definer = new ProductDefinition;
        foreach ($catalog as $i => [$serviceName, $icon, $summary, $products]) {
            $service = Service::create([
                'name' => $serviceName, 'slug' => Str::slug($serviceName), 'icon' => $icon, 'summary' => $summary, 'sort' => ($i + 1) * 10,
            ]);

            foreach ($products as $j => $p) {
                $def = $definer->normalise([
                    'measure_unit' => $p['measure_unit'] ?? 'sq ft',
                    'fields' => $p['fields'],
                    'constants' => $p['constants'],
                    'area_formula' => $p['area_formula'],
                    'price_formula' => $p['price_formula'] ?? $areaPrice,
                    'uses_coating' => $p['uses_coating'] ?? true,
                    'base_rate' => $p['base_rate'] ?? 0,
                    'apply_min' => $p['apply_min'] ?? true,
                    'min_charge' => $p['min_charge'] ?? null,
                ]);
                if ($definer->fails()) {
                    throw new RuntimeException($p['name'].': '.implode(' ', $definer->errors));
                }

                [$dc, $dm, $dd] = $p['default'] ?? [null, null, null];
                $product = Product::create($def + [
                    'service_id' => $service->id,
                    'name' => $p['name'],
                    'slug' => Str::slug($p['name']),
                    'description' => $p['description'],
                    'default_coating_id' => $dc ? $coating($dc) : null,
                    'default_material_id' => $dm ? $material($dm) : null,
                    'default_design_id' => $dd ? $design($dd) : null,
                    'sort' => ($j + 1) * 10,
                ]);
                $product->coatings()->sync(collect($p['coatings'] ?? [])->map($coating));
            }
        }
    }
}
