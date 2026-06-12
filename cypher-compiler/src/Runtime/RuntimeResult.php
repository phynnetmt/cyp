<?php

namespace Cypher\Compiler\Runtime;

class RuntimeResult
{
    public bool $success = false;
    public string $output = '';
    public array $errors = [];

    public function hasErrors(): bool { return !empty($this->errors); }

    public function getOutput(): string { return $this->output; }
}
