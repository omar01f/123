<?php
// Safe expression evaluator (no eval): + - * / ^ % ( ) sqrt
class Calc {
    private string $s; private int $i = 0;
    static function evaluate(string $src): float {
        $s = preg_replace('/\s+/', '', str_replace(['×', '÷', '√'], ['*', '/', 'sqrt'], $src));
        if ($s === '' || strlen($s) > 200) throw new Exception('Invalid expression');
        $c = new self; $c->s = $s; return $c->run();
    }
    private function pk(): string { return $this->s[$this->i] ?? ''; }
    private function run(): float {
        $v = $this->expr();
        if ($this->i < strlen($this->s)) throw new Exception('Invalid expression');
        if (!is_finite($v)) throw new Exception('Result out of range');
        return (float) sprintf('%.14G', $v);
    }
    private function expr(): float { $v = $this->term(); while ($this->pk() === '+' || $this->pk() === '-') { $o = $this->s[$this->i++]; $r = $this->term(); $v = $o === '+' ? $v + $r : $v - $r; } return $v; }
    private function term(): float {
        $v = $this->unary();
        while ($this->pk() === '*' || $this->pk() === '/') { $o = $this->s[$this->i++]; $r = $this->unary(); if ($o === '/' && $r == 0) throw new Exception('Division by zero'); $v = $o === '*' ? $v * $r : $v / $r; }
        return $v;
    }
    private function unary(): float { $c = $this->pk(); if ($c === '-') { $this->i++; return -$this->unary(); } if ($c === '+') { $this->i++; return $this->unary(); } return $this->power(); }
    private function power(): float { $b = $this->post(); if ($this->pk() === '^') { $this->i++; return (float) pow($b, $this->unary()); } return $b; }
    private function post(): float { $v = $this->atom(); while ($this->pk() === '%') { $this->i++; $v /= 100; } return $v; }
    private function close(): void { if ($this->pk() !== ')') throw new Exception('Missing )'); $this->i++; }
    private function atom(): float {
        if ($this->pk() === '(') { $this->i++; $v = $this->expr(); $this->close(); return $v; }
        if (substr($this->s, $this->i, 4) === 'sqrt') {
            $this->i += 4;
            if ($this->pk() === '(') { $this->i++; $v = $this->expr(); $this->close(); } else $v = $this->atom();
            if ($v < 0) throw new Exception('Square root of a negative number');
            return sqrt($v);
        }
        if (!preg_match('/^(\d+\.?\d*|\.\d+)/', substr($this->s, $this->i), $m)) throw new Exception('Invalid expression');
        $this->i += strlen($m[0]); return (float) $m[0];
    }
}
