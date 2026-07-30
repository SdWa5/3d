<?php

declare(strict_types=1);

namespace App\Spec;

/**
 * Typed access to the decoded YAML of a spec file. Every failure names the key path it was
 * reading, because a spec author needs to know *which* field is wrong, not just that one is.
 * Numbers accept int as well as float — YAML writes `0.6` and `2` for the same kind of field.
 */
final class ArrayReader
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        private readonly array $data,
        private readonly string $path = '',
    ) {
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data) && $this->data[$key] !== null;
    }

    public function requireString(string $key): string
    {
        $value = $this->data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidSpecException("{$this->keyPath($key)}: expected a non-empty string");
        }

        return $value;
    }

    public function optionalString(string $key, ?string $default = null): ?string
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->requireString($key);
    }

    public function requireFloat(string $key): float
    {
        $value = $this->data[$key] ?? null;
        if (!is_int($value) && !is_float($value)) {
            throw new InvalidSpecException("{$this->keyPath($key)}: expected a number");
        }

        return (float)$value;
    }

    public function optionalFloat(string $key, ?float $default = null): ?float
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->requireFloat($key);
    }

    public function requireInt(string $key): int
    {
        $value = $this->data[$key] ?? null;
        if (!is_int($value)) {
            throw new InvalidSpecException("{$this->keyPath($key)}: expected an integer");
        }

        return $value;
    }

    public function optionalInt(string $key, ?int $default = null): ?int
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->requireInt($key);
    }

    public function requireBool(string $key): bool
    {
        $value = $this->data[$key] ?? null;
        if (!is_bool($value)) {
            throw new InvalidSpecException("{$this->keyPath($key)}: expected true or false");
        }

        return $value;
    }

    public function optionalBool(string $key, bool $default = false): bool
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->requireBool($key);
    }

    /**
     * Whether $key holds a nested mapping. Needed where a field accepts either a scalar shorthand
     * or the expanded form, e.g. `provenance`.
     */
    public function isSection(string $key): bool
    {
        return $this->has($key) && is_array($this->data[$key]) && !array_is_list($this->data[$key]);
    }

    /**
     * Nested mapping as a reader of its own, so error messages keep the full key path.
     */
    public function requireSection(string $key): self
    {
        $value = $this->data[$key] ?? null;
        if (!is_array($value)) {
            throw new InvalidSpecException("{$this->keyPath($key)}: expected a mapping");
        }

        return new self($value, $this->keyPath($key));
    }

    public function optionalSection(string $key): ?self
    {
        if (!$this->has($key)) {
            return null;
        }

        return $this->requireSection($key);
    }

    /**
     * List of nested mappings, each as its own reader (e.g. rigging points, drivers).
     *
     * @return list<self>
     */
    public function sectionList(string $key): array
    {
        if (!$this->has($key)) {
            return [];
        }
        $value = $this->data[$key];
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidSpecException("{$this->keyPath($key)}: expected a list");
        }

        $readers = [];
        foreach ($value as $index => $entry) {
            if (!is_array($entry)) {
                throw new InvalidSpecException("{$this->keyPath($key)}[{$index}]: expected a mapping");
            }
            $readers[] = new self($entry, "{$this->keyPath($key)}[{$index}]");
        }

        return $readers;
    }

    /**
     * List of plain strings (e.g. `handles: [left, right]`).
     *
     * @return list<string>
     */
    public function stringList(string $key): array
    {
        if (!$this->has($key)) {
            return [];
        }
        $value = $this->data[$key];
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidSpecException("{$this->keyPath($key)}: expected a list");
        }
        foreach ($value as $entry) {
            if (!is_string($entry)) {
                throw new InvalidSpecException("{$this->keyPath($key)}: expected a list of strings");
            }
        }

        /** @var list<string> $value */
        return $value;
    }

    /**
     * Exactly three numbers, used for `position_m: [x, y, z]`.
     *
     * @return array{float, float, float}
     */
    public function requireVector3(string $key): array
    {
        $value = $this->data[$key] ?? null;
        if (!is_array($value) || !array_is_list($value) || count($value) !== 3) {
            throw new InvalidSpecException("{$this->keyPath($key)}: expected [x, y, z]");
        }
        $vector = [];
        foreach ($value as $index => $component) {
            if (!is_int($component) && !is_float($component)) {
                throw new InvalidSpecException("{$this->keyPath($key)}[{$index}]: expected a number");
            }
            $vector[] = (float)$component;
        }

        /** @var array{float, float, float} $vector */
        return $vector;
    }

    /**
     * Enum value by its backed string, listing the accepted values on failure — the most
     * common spec mistake is a plausible-but-wrong word like `centre` or `measured-ish`.
     *
     * @template T of \BackedEnum
     * @param class-string<T> $enum
     * @return T
     */
    public function requireEnum(string $key, string $enum): object
    {
        $raw = $this->requireString($key);
        $case = $enum::tryFrom($raw);
        if ($case === null) {
            $allowed = implode(', ', array_column($enum::cases(), 'value'));
            throw new InvalidSpecException("{$this->keyPath($key)}: unknown value '{$raw}' (allowed: {$allowed})");
        }

        return $case;
    }

    /**
     * @template T of \BackedEnum
     * @param class-string<T> $enum
     * @param T $default
     * @return T
     */
    public function optionalEnum(string $key, string $enum, object $default): object
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->requireEnum($key, $enum);
    }

    private function keyPath(string $key): string
    {
        return $this->path === '' ? $key : "{$this->path}.{$key}";
    }
}
