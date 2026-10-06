<?php

namespace Tests\Unit;

use App\Services\Pricing\Formula;
use App\Services\Pricing\FormulaError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FormulaTest extends TestCase
{
    private static function fixtures(): array
    {
        static $data;

        return $data ??= json_decode(file_get_contents(__DIR__.'/../fixtures/formula-cases.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function evalCases(): iterable
    {
        foreach (self::fixtures()['eval'] as $c) {
            yield $c['f'] => [$c];
        }
    }

    public static function errorCases(): iterable
    {
        foreach (self::fixtures()['errors'] as $c) {
            yield 'error: '.$c['f'] => [$c];
        }
    }

    #[Test]
    #[DataProvider('evalCases')]
    public function evaluates_and_explains(array $c): void
    {
        $this->assertEqualsWithDelta($c['result'], Formula::evaluate($c['f'], $c['vars']), 1e-6);
        $this->assertSame($c['explain'], Formula::explain($c['f'], $c['vars']));
    }

    #[Test]
    #[DataProvider('errorCases')]
    public function reports_errors(array $c): void
    {
        try {
            Formula::evaluate($c['f'], $c['vars'] ?? []);
            $this->fail('Expected FormulaError for '.$c['f']);
        } catch (FormulaError $e) {
            $this->assertSame($c['error'], $e->getMessage());
        }
    }

    #[Test]
    public function explain_with_display_overrides_and_variables(): void
    {
        foreach (self::fixtures()['explain_display'] as $c) {
            $this->assertSame($c['explain'], Formula::explain($c['f'], $c['vars'], $c['display']));
        }
        foreach (self::fixtures()['variables'] as $c) {
            $this->assertSame($c['vars'], Formula::variables($c['f']));
        }
    }

    #[Test]
    public function never_executes_php(): void
    {
        $this->expectException(FormulaError::class);
        Formula::evaluate('system("dir")', []);
    }
}
