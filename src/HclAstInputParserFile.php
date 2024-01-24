<?php

declare(strict_types=1);

class HclAstInputParserFile extends HclAstInputParserAbstract
{
    private const BLOCKS = ['remote_state', 'terraform', 'inputs', 'include', 'locals', 'dependency', 'dependencies', 'generate'];

    public function parse(): array
    {
        $prog = [];
        $this->input->next();
        do {
            $prog[] = $this->parseBlocks();
        } while (!$this->input->eof());
        return ['type' => 'blocks', 'prog' => $prog];
    }

    private function parseBlocks(): ?array
    {
        if ($this->isBlock()) {
            return $this->parseBlock();
        }
        $this->croak('Expected block');
    }

    private function isBlock(): bool
    {
        return $this->input->peek()['type'] === 'var'
            && in_array($this->input->peek()['value'], self::BLOCKS, true);
    }

    private function parseBlock(): array
    {
        $blockType = $this->input->peek()['value'];
        $this->input->next();

        $blockName = null;
        if (!$this->maybeOpNext('=')) {
            $blockName = $this->maybeStringNext();
        }
        $blockBody = $this->parseBlockVars();

        return $this->appendPos([
            'type' => 'block',
            'blockType' => $blockType,
            'blockName' => $blockName,
            'body' => $blockBody,
        ]);
    }

    private function parseBlockVars()
    {
        $this->expectPuncNext('{');
        $result = [];
        while (!$this->input->eof()) {
            if ($this->isPunc('}')) {
                $this->input->next();
                break;
            }
            $result[] = $this->parseBlockVarEqValue();
        }
        return $result;
    }

    private function parseBlockVarEqValue(): array
    {
        $result = $this->parseExpression();
        $this->maybePuncNext(',');
        return $result;
    }

    protected function parseAtom(): array
    {
        // assoc 
        if ($this->isPunc('{')) {
            $value = $this->input->peek()['value'];
            $items = $this->parseBlockVars();
            return $this->appendPos(['type' => 'assoc', 'name' => $value, 'items' => $items]);
        }

        $result = parent::parseAtom();

        // post-process inline strings
        if ($result['type'] === 'str') {
            $s = new HclAstInputStream($result['value']);
            $t = new HclAstInputTokenizerString($s);
            $p = new HclAstInputParserString($t);

            $subitems = $p->parse();

            // if (count($subitems) > 1 && $subitems[0][''])

            $result = [
                'type' => 'strInterpolated',
                'items' => $subitems,
            ];
        }

        return $result;
    }
}
