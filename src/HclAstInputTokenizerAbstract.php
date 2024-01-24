<?php

declare(strict_types=1);

abstract class HclAstInputTokenizerAbstract
{
    protected ?array $current = null;

    public function __construct(protected HclAstInputStream $input)
    {
    }

    public function next(): ?array
    {
        $this->current = $this->readNext();
        return $this->current;
    }

    public function peek(): ?array
    {
        return $this->current;
    }

    public function peekPreviousPos(): array
    {
        return $this->input->previousPos();
    }

    public function eof(): bool
    {
        return $this->input->eof();
    }

    protected function readNext(): ?array
    {
        $this->readWhile([$this, 'isWhitespace']);
        if ($this->input->eof()) {
            return null;
        }
        $c = $this->input->peek();
        if ($c === "#") {
            $this->skipComment();
            return $this->readNext();
        }
        if ($c === '/' && $this->input->peekNextOnMatch('/')) {
            $this->skipComment();
            return $this->readNext();
        }
        if ($c === '"') {
            return $this->readString();
        }
        if ($c === '<' && $this->input->peekNextOnMatch('<')) {
            $this->input->peekNextOnMatch('-'); // support for indented heredocs https://developer.hashicorp.com/terraform/language/expressions/strings#indented-heredocs
            return $this->readMultilineString();
        }
        if ($this->isDigit($c)) {
            return $this->readNumber();
        }
        if ($this->isIdStart($c)) {
            return $this->readIdent();
        }
        if ($this->isPunc($c)) {
            return ['type' => 'punc', 'value' => $this->input->next()];
        }
        if ($this->isOp($c)) {
            return ['type' => 'op', 'value' => $this->readWhile([$this, 'isOp'])];
        }
        $this->input->croak("Can't handle char: " . $c);
    }

    protected function readWhile(callable $fn): string
    {
        $r = "";
        while (!$this->input->eof() && $fn($this->input->peek())) {
            $r .= $this->input->next();
        }
        return $r;
    }

    protected function isWhitespace(string $ch): bool
    {
        return in_array($ch, [' ', "\n", "\t"], true);
    }

    protected function isDigit(string $ch): bool
    {
        return $ch === (string)(int)$ch;
    }

    protected function isId(string $ch): bool
    {
        return $this->isIdStart($ch)
            || strpos('_0123456789-', $ch) !== false;
    }

    protected function isIdStart(string $ch): bool
    {
        return preg_match('/[a-z]/i', $ch) === 1;
    }

    protected function isPunc(string $ch): bool
    {
        return strpos('.,;(){}[]', $ch) !== false;
    }

    protected function isOp(string $ch): bool
    {
        return strpos('+-*/%=&|<>!:?', $ch) !== false;
    }

    protected function skipComment(): void
    {
        $this->readWhile(fn ($s) => $s !== PHP_EOL);
        $this->input->next();
    }

    protected function readString()
    {
        return ['type' => 'str', 'value' => $this->readStringEscaped('"')];
    }

    protected function readStringEscaped($end): string
    {
        $escaped = false;
        $varSubstitution = false;
        $r = "";
        $this->input->next();
        while (!$this->input->eof()) {
            $c = $this->input->next();
            if ($escaped) {
                $r .= $c;
                $escaped = false;
            } elseif ($c === "\\") {
                $escaped = true;
            } elseif ($c === "$" && $this->input->peek() === '{') {
                $r .= '$';
                $varSubstitution = true;
            } elseif ($varSubstitution && $c === "}") {
                $r .= '}';
                $varSubstitution = false;
            } elseif (!$varSubstitution && $c === $end) {
                break;
            } else {
                $r .= $c;
            }
        }
        return $r;
    }

    protected function readMultilineString()
    {
        $this->input->next();
        $end = $this->readIdent()['value'];
        $r = "";
        $this->input->next();
        while (!$this->input->eof()) {
            $c = $this->input->next();
            if (str_ends_with($r, $end)) {
                break;
            } else {
                $r .= $c;
            }
        }
        return ['type' => 'str', 'value' => substr($r, 0, -strlen($end))];
    }

    protected function readNumber()
    {
        $v = $this->readWhile([$this, 'isDigit']);
        return ['type' => 'num', 'value' => (int)$v];
    }

    protected function readIdent()
    {
        $id = $this->readWhile([$this, 'isId']);
        return ['type' => $this->isKeyword($id) ? 'keyword' : 'var', 'value' => $id];
    }

    protected function isKeyword(string $id): bool
    {
        return in_array($id, ['true', 'false'], true);
    }

    public function croak($msg): void
    {
        $this->input->croak($msg . ', current token: ' . json_encode($this->peek()));
    }
}
