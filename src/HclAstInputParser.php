<?php

declare(strict_types=1);

class HclAstInputParser
{
    private const BLOCKS = ['remote_state', 'terraform', 'inputs', 'include', 'locals', 'dependency', 'dependencies', 'generate'];

    private const PRECEDENCE = [
        "=" => 1,
        "==" => 7,
        "!=" => 7,
        "+" => 10,
        "-" => 10,
        "*" => 20,
        "/" => 20,
        "%" => 20,
        "?" => 2,
        ":" => 3,
    ];

    public function __construct(private HclAstInputTokenizer $input)
    {
    }

    public function read()
    {
        $prog = [];
        $this->input->next();
        while (!$this->input->eof()) {
            $prog[] = $this->parseBlocks();
        }
        return ['type' => 'blocks', 'prog' => $prog];
    }

    public function parseBlocks(): ?array
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

    private function maybeOpNext(string $op): bool
    {
        if ($this->input->eof()) {
            return false;
        }

        $item = $this->input->peek();
        if ($item['type'] === 'op' && $item['value'] === $op) {
            $this->input->next();
            return true;
        }
        return false;
    }

    private function maybePuncNext(string $punc): bool
    {
        if ($this->input->eof()) {
            return false;
        }

        $item = $this->input->peek();
        if ($item['type'] === 'punc' && $item['value'] === $punc) {
            $this->input->next();
            return true;
        }
        return false;
    }

    private function maybeStringNext(): ?string
    {
        if ($this->input->eof()) {
            return null;
        }

        $item = $this->input->peek();
        if ($item['type'] === 'str') {
            return $this->returnAndNext($item['value']);
        }
        return null;
    }

    private function expectPuncNext(string $punc): void
    {
        if ($this->isPunc($punc)) {
            $this->input->next();
            return;
        }
        $this->croak('Expected punc: ' . $punc);
    }

    private function parseBlockVarEqValue(): array
    {
        $result = $this->maybeBinary($this->parseAtom(), 0);
        $this->maybePuncNext(',');
        return $result;
    }

    private function expectOpNext(string $op): void
    {
        if ($this->isOp($op)) {
            $this->input->next();
            return;
        }
        $this->croak('Expected op: ' . $op);
    }

    private function expectVarNext(): string
    {
        if ($this->input->peek()['type'] === 'var') {
            return $this->returnAndNext($this->input->peek()['value']);
        }
        $this->croak('Expected variable');
    }

    private function parseAtom(): array
    {
        // raw string
        if ($this->input->peek()['type'] === 'str') {
            return $this->returnAndNext(['type' => 'str', 'value' => $this->input->peek()['value']]);
        }

        // raw number
        if ($this->input->peek()['type'] === 'num') {
            return $this->returnAndNext(['type' => 'num', 'value' => $this->input->peek()['value']]);
        }

        // variable or function call
        if ($this->input->peek()['type'] === 'var') {
            $value = $this->input->peek()['value'];
            $this->input->next();
            if ($this->isPunc('(')) {
                // function call

                // next after open scope
                $this->input->next();

                $args = [];
                while (!$this->isPunc(')')) {
                    $args[] = $this->parseExpression();
                    $this->maybePuncNext(',');
                }

                // next token to process
                $this->expectPuncNext(')');

                return $this->appendPos([
                    'type' => 'functionCall',
                    'name' => $value,
                    'args' => $args,
                    'result' => $this->parseVariableOrFunctionSuffix(),
                ]);
            } else {
                // regular var
                return $this->appendPos([
                    'type' => 'variable',
                    'name' => $value,
                    'result' => $this->parseVariableOrFunctionSuffix(),
                ]);
            }
        }

        // kw
        if ($this->input->peek()['type'] === 'keyword') {
            return $this->returnAndNext(['type' => 'keywork', 'name' => $this->input->peek()['value']]);
        }

        // list
        if ($this->isPunc('[')) {
            $this->input->next();

            $items = [];
            while (!$this->isPunc(']')) {
                $items[] = $this->parseExpression();
                $this->maybePuncNext(',');
            }

            // next token to process
            $this->expectPuncNext(']');

            return $this->appendPos(['type' => 'list', 'items' => $items]);
        }

        // assoc 
        if ($this->isPunc('{')) {
            $value = $this->input->peek()['value'];
            $items = $this->parseBlockVars();
            return $this->appendPos(['type' => 'assoc', 'name' => $value, 'items' => $items]);
        }

        $this->croak('Unexpected');
    }

    private function parseVariableOrFunctionSuffix(): array
    {
        $result = ['type' => 'all'];

        // simplest case
        if (!$this->isPunc('.') && !$this->isPunc('[')) {
            return $result;
        }

        $result['type'] = 'partial';
        $result['partials'] = [];

        while ($this->isPunc('.') || $this->isPunc('[')) {
            if ($this->isPunc('.')) {
                // hash key
                $this->input->next();
                $result['partials'][] = [
                    'type' => 'hashKey',
                    'key' => $this->expectVarNext(),
                ];
            } elseif ($this->isPunc('[')) {
                // list item
                $this->input->next();
                $result['partials'][] = [
                    'type' => 'listKey',
                    'key' => $this->parseExpression(),
                ];

                $this->expectPuncNext(']');
            }
        }
        return $result;
    }

    private function parseExpression(): array
    {
        return $this->maybeBinary($this->parseAtom(), 0);
    }

    private function maybeTernary(array $item): array
    {
        if ($item['type'] === 'binary' && $item['operator'] === '?') {
            // print_r($item['right']);
            if ($item['right']['type'] !== 'binary' || $item['right']['operator'] !== ':') {
                $this->croak('Ternary else not found');
            }
            return [
                'type' => 'ternary',
                'expression' => $item['left'],
                'then' => $item['right']['left'],
                'else' => $item['right']['right'],
            ];
        }
        return $item;
    }

    private function maybeBinary(array $left, int $myPrec, bool $expectElse = false): array
    {
        $item = $this->input->peek();
        if ($item['type'] === 'op') {
            $hisPrec = self::PRECEDENCE[$item['value']];
            if ($hisPrec > $myPrec) {

                // dirty hack to ignore 2+ sequential op (var = -1), as we do not care
                do {
                    $this->input->next();
                    $item2 = $this->input->peek();
                } while ($item2['type'] === 'op'/* && strpos('?:', $item2['value']) === false*/);

                $right = $this->maybeBinary(
                    $this->parseAtom(),
                    $hisPrec,
                    $expectElse
                );

                $result =
                $this->maybeBinary(
                    $this->appendPos($this->maybeTernary([
                        'type' => ($item['value'] === '=') ? 'assign' : 'binary',
                        'operator' =>   $item['value'],
                        'left' => $left,
                        'right' => $right,
                    ])),
                    $myPrec,
                    $expectElse
                );

                return $result;
            }
        }
        return $left;
    }

    private function returnAndNext(mixed $var): mixed
    {
        $this->input->next();
        return $var;
    }

    private function isPunc(string $punc): bool
    {
        $item = $this->input->peek();
        return $item['type'] === 'punc' && $item['value'] === $punc;
    }

    private function isOp(string $op): bool
    {
        $item = $this->input->peek();
        return $item['type'] === 'op' && $item['value'] === $op;
    }

    private function croak(string $msg): void
    {
        $this->input->croak($msg);
    }

    private function appendPos(array $array): array
    {
        // $array['file'] = [
        //     'line' => $this->input->peekPreviousPos()[0],
        //     'col' => $this->input->peekPreviousPos()[1],
        // ];
        return $array;
    }
}
