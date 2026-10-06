<?php

namespace App\Services\Catalog;

use App\Services\Pricing\Formula;
use App\Services\Pricing\FormulaError;
use App\Services\Pricing\PricingService;

/**
 * Validates and normalises a product definition coming from the admin's
 * product designer, so that whatever the admin builds can always be priced.
 */
class ProductDefinition
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,30}$/';

    /** @var array<string, string> */
    public array $errors = [];

    public function normalise(array $in): array
    {
        $this->errors = [];
        $keys = [];

        $fields = [];
        foreach (array_values($in['fields'] ?? []) as $i => $f) {
            $n = $i + 1;
            $key = trim((string) ($f['key'] ?? ''));
            $label = trim((string) ($f['label'] ?? ''));
            $type = $f['type'] ?? 'number';
            $this->checkKey($key, "fields.$i.key", "Input $n", $keys);
            if ($label === '') {
                $this->errors["fields.$i.label"] = "Input $n: give it a label.";
            }
            if (! in_array($type, PricingService::FIELD_TYPES, true)) {
                $this->errors["fields.$i.type"] = "Input $n: unknown type.";
                $type = 'number';
            }

            $field = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'default' => $this->number($f['default'] ?? null),
                'help' => trim((string) ($f['help'] ?? '')) ?: null,
                'customer' => (bool) ($f['customer'] ?? true),
            ];
            foreach (['min', 'max', 'step'] as $opt) {
                if (($v = $this->number($f[$opt] ?? null)) !== null) {
                    $field[$opt] = $v;
                }
            }
            if ($type === 'dimension') {
                $unit = $f['unit'] ?? 'ft';
                $field['unit'] = isset(PricingService::UNIT_TO_FT[$unit]) ? $unit : 'ft';
            }
            if ($type === 'select') {
                $options = [];
                foreach (array_values($f['options'] ?? []) as $o) {
                    $ol = trim((string) ($o['label'] ?? ''));
                    $ov = $this->number($o['value'] ?? null);
                    if ($ol === '' && $ov === null) {
                        continue;
                    }
                    if ($ol === '' || $ov === null) {
                        $this->errors["fields.$i.options"] = "Input $n: every choice needs a label and a number.";

                        continue;
                    }
                    $options[] = ['label' => $ol, 'value' => $ov];
                }
                if (! $options) {
                    $this->errors["fields.$i.options"] ??= "Input $n: add at least one choice.";
                }
                $field['options'] = $options;
                if ($field['default'] === null && $options) {
                    $field['default'] = $options[0]['value'];
                }
            }
            $fields[] = $field;
        }

        $constants = [];
        foreach (array_values($in['constants'] ?? []) as $i => $c) {
            $n = $i + 1;
            $key = trim((string) ($c['key'] ?? ''));
            $this->checkKey($key, "constants.$i.key", "Constant $n", $keys);
            $value = $this->number($c['value'] ?? null);
            if ($value === null) {
                $this->errors["constants.$i.value"] = "Constant $n: enter a number.";
            }
            $constants[] = [
                'key' => $key,
                'label' => trim((string) ($c['label'] ?? '')) ?: $key,
                'value' => $value ?? 0,
            ];
        }

        $area = trim((string) ($in['area_formula'] ?? ''));
        $price = trim((string) ($in['price_formula'] ?? '')) ?: PricingService::DEFAULT_PRICE_FORMULA;
        $this->checkFormula($area, 'area_formula', 'Area formula', $keys);
        $this->checkFormula($price, 'price_formula', 'Price formula', array_merge($keys, array_diff(PricingService::RESERVED, ['qty'])));

        $def = [
            'measure_unit' => trim((string) ($in['measure_unit'] ?? '')) ?: 'sq ft',
            'fields' => $fields,
            'constants' => $constants,
            'area_formula' => $area,
            'price_formula' => $price,
            'uses_coating' => (bool) ($in['uses_coating'] ?? true),
            'base_rate' => (float) ($this->number($in['base_rate'] ?? null) ?? 0),
            'apply_min' => (bool) ($in['apply_min'] ?? true),
            'min_charge' => $this->number($in['min_charge'] ?? null),
        ];

        // Dry run with every default: the product must price without an error.
        if (! $this->errors) {
            $r = PricingService::line([
                'product' => $def, 'qty' => 1, 'inputs' => [],
                'coating_rate' => 1, 'addon_rate' => 0, 'material_factor' => 1, 'design_factor' => 1,
            ], ['min_item' => 0]);
            if ($r['error']) {
                $this->errors['area_formula'] = 'With the default values the formula fails: '.$r['error'];
            }
        }

        return $def;
    }

    public function fails(): bool
    {
        return (bool) $this->errors;
    }

    private function checkKey(string $key, string $path, string $what, array &$keys): void
    {
        if (! preg_match(self::KEY_PATTERN, $key)) {
            $this->errors[$path] = "$what: the formula name must start with a letter and use only a–z, 0–9 and _ (e.g. seat_sqft).";
        } elseif (in_array($key, PricingService::RESERVED, true) || in_array($key, Formula::FUNCTIONS, true)) {
            $this->errors[$path] = "$what: \"$key\" is a reserved name.";
        } elseif (in_array($key, $keys, true)) {
            $this->errors[$path] = "$what: \"$key\" is used twice.";
        }
        $keys[] = $key;
    }

    private function checkFormula(string $formula, string $path, string $what, array $allowed): void
    {
        if ($formula === '') {
            $this->errors[$path] = "$what is required.";

            return;
        }
        try {
            $unknown = array_diff(Formula::variables($formula), $allowed);
            if ($unknown) {
                $this->errors[$path] = "$what uses unknown name(s): ".implode(', ', $unknown).'.';
            }
        } catch (FormulaError $e) {
            $this->errors[$path] = "$what: ".$e->getMessage();
        }
    }

    private function number($v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }
}
