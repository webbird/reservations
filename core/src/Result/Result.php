<?php

declare(strict_types=1);

namespace Reservations\Core\Result;

/**
 * Outcome of a service call. Errors carry a translation key, parameters and an
 * optional form field; translating is the wrapper's job.
 */
final readonly class Result
{
    /**
     * @param list<array{key: string, params: array<string, mixed>, field: ?string}> $errors
     */
    private function __construct(
        public bool $ok,
        public mixed $value,
        public array $errors,
    ) {
    }

    public static function ok(mixed $value = null): self
    {
        return new self(true, $value, []);
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function fail(string $key, array $params = [], ?string $field = null): self
    {
        return new self(false, null, [['key' => $key, 'params' => $params, 'field' => $field]]);
    }

    public function firstErrorKey(): ?string
    {
        return $this->errors[0]['key'] ?? null;
    }
}
