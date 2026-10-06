<?php

namespace App\Services\Pricing;

/**
 * Safe arithmetic formula engine for admin-defined product formulas.
 * Never uses eval(). resources/js/formula.js is a line-for-line port; both
 * are tested against tests/fixtures/formula-cases.json.
 *
 * Grammar: numbers, variables, + - * / ^, comparisons (< <= > >= == !=),
 * && || !, parentheses and the functions below. Comparisons and logic
 * return 1 or 0. if(cond, a, b) only evaluates the branch it takes.
 */
final class Formula
{
    public const FUNCTIONS = ['min', 'max', 'round', 'ceil', 'floor', 'abs', 'sqrt', 'if'];

    private const BINARY = [
        '||' => 1, '&&' => 2, '==' => 3, '!=' => 3,
        '<' => 4, '<=' => 4, '>' => 4, '>=' => 4,
        '+' => 5, '-' => 5, '*' => 6, '/' => 6, '^' => 8,
    ];

    private const PREFIX_BP = 7;

    private array $tokens = [];

    private int $pos = 0;

    /** @var array<string, array> */
    private static array $cache = [];

    /** Parse to an AST (cached). Throws FormulaError on bad syntax. */
    public static function parse(string $src): array
    {
        if (isset(self::$cache[$src])) {
            return self::$cache[$src];
        }
        $p = new self;
        $p->tokens = self::tokenize($src);
        $p->pos = 0;
        if (! $p->tokens) {
            throw new FormulaError('Formula is empty.');
        }
        $ast = $p->expr(0);
        if ($p->pos < count($p->tokens)) {
            throw new FormulaError('Unexpected "'.$p->tokens[$p->pos]['v'].'".');
        }

        return self::$cache[$src] = $ast;
    }

    public static function evaluate(string $src, array $vars): float
    {
        return self::evalNode(self::parse($src), $vars);
    }

    /** Variable names used by a formula (sorted, unique). */
    public static function variables(string $src): array
    {
        $out = [];
        $walk = function (array $n) use (&$walk, &$out) {
            switch ($n['t']) {
                case 'var': $out[$n['n']] = true; break;
                case 'grp': $walk($n['e']); break;
                case 'un': $walk($n['a']); break;
                case 'bin': $walk($n['l']); $walk($n['r']); break;
                case 'call': foreach ($n['args'] as $a) {
                    $walk($a);
                } break;
            }
        };
        $walk(self::parse($src));
        $names = array_keys($out);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * The formula with values substituted, for showing customers how a price
     * was reached: "3 × 4.5 + 2 × 5 = …". Terms of a sum that evaluate to 0
     * are left out. $display overrides how a variable is printed.
     */
    public static function explain(string $src, array $vars, array $display = []): string
    {
        return self::printNode(self::parse($src), $vars, $display);
    }

    // ---------------------------------------------------------------- tokenizer

    private static function tokenize(string $src): array
    {
        $tokens = [];
        $i = 0;
        $len = strlen($src);
        while ($i < $len) {
            $c = $src[$i];
            if (ctype_space($c)) {
                $i++;

                continue;
            }
            if (ctype_digit($c) || ($c === '.' && $i + 1 < $len && ctype_digit($src[$i + 1]))) {
                preg_match('/\G(\d+\.?\d*|\.\d+)/', $src, $m, 0, $i);
                $tokens[] = ['k' => 'num', 'v' => $m[1]];
                $i += strlen($m[1]);

                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $src, $m, 0, $i);
                $tokens[] = ['k' => 'id', 'v' => $m[0]];
                $i += strlen($m[0]);

                continue;
            }
            $two = substr($src, $i, 2);
            if (in_array($two, ['<=', '>=', '==', '!=', '&&', '||'], true)) {
                $tokens[] = ['k' => 'op', 'v' => $two];
                $i += 2;

                continue;
            }
            if (str_contains('+-*/^<>!(),', $c)) {
                $tokens[] = ['k' => 'op', 'v' => $c];
                $i++;

                continue;
            }
            throw new FormulaError('Unexpected character "'.$c.'".');
        }

        return $tokens;
    }

    // ---------------------------------------------------------------- parser

    private function peek(): ?array
    {
        return $this->tokens[$this->pos] ?? null;
    }

    private function expect(string $v): void
    {
        $t = $this->peek();
        if (! $t || $t['v'] !== $v) {
            throw new FormulaError('Expected "'.$v.'"'.($t ? ' but found "'.$t['v'].'".' : ' at the end.'));
        }
        $this->pos++;
    }

    private function expr(int $minBp): array
    {
        $left = $this->prefix();
        while (($t = $this->peek()) && $t['k'] === 'op' && isset(self::BINARY[$t['v']])) {
            $bp = self::BINARY[$t['v']];
            if ($bp <= $minBp) {
                break;
            }
            $this->pos++;
            // ^ is right-associative.
            $right = $this->expr($t['v'] === '^' ? $bp - 1 : $bp);
            $left = ['t' => 'bin', 'op' => $t['v'], 'l' => $left, 'r' => $right];
        }

        return $left;
    }

    private function prefix(): array
    {
        $t = $this->peek();
        if (! $t) {
            throw new FormulaError('Formula ends unexpectedly.');
        }
        $this->pos++;

        if ($t['k'] === 'num') {
            return ['t' => 'num', 'v' => (float) $t['v']];
        }
        if ($t['k'] === 'id') {
            $next = $this->peek();
            if ($next && $next['v'] === '(') {
                $name = strtolower($t['v']);
                if (! in_array($name, self::FUNCTIONS, true)) {
                    throw new FormulaError('Unknown function "'.$t['v'].'".');
                }
                $this->pos++;
                $args = [];
                if (($this->peek()['v'] ?? null) !== ')') {
                    $args[] = $this->expr(0);
                    while (($this->peek()['v'] ?? null) === ',') {
                        $this->pos++;
                        $args[] = $this->expr(0);
                    }
                }
                $this->expect(')');
                self::checkArity($name, count($args));

                return ['t' => 'call', 'n' => $name, 'args' => $args];
            }

            return ['t' => 'var', 'n' => $t['v']];
        }
        if ($t['v'] === '(') {
            $e = $this->expr(0);
            $this->expect(')');

            return ['t' => 'grp', 'e' => $e];
        }
        if ($t['v'] === '-' || $t['v'] === '!' || $t['v'] === '+') {
            $a = $this->expr(self::PREFIX_BP);

            return $t['v'] === '+' ? $a : ['t' => 'un', 'op' => $t['v'], 'a' => $a];
        }
        throw new FormulaError('Unexpected "'.$t['v'].'".');
    }

    private static function checkArity(string $name, int $n): void
    {
        $ok = match ($name) {
            'min', 'max' => $n >= 1,
            'round' => $n === 1 || $n === 2,
            'if' => $n === 3,
            default => $n === 1,
        };
        if (! $ok) {
            throw new FormulaError('Wrong number of arguments for '.$name.'().');
        }
    }

    // ---------------------------------------------------------------- evaluation

    private static function evalNode(array $n, array $vars): float
    {
        switch ($n['t']) {
            case 'num':
                return $n['v'];
            case 'var':
                if (! array_key_exists($n['n'], $vars)) {
                    throw new FormulaError('Unknown variable "'.$n['n'].'".');
                }

                return (float) $vars[$n['n']];
            case 'grp':
                return self::evalNode($n['e'], $vars);
            case 'un':
                $a = self::evalNode($n['a'], $vars);

                return $n['op'] === '-' ? -$a : ($a == 0 ? 1.0 : 0.0);
            case 'bin':
                $l = self::evalNode($n['l'], $vars);
                if ($n['op'] === '&&') {
                    return $l != 0 && self::evalNode($n['r'], $vars) != 0 ? 1.0 : 0.0;
                }
                if ($n['op'] === '||') {
                    return $l != 0 || self::evalNode($n['r'], $vars) != 0 ? 1.0 : 0.0;
                }
                $r = self::evalNode($n['r'], $vars);
                switch ($n['op']) {
                    case '+': return $l + $r;
                    case '-': return $l - $r;
                    case '*': return $l * $r;
                    case '/':
                        if ($r == 0) {
                            throw new FormulaError('Division by zero.');
                        }

                        return $l / $r;
                    case '^':
                        $v = $l ** $r;
                        if (! is_finite($v)) {
                            throw new FormulaError('Result is too large.');
                        }

                        return $v;
                    case '<': return $l < $r ? 1.0 : 0.0;
                    case '<=': return $l <= $r ? 1.0 : 0.0;
                    case '>': return $l > $r ? 1.0 : 0.0;
                    case '>=': return $l >= $r ? 1.0 : 0.0;
                    case '==': return abs($l - $r) < 1e-9 ? 1.0 : 0.0;
                    case '!=': return abs($l - $r) < 1e-9 ? 0.0 : 1.0;
                }
                break;
            case 'call':
                if ($n['n'] === 'if') {
                    return self::evalNode($n['args'][0], $vars) != 0
                        ? self::evalNode($n['args'][1], $vars)
                        : self::evalNode($n['args'][2], $vars);
                }
                $a = array_map(fn ($x) => self::evalNode($x, $vars), $n['args']);
                switch ($n['n']) {
                    case 'min': return (float) min($a);
                    case 'max': return (float) max($a);
                    case 'round': return self::roundTo($a[0], (int) ($a[1] ?? 0));
                    case 'ceil': return (float) ceil(self::normalise($a[0]));
                    case 'floor': return (float) floor(self::normalise($a[0]));
                    case 'abs': return abs($a[0]);
                    case 'sqrt':
                        if ($a[0] < 0) {
                            throw new FormulaError('Square root of a negative number.');
                        }

                        return sqrt($a[0]);
                }
        }
        throw new FormulaError('Invalid formula.');
    }

    /** Remove binary noise (0.1 + 0.2 → 0.3) before rounding. Same as formula.js. */
    public static function normalise(float $x): float
    {
        return (float) sprintf('%.12g', $x);
    }

    /** Round half away from zero. */
    public static function roundTo(float $x, int $decimals): float
    {
        $decimals = max(0, min(6, $decimals));
        $f = 10 ** $decimals;
        $r = floor(self::normalise(abs($x) * $f) + 0.5) / $f;

        return $x < 0 ? -$r : $r;
    }

    /** Up to 4 decimals, trailing zeros removed. */
    public static function fmt(float $x): string
    {
        $s = number_format(self::roundTo($x, 4), 4, '.', '');
        $s = rtrim(rtrim($s, '0'), '.');

        return $s === '-0' ? '0' : $s;
    }

    // ---------------------------------------------------------------- explain

    private static function printNode(array $n, array $vars, array $display): string
    {
        switch ($n['t']) {
            case 'num':
                return self::fmt($n['v']);
            case 'var':
                if (isset($display[$n['n']])) {
                    return $display[$n['n']];
                }

                return array_key_exists($n['n'], $vars) ? self::fmt((float) $vars[$n['n']]) : $n['n'];
            case 'grp':
                return '('.self::printNode($n['e'], $vars, $display).')';
            case 'un':
                return ($n['op'] === '-' ? '-' : 'not ').self::printNode($n['a'], $vars, $display);
            case 'call':
                return $n['n'].'('.implode(', ', array_map(fn ($a) => self::printNode($a, $vars, $display), $n['args'])).')';
            case 'bin':
                if ($n['op'] === '+') {
                    $terms = [];
                    self::flattenSum($n, $terms);
                    $kept = array_values(array_filter($terms, fn ($t) => $t['t'] === 'num' || ! self::isZero($t, $vars)));
                    if (! $kept) {
                        return '0';
                    }

                    return implode(' + ', array_map(fn ($t) => self::printNode($t, $vars, $display), $kept));
                }
                $sym = ['*' => '×', '/' => '÷'][$n['op']] ?? $n['op'];

                return self::printNode($n['l'], $vars, $display).' '.$sym.' '.self::printNode($n['r'], $vars, $display);
        }

        return '';
    }

    private static function flattenSum(array $n, array &$terms): void
    {
        if ($n['t'] === 'bin' && $n['op'] === '+') {
            self::flattenSum($n['l'], $terms);
            self::flattenSum($n['r'], $terms);

            return;
        }
        $terms[] = $n;
    }

    private static function isZero(array $n, array $vars): bool
    {
        try {
            return self::evalNode($n, $vars) == 0;
        } catch (FormulaError) {
            return false;
        }
    }
}
