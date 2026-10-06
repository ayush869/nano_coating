<?php

namespace Tests\Unit;

use App\Services\Pricing\PricingService as P;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private static function fixtures(): array
    {
        static $data;

        return $data ??= json_decode(file_get_contents(__DIR__.'/../fixtures/pricing-cases.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    private static function lineInput(string $name): array
    {
        foreach (self::fixtures()['line_cases'] as $case) {
            if ($case['name'] === $name) {
                return $case['line'] + ['product' => self::fixtures()['products'][$case['product']]];
            }
        }
        throw new \RuntimeException("No line case [$name]");
    }

    public static function lineCases(): iterable
    {
        foreach (self::fixtures()['line_cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    public static function quoteCases(): iterable
    {
        foreach (self::fixtures()['quote_cases'] as $case) {
            yield $case['name'] => [$case];
        }
    }

    #[Test]
    #[DataProvider('lineCases')]
    public function line_fixture(array $case): void
    {
        $r = P::line(self::lineInput($case['name']), $case['rules'] ?? self::fixtures()['rules']);
        $e = $case['expected'];

        $this->assertEqualsWithDelta($e['area'], $r['area'], 1e-6, 'area');
        $this->assertSame((float) $e['raw_amount'], $r['raw_amount'], 'raw_amount');
        $this->assertSame($e['min_applied'], $r['min_applied'], 'min_applied');
        $this->assertSame((float) $e['amount'], $r['amount'], 'amount');
        $this->assertSame($e['area_text'], $r['area_text'], 'area_text');
        $this->assertSame($e['formula'], $r['formula'], 'formula');
        $this->assertSame($e['error'], $r['error'], 'error');
    }

    #[Test]
    #[DataProvider('quoteCases')]
    public function quote_fixture(array $case): void
    {
        $lines = [];
        foreach ($case['lines'] as $i => $name) {
            $line = self::lineInput($name);
            if (isset($case['line_qty_override'][$i])) {
                $line['qty'] = $case['line_qty_override'][$i];
            }
            $lines[] = $line;
        }

        $q = P::quote($lines, $case['options'], $case['rules'] ?? self::fixtures()['rules']);
        $e = $case['expected'];

        $this->assertSame(array_map('floatval', $e['line_amounts']), array_column($q['lines'], 'amount'));
        foreach (['subtotal', 'discount_amount', 'visit_charge', 'taxable_before_min', 'min_job_adjustment',
            'taxable', 'cgst', 'sgst', 'igst', 'gst_total', 'total'] as $key) {
            $this->assertSame((float) $e[$key], $q[$key], $key);
        }
        $this->assertSame($e['min_job_applied'], $q['min_job_applied']);
        if (isset($e['money_total'])) {
            $this->assertSame($e['money_total'], P::money($q['total']));
        }
    }

    #[Test]
    public function worked_sofa_example_equals_1422(): void
    {
        $r = P::line(self::lineInput('worked sofa example'), ['min_item' => 500]);

        $this->assertSame(39.5, $r['area']);
        $this->assertSame('₹1,422.00', P::money($r['amount']));
    }

    #[Test]
    public function unit_conversions_for_dimension_fields(): void
    {
        $product = self::fixtures()['products']['glass_top'];
        $sq = fn ($l, $w, $u) => P::line(['product' => $product, 'qty' => 1, 'coating_rate' => 1,
            'inputs' => ['length' => $l, 'length__unit' => $u, 'width' => $w, 'width__unit' => $u]], ['min_item' => 0])['area'];

        $this->assertEqualsWithDelta(1.0, $sq(12, 12, 'in'), 1e-9);
        $this->assertEqualsWithDelta(10.7639104, $sq(1, 1, 'm'), 1e-6);
        $this->assertEqualsWithDelta(10.7639104, $sq(100, 100, 'cm'), 1e-6);
        $this->assertEqualsWithDelta(1.0, $sq(1, 1, 'yd'), 1e-9, 'unknown units fall back to feet');
    }

    #[Test]
    public function percent_and_flat_discount_and_gst_modes(): void
    {
        $line = self::lineInput('worked sofa example');
        $rules = ['gst_pct' => 18, 'min_item' => 0, 'min_job' => 0];

        $pct = P::quote([$line], ['discount_type' => 'percent', 'discount_value' => 10], $rules);
        $this->assertSame([142.2, 1279.8], [$pct['discount_amount'], $pct['taxable']]);

        $flat = P::quote([$line], ['discount_type' => 'flat', 'discount_value' => 222], $rules);
        $this->assertSame([222.0, 1200.0], [$flat['discount_amount'], $flat['taxable']]);

        $intra = P::quote([$line], ['intra_state' => true], $rules);
        $this->assertSame([127.98, 127.98, 0.0, 1677.96], [$intra['cgst'], $intra['sgst'], $intra['igst'], $intra['total']]);

        $inter = P::quote([$line], ['intra_state' => false], $rules);
        $this->assertSame([0.0, 0.0, 255.96, 1677.96], [$inter['cgst'], $inter['sgst'], $inter['igst'], $inter['total']]);
    }

    #[Test]
    public function advance_and_money_format(): void
    {
        $this->assertSame(1536.15, P::advance(3072.3, 50));
        $this->assertSame(921.69, P::advance(3072.3, 30));
        $this->assertSame('₹0.00', P::money(0));
        $this->assertSame('₹1,00,000.00', P::money(100000));
        $this->assertSame('₹1,23,45,678.90', P::money(12345678.9));
        $this->assertSame('₹18', P::money(18, false));
        $this->assertSame('₹18.50', P::money(18.5, false));
    }
}
