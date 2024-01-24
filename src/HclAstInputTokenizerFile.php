<?php

declare(strict_types=1);

class HclAstInputTokenizerFile extends HclAstInputTokenizerAbstract
{
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
}
