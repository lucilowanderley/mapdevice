<?php

namespace App\Execution;

/** Resultado de uma execução: saída de cada passo e, quando houve, o resultado da verificação (read-back). */
class ExecutionResult
{
    /**
     * @param  array<int, array{command: string, output: string}>  $steps
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $steps = [],
        public readonly ?string $error = null,
        public readonly ?bool $verified = null,
        public readonly ?string $verifyOutput = null,
        public readonly ?string $expected = null,
    ) {}

    public function output(): string
    {
        return trim(implode("\n", array_map(
            fn (array $s) => $s['command'] === '' ? $s['output'] : "$ {$s['command']}\n{$s['output']}",
            $this->steps
        )));
    }

    public static function failed(string $error): self
    {
        return new self(false, [], $error);
    }
}
