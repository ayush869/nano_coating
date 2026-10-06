<?php

namespace App\Services\Pricing;

/**
 * Pure pricing engine. No database, no framework calls.
 *
 * A product (defined by the admin) has input fields, named constants, an
 * area formula (sq ft, rft, pieces… whatever its unit is) and a price
 * formula. resources/js/pricing.js is a line-for-line port used for live
 * estimates; both are tested against tests/fixtures/pricing-cases.json.
 *
 *   area        = area_formula(fields, constants)            (never below 0)
 *   rate        = coating + add-on   (or the product's base rate)
 *   unit price  = price_formula(area, rate, material, design, fields, constants)
 *   line amount = max(unit price × qty, minimum charge)
 *
 * Money is rounded to 2 decimals only at line and total level.
 */
final class PricingService
{
    /** Feet per unit of measure, for "dimension" fields. */
    public const UNIT_TO_FT = [
        'ft' => 1.0,
        'in' => 1 / 12,
        'm' => 1 / 0.3048,
        'cm' => 1 / 30.48,
    ];

    public const FIELD_TYPES = ['number', 'counter', 'dimension', 'select'];

    /** Names available in price formulas that fields and constants may not use. */
    public const RESERVED = ['area', 'rate', 'coating', 'addon', 'material', 'design', 'base_rate', 'qty'];

    public const DEFAULT_PRICE_FORMULA = 'area * rate * material * design';

    /**
     * Resolve field inputs to formula variables.
     *
     * @return array{0: array<string, float>, 1: array<string, string>} [vars, display overrides]
     */
    public static function fieldVars(array $product, array $inputs): array
    {
        $vars = [];
        $display = [];
        foreach ($product['fields'] ?? [] as $field) {
            $key = $field['key'];
            $raw = $inputs[$key] ?? null;
            $default = $field['default'] ?? null;

            switch ($field['type'] ?? 'number') {
                case 'dimension':
                    $unit = $inputs[$key.'__unit'] ?? ($field['unit'] ?? 'ft');
                    if (! isset(self::UNIT_TO_FT[$unit])) {
                        $unit = 'ft';
                    }
                    $value = self::toFloat($raw, (float) ($default ?? 0));
                    $vars[$key] = $value * self::UNIT_TO_FT[$unit];
                    $display[$key] = Formula::fmt($value).' '.$unit;
                    break;

                case 'select':
                    $options = $field['options'] ?? [];
                    $fallback = $default ?? ($options[0]['value'] ?? 0);
                    $vars[$key] = self::toFloat($raw, (float) $fallback);
                    break;

                default: // number, counter
                    $vars[$key] = self::toFloat($raw, (float) ($default ?? 0));
            }
        }
        foreach ($product['constants'] ?? [] as $c) {
            $vars[$c['key']] = (float) $c['value'];
        }

        return [$vars, $display];
    }

    /**
     * Price one line.
     *
     * @param  array  $line  product, inputs, qty, coating_rate, addon_rate, material_factor, design_factor
     * @param  array  $rules  min_item
     */
    public static function line(array $line, array $rules): array
    {
        $product = $line['product'];
        $qty = self::toFloat($line['qty'] ?? null, 1.0);
        $unit = $product['measure_unit'] ?? 'sq ft';

        $coating = (float) ($line['coating_rate'] ?? 0);
        $addon = (float) ($line['addon_rate'] ?? 0);
        $material = (float) ($line['material_factor'] ?? 1);
        $design = (float) ($line['design_factor'] ?? 1);
        $baseRate = (float) ($product['base_rate'] ?? 0);
        $usesCoating = (bool) ($product['uses_coating'] ?? true);
        $rate = $usesCoating ? $coating + $addon : $baseRate;

        try {
            [$vars, $display] = self::fieldVars($product, $line['inputs'] ?? []);

            $areaFormula = (string) ($product['area_formula'] ?? '0');
            $area = max(0.0, Formula::evaluate($areaFormula, $vars));
            $areaExplain = Formula::explain($areaFormula, $vars, $display);
            $areaText = ($areaExplain === Formula::fmt($area) ? '' : $areaExplain.' = ').self::num($area).' '.$unit;

            $priceVars = $vars + [
                'area' => $area, 'rate' => $rate, 'coating' => $coating, 'addon' => $addon,
                'material' => $material, 'design' => $design, 'base_rate' => $baseRate,
            ];
            $priceFormula = (string) (($product['price_formula'] ?? '') ?: self::DEFAULT_PRICE_FORMULA);
            $unitPrice = Formula::evaluate($priceFormula, $priceVars);
            if ($usesCoating && $addon > 0) {
                $display['rate'] = '('.Formula::fmt($coating).' + '.Formula::fmt($addon).')';
            }
            $priceExplain = Formula::explain($priceFormula, $priceVars, $display);
        } catch (FormulaError $e) {
            return [
                'area' => 0.0, 'rate' => $rate, 'unit_price' => 0.0, 'raw_amount' => 0.0,
                'min_applied' => false, 'amount' => 0.0, 'area_text' => '', 'formula' => '',
                'error' => $e->getMessage(),
            ];
        }

        $raw = self::round2($unitPrice * $qty);
        $min = isset($product['min_charge']) && $product['min_charge'] !== null && $product['min_charge'] !== ''
            ? (float) $product['min_charge']
            : (float) ($rules['min_item'] ?? 0);
        $minApplied = ($product['apply_min'] ?? true) && $raw > 0 && $raw < $min;
        $amount = $minApplied ? self::round2($min) : $raw;

        $formula = $priceExplain.' × '.self::num($qty).' = '.self::money($raw);
        if ($minApplied) {
            $formula .= ', raised to minimum item charge '.self::money($amount);
        }

        return [
            'area' => $area,
            'rate' => $rate,
            'unit_price' => $unitPrice,
            'raw_amount' => $raw,
            'min_applied' => $minApplied,
            'amount' => $amount,
            'area_text' => $areaText,
            'formula' => $formula,
            'error' => null,
        ];
    }

    /**
     * Price a whole quote or estimate.
     *
     * @param  array  $options  discount_type (percent|flat|null), discount_value, visit_charge, intra_state (bool)
     * @param  array  $rules  gst_pct, min_item, min_job
     */
    public static function quote(array $lines, array $options, array $rules): array
    {
        $results = [];
        $sum = 0.0;
        foreach ($lines as $line) {
            $r = self::line($line, $rules);
            $results[] = $r;
            $sum += $r['amount'];
        }
        $subtotal = self::round2($sum);

        $discountValue = (float) ($options['discount_value'] ?? 0);
        $discount = match ($options['discount_type'] ?? null) {
            'percent' => self::round2($subtotal * $discountValue / 100),
            'flat' => self::round2($discountValue),
            default => 0.0,
        };
        $discount = max(0.0, min($discount, $subtotal));
        $discountPct = $subtotal > 0 ? $discount / $subtotal * 100 : 0.0;

        $visit = self::round2((float) ($options['visit_charge'] ?? 0));
        $taxableBeforeMin = self::round2($subtotal - $discount + $visit);

        $minJob = (float) ($rules['min_job'] ?? 0);
        $minJobApplied = $taxableBeforeMin > 0 && $taxableBeforeMin < $minJob;
        $taxable = $minJobApplied ? self::round2($minJob) : $taxableBeforeMin;

        $gstPct = (float) ($rules['gst_pct'] ?? 0);
        $intra = (bool) ($options['intra_state'] ?? true);
        $cgst = $sgst = $igst = 0.0;
        if ($intra) {
            $cgst = self::round2($taxable * $gstPct / 2 / 100);
            $sgst = $cgst;
        } else {
            $igst = self::round2($taxable * $gstPct / 100);
        }
        $gstTotal = self::round2($cgst + $sgst + $igst);

        return [
            'lines' => $results,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'discount_pct' => $discountPct,
            'visit_charge' => $visit,
            'taxable_before_min' => $taxableBeforeMin,
            'min_job_applied' => $minJobApplied,
            'min_job_adjustment' => self::round2($taxable - $taxableBeforeMin),
            'taxable' => $taxable,
            'intra_state' => $intra,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => $igst,
            'gst_total' => $gstTotal,
            'total' => self::round2($taxable + $gstTotal),
        ];
    }

    /** Advance amount for a total and percentage. */
    public static function advance(float $total, float $pct): float
    {
        return self::round2($total * $pct / 100);
    }

    public static function round2(float $x): float
    {
        return Formula::roundTo($x, 2);
    }

    /** Plain number, up to 2 decimals, trailing zeros removed (39.5, 1.25, 18). */
    public static function num(float $x): string
    {
        $s = number_format(self::round2($x), 2, '.', '');
        $s = str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;

        return $s === '-0' ? '0' : $s;
    }

    /** Indian-grouped rupee amount: ₹1,00,000.00 (or ₹18 / ₹18.50 when $fixed is false). */
    public static function money(float $x, bool $fixed = true): string
    {
        $x = self::round2($x);
        $neg = $x < 0;
        [$int, $dec] = explode('.', number_format(abs($x), 2, '.', ''));

        if (strlen($int) > 3) {
            $int = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($int, 0, -3)).','.substr($int, -3);
        }

        $out = '₹'.$int;
        if ($fixed || $dec !== '00') {
            $out .= '.'.$dec;
        }

        return ($neg ? '-' : '').$out;
    }

    private static function toFloat($v, float $default): float
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return $default;
        }

        return (float) $v;
    }
}
