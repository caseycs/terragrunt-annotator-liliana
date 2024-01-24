<?php

declare(strict_types=1);

class HclAstInputTokenizerString extends HclAstInputTokenizerAbstract
{
    private bool $expression = false;

    protected function readNext(): ?array
    {
        if ($this->input->eof()) {
            return null;
        }

        if ($this->expression && $this->input->peek() === '}') {
            $this->input->next();
            $this->expression = false;
        }

        if ($this->input->peek() === '$' && $this->input->peekNextOnMatch('{')) {
            $this->input->next();
            if ($this->expression) {
                $this->input->croak('Inherited inline expression not allowerd');
            }
            $this->expression = true;
        }

        $c = $this->input->peek();

        if ($this->expression) {
            if ($c === '"') {
                return $this->readString();
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

        return [
            'type' => 'str',
            'value' => $this->readWhile(fn ($s) => $s !== '$' && !$this->input->peekNextEquals('{')),
        ];
    }
}
