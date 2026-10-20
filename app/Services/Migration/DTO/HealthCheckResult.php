<?php

namespace App\Services\Migration\DTO;

class HealthCheckResult
{
    public function __construct(
        public bool $passed = true,
        public array $errors = [],
        public array $warnings = [],
        public array $reconciliation = [],
        public bool $autoRepairPossible = true
    ) {}

    public function addError(string $message, array $context = []): void
    {
        $this->passed = false;
        $this->errors[] = [
            'message' => $message,
            'context' => $context,
        ];
    }

    public function addWarning(string $message, array $context = []): void
    {
        $this->warnings[] = [
            'message' => $message,
            'context' => $context,
        ];
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }
}
