<?php

declare(strict_types=1);

class HclAstInputParserString extends HclAstInputParserAbstract
{
    public function parse(): array
    {
        $prog = [];
        $this->input->next();
        do {
            $prog[] = $this->maybeBinary($this->parseAtom(), 0);
        } while (!$this->input->eof());
        return $prog;
    }
}
