<?php

namespace App\Services\Catalog;

/**
 * Starting points for the product designer. The admin can change anything
 * afterwards: inputs, constants and both formulas.
 */
class ProductTemplates
{
    public static function all(): array
    {
        return [
            'parts' => [
                'label' => 'Built from parts (sofa, sectional)',
                'hint' => 'Count each part; each part has a standard size you can edit.',
                'measure_unit' => 'sq ft',
                'fields' => [
                    self::counter('seats', 'Seats', 3),
                    self::counter('backs', 'Back sections', 3),
                    self::counter('arms', 'Armrests', 2),
                    self::counter('cushions', 'Loose cushions', 0),
                ],
                'constants' => [
                    ['key' => 'seat_sqft', 'label' => 'Seat (sq ft)', 'value' => 4.5],
                    ['key' => 'back_sqft', 'label' => 'Back section (sq ft)', 'value' => 5],
                    ['key' => 'arm_sqft', 'label' => 'Armrest (sq ft)', 'value' => 6],
                    ['key' => 'cushion_sqft', 'label' => 'Cushion (sq ft)', 'value' => 2],
                ],
                'area_formula' => 'seats * seat_sqft + backs * back_sqft + arms * arm_sqft + cushions * cushion_sqft',
                'price_formula' => 'area * rate * material * design',
                'uses_coating' => true,
            ],
            'measured' => [
                'label' => 'Measured surface (length × width)',
                'hint' => 'Table tops, rugs, panels. Sizes in ft, inches, m or cm.',
                'measure_unit' => 'sq ft',
                'fields' => [
                    self::dimension('length', 'Length'),
                    self::dimension('width', 'Width'),
                    ['key' => 'sides', 'label' => 'Sides', 'type' => 'select', 'default' => 1, 'customer' => true,
                        'options' => [['label' => 'One side', 'value' => 1], ['label' => 'Both sides', 'value' => 2]]],
                ],
                'constants' => [],
                'area_formula' => 'length * width * sides',
                'price_formula' => 'area * rate * material * design',
                'uses_coating' => true,
            ],
            'piece' => [
                'label' => 'Fixed-size piece (chair, headboard)',
                'hint' => 'Every piece has the same coated area.',
                'measure_unit' => 'sq ft',
                'fields' => [],
                'constants' => [['key' => 'piece_sqft', 'label' => 'Area of one piece (sq ft)', 'value' => 6]],
                'area_formula' => 'piece_sqft',
                'price_formula' => 'area * rate * material * design',
                'uses_coating' => true,
            ],
            'sizes' => [
                'label' => 'Choose a size (S / M / L)',
                'hint' => 'Customer picks a size; each size has its own area.',
                'measure_unit' => 'sq ft',
                'fields' => [
                    ['key' => 'size', 'label' => 'Size', 'type' => 'select', 'default' => 12, 'customer' => true,
                        'options' => [['label' => 'Small', 'value' => 8], ['label' => 'Medium', 'value' => 12], ['label' => 'Large', 'value' => 16]]],
                ],
                'constants' => [],
                'area_formula' => 'size',
                'price_formula' => 'area * rate * material * design',
                'uses_coating' => true,
            ],
            'running' => [
                'label' => 'Running feet (edges, frames)',
                'hint' => 'Priced per running foot at a fixed rate, no coating choice.',
                'measure_unit' => 'rft',
                'fields' => [['key' => 'length', 'label' => 'Length (running feet)', 'type' => 'number', 'default' => 0, 'min' => 0, 'step' => 0.5, 'customer' => true]],
                'constants' => [],
                'area_formula' => 'length',
                'price_formula' => 'area * rate',
                'uses_coating' => false,
                'base_rate' => 15,
                'apply_min' => false,
            ],
            'wall' => [
                'label' => 'Wall (height × width − openings)',
                'hint' => 'Subtracts doors and windows; includes a sample volume discount.',
                'measure_unit' => 'sq ft',
                'fields' => [
                    self::dimension('height', 'Height', 10),
                    self::dimension('width', 'Width'),
                    ['key' => 'openings', 'label' => 'Doors & windows (sq ft)', 'type' => 'number', 'default' => 0, 'min' => 0, 'customer' => true,
                        'help' => 'Total area to leave out.'],
                ],
                'constants' => [],
                'area_formula' => 'max(0, height * width - openings)',
                'price_formula' => 'area * rate * material * design * if(area > 500, 0.9, 1)',
                'uses_coating' => true,
            ],
            'blank' => [
                'label' => 'Start from scratch',
                'hint' => 'Your own inputs and formulas.',
                'measure_unit' => 'sq ft',
                'fields' => [self::number('quantity_sqft', 'Area (sq ft)')],
                'constants' => [],
                'area_formula' => 'quantity_sqft',
                'price_formula' => 'area * rate * material * design',
                'uses_coating' => true,
            ],
        ];
    }

    public static function counter(string $key, string $label, int $default = 0): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'counter', 'default' => $default, 'min' => 0, 'customer' => true];
    }

    public static function dimension(string $key, string $label, float $default = 0, string $unit = 'ft'): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'dimension', 'default' => $default, 'unit' => $unit, 'min' => 0, 'customer' => true];
    }

    public static function number(string $key, string $label, float $default = 0): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'number', 'default' => $default, 'min' => 0, 'customer' => true];
    }
}
