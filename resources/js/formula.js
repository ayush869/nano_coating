/**
 * Safe formula engine — line-for-line port of app/Services/Pricing/Formula.php.
 * Both are tested against tests/fixtures/formula-cases.json.
 */

export class FormulaError extends Error {}

export const FUNCTIONS = ['min', 'max', 'round', 'ceil', 'floor', 'abs', 'sqrt', 'if'];

const BINARY = {
    '||': 1, '&&': 2, '==': 3, '!=': 3,
    '<': 4, '<=': 4, '>': 4, '>=': 4,
    '+': 5, '-': 5, '*': 6, '/': 6, '^': 8,
};
const PREFIX_BP = 7;
const cache = new Map();

function tokenize(src) {
    const tokens = [];
    let i = 0;
    while (i < src.length) {
        const c = src[i];
        if (/\s/.test(c)) { i++; continue; }
        if (/[0-9]/.test(c) || (c === '.' && /[0-9]/.test(src[i + 1] ?? ''))) {
            const m = /^(\d+\.?\d*|\.\d+)/.exec(src.slice(i));
            tokens.push({ k: 'num', v: m[1] });
            i += m[1].length;
            continue;
        }
        if (/[A-Za-z_]/.test(c)) {
            const m = /^[A-Za-z_][A-Za-z0-9_]*/.exec(src.slice(i));
            tokens.push({ k: 'id', v: m[0] });
            i += m[0].length;
            continue;
        }
        const two = src.slice(i, i + 2);
        if (['<=', '>=', '==', '!=', '&&', '||'].includes(two)) {
            tokens.push({ k: 'op', v: two });
            i += 2;
            continue;
        }
        if ('+-*/^<>!(),'.includes(c)) {
            tokens.push({ k: 'op', v: c });
            i++;
            continue;
        }
        throw new FormulaError(`Unexpected character "${c}".`);
    }
    return tokens;
}

function checkArity(name, n) {
    let ok;
    if (name === 'min' || name === 'max') ok = n >= 1;
    else if (name === 'round') ok = n === 1 || n === 2;
    else if (name === 'if') ok = n === 3;
    else ok = n === 1;
    if (!ok) throw new FormulaError(`Wrong number of arguments for ${name}().`);
}

class Parser {
    constructor(tokens) { this.tokens = tokens; this.pos = 0; }
    peek() { return this.tokens[this.pos] ?? null; }
    expect(v) {
        const t = this.peek();
        if (!t || t.v !== v) throw new FormulaError(`Expected "${v}"` + (t ? ` but found "${t.v}".` : ' at the end.'));
        this.pos++;
    }
    expr(minBp) {
        let left = this.prefix();
        let t;
        while ((t = this.peek()) && t.k === 'op' && t.v in BINARY) {
            const bp = BINARY[t.v];
            if (bp <= minBp) break;
            this.pos++;
            const right = this.expr(t.v === '^' ? bp - 1 : bp);
            left = { t: 'bin', op: t.v, l: left, r: right };
        }
        return left;
    }
    prefix() {
        const t = this.peek();
        if (!t) throw new FormulaError('Formula ends unexpectedly.');
        this.pos++;
        if (t.k === 'num') return { t: 'num', v: parseFloat(t.v) };
        if (t.k === 'id') {
            const next = this.peek();
            if (next && next.v === '(') {
                const name = t.v.toLowerCase();
                if (!FUNCTIONS.includes(name)) throw new FormulaError(`Unknown function "${t.v}".`);
                this.pos++;
                const args = [];
                if ((this.peek()?.v ?? null) !== ')') {
                    args.push(this.expr(0));
                    while ((this.peek()?.v ?? null) === ',') {
                        this.pos++;
                        args.push(this.expr(0));
                    }
                }
                this.expect(')');
                checkArity(name, args.length);
                return { t: 'call', n: name, args };
            }
            return { t: 'var', n: t.v };
        }
        if (t.v === '(') {
            const e = this.expr(0);
            this.expect(')');
            return { t: 'grp', e };
        }
        if (t.v === '-' || t.v === '!' || t.v === '+') {
            const a = this.expr(PREFIX_BP);
            return t.v === '+' ? a : { t: 'un', op: t.v, a };
        }
        throw new FormulaError(`Unexpected "${t.v}".`);
    }
}

export function parse(src) {
    if (cache.has(src)) return cache.get(src);
    const p = new Parser(tokenize(String(src)));
    if (!p.tokens.length) throw new FormulaError('Formula is empty.');
    const ast = p.expr(0);
    if (p.pos < p.tokens.length) throw new FormulaError(`Unexpected "${p.tokens[p.pos].v}".`);
    cache.set(src, ast);
    return ast;
}

export function normalise(x) {
    return Number(x.toPrecision(12));
}

export function roundTo(x, decimals) {
    decimals = Math.max(0, Math.min(6, Math.trunc(decimals)));
    const f = 10 ** decimals;
    const r = Math.floor(normalise(Math.abs(x) * f) + 0.5) / f;
    return x < 0 ? -r : r;
}

export function fmt(x) {
    let s = roundTo(x, 4).toFixed(4);
    s = s.replace(/0+$/, '').replace(/\.$/, '');
    return s === '-0' ? '0' : s;
}

function evalNode(n, vars) {
    switch (n.t) {
        case 'num':
            return n.v;
        case 'var':
            if (!Object.prototype.hasOwnProperty.call(vars, n.n)) throw new FormulaError(`Unknown variable "${n.n}".`);
            return Number(vars[n.n]) || 0;
        case 'grp':
            return evalNode(n.e, vars);
        case 'un': {
            const a = evalNode(n.a, vars);
            return n.op === '-' ? -a : (a === 0 ? 1 : 0);
        }
        case 'bin': {
            const l = evalNode(n.l, vars);
            if (n.op === '&&') return l !== 0 && evalNode(n.r, vars) !== 0 ? 1 : 0;
            if (n.op === '||') return l !== 0 || evalNode(n.r, vars) !== 0 ? 1 : 0;
            const r = evalNode(n.r, vars);
            switch (n.op) {
                case '+': return l + r;
                case '-': return l - r;
                case '*': return l * r;
                case '/':
                    if (r === 0) throw new FormulaError('Division by zero.');
                    return l / r;
                case '^': {
                    const v = l ** r;
                    if (!Number.isFinite(v)) throw new FormulaError('Result is too large.');
                    return v;
                }
                case '<': return l < r ? 1 : 0;
                case '<=': return l <= r ? 1 : 0;
                case '>': return l > r ? 1 : 0;
                case '>=': return l >= r ? 1 : 0;
                case '==': return Math.abs(l - r) < 1e-9 ? 1 : 0;
                case '!=': return Math.abs(l - r) < 1e-9 ? 0 : 1;
            }
            break;
        }
        case 'call': {
            if (n.n === 'if') {
                return evalNode(n.args[0], vars) !== 0 ? evalNode(n.args[1], vars) : evalNode(n.args[2], vars);
            }
            const a = n.args.map((x) => evalNode(x, vars));
            switch (n.n) {
                case 'min': return Math.min(...a);
                case 'max': return Math.max(...a);
                case 'round': return roundTo(a[0], a[1] ?? 0);
                case 'ceil': return Math.ceil(normalise(a[0]));
                case 'floor': return Math.floor(normalise(a[0]));
                case 'abs': return Math.abs(a[0]);
                case 'sqrt':
                    if (a[0] < 0) throw new FormulaError('Square root of a negative number.');
                    return Math.sqrt(a[0]);
            }
        }
    }
    throw new FormulaError('Invalid formula.');
}

export function evaluate(src, vars) {
    return evalNode(parse(src), vars);
}

export function variables(src) {
    const out = new Set();
    const walk = (n) => {
        if (n.t === 'var') out.add(n.n);
        else if (n.t === 'grp') walk(n.e);
        else if (n.t === 'un') walk(n.a);
        else if (n.t === 'bin') { walk(n.l); walk(n.r); }
        else if (n.t === 'call') n.args.forEach(walk);
    };
    walk(parse(src));
    return [...out].sort();
}

function flattenSum(n, terms) {
    if (n.t === 'bin' && n.op === '+') {
        flattenSum(n.l, terms);
        flattenSum(n.r, terms);
        return;
    }
    terms.push(n);
}

function isZero(n, vars) {
    try {
        return evalNode(n, vars) === 0;
    } catch (e) {
        if (e instanceof FormulaError) return false;
        throw e;
    }
}

function printNode(n, vars, display) {
    switch (n.t) {
        case 'num': return fmt(n.v);
        case 'var':
            if (Object.prototype.hasOwnProperty.call(display, n.n)) return display[n.n];
            return Object.prototype.hasOwnProperty.call(vars, n.n) ? fmt(Number(vars[n.n]) || 0) : n.n;
        case 'grp': return '(' + printNode(n.e, vars, display) + ')';
        case 'un': return (n.op === '-' ? '-' : 'not ') + printNode(n.a, vars, display);
        case 'call': return n.n + '(' + n.args.map((a) => printNode(a, vars, display)).join(', ') + ')';
        case 'bin': {
            if (n.op === '+') {
                const terms = [];
                flattenSum(n, terms);
                const kept = terms.filter((t) => t.t === 'num' || !isZero(t, vars));
                if (!kept.length) return '0';
                return kept.map((t) => printNode(t, vars, display)).join(' + ');
            }
            const sym = { '*': '×', '/': '÷' }[n.op] ?? n.op;
            return printNode(n.l, vars, display) + ' ' + sym + ' ' + printNode(n.r, vars, display);
        }
    }
    return '';
}

export function explain(src, vars, display = {}) {
    return printNode(parse(src), vars, display);
}
