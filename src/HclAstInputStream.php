<?php

declare(strict_types=1);

class HclAstInputStream
{
    private int $pos = 0;
    private int $line = 1;
    private int $col = 0;

    private array $previousPos = [1, 0];

    public function __construct(private string $hcl)
    {
    }

    public function next(): ?string
    {
        if ($this->eof()) {
            return null;
        }

        $this->previousPos = [$this->line, $this->col];

        $r = $this->hcl[$this->pos++];
        if ($r === PHP_EOL) {
            $this->line++;
            $this->col = 0;
        } else {
            $this->col++;
        }
        return $r;
    }

    public function previousPos(): array
    {
        return $this->previousPos;
    }

    public function peekNextOnMatch(string $match): bool
    {
        if (
            $this->pos + 1 < strlen($this->hcl)
            && $this->hcl[$this->pos + 1] === $match
        ) {
            $this->next();
            return true;
        }

        return false;
    }

    public function peekNextEquals(string $match): bool
    {
        return $this->pos + 1 < strlen($this->hcl)
            && $this->hcl[$this->pos + 1] === $match;
    }

    public function peek(): string
    {
        return $this->hcl[$this->pos];
    }

    public function eof(): bool
    {
        return $this->pos >= strlen($this->hcl);
    }

    public function croak($msg): void
    {
        throw new Exception($msg . " (" . (string)$this->line . ":" . (string)$this->col . ")");
    }
}
