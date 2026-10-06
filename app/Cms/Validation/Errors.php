<?php

namespace App\Cms\Validation;

use Illuminate\Validation\ValidationException;

/**
 * Collects validation messages keyed by dotted path ("blocks.<uuid>.content.title").
 */
final class Errors
{
    /** @var array<string, list<string>> */
    private array $messages = [];

    public function add(string $path, string $message): void
    {
        $this->messages[$path][] = $message;
    }

    public function isEmpty(): bool
    {
        return $this->messages === [];
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->messages;
    }

    public function throwIfAny(): void
    {
        if (! $this->isEmpty()) {
            throw ValidationException::withMessages($this->messages);
        }
    }
}
