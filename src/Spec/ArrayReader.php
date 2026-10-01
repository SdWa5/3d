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
        return array_key_exists($key, $this->data) && null !== $this->data[$key];
    }

    /**
     * Keys present here that the caller does not know about.
     *
     * Unknown keys are accepted everywhere else in this reader on purpose — it keeps old spec files
     * readable and new fields optional. But a block whose every field changes the geometry cannot afford
     * it: `arc: {step: 10}` instead of `step_deg` would silently fall back to the default angle and move
     * every cabinet, with nothing to see in the output.
     *
     * @param list<string> $known
     *
     * @return list<string>
     */
    public function unknownKeys(array $known): array
    {
        return array_values(array_diff(array_keys($this->data), $known));
    }

    public function requireString(string $key): string
    {
        $value = $this->data[$key] ?? null;
        if (!is_string($value) || '' === trim($value)) {
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

        return (float) $value;
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
     * The keys present here, for a block whose field *names* are data — a map of named foci, say, where
     * the names are the scene's own choice rather than part of the schema.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(static fn (int|string $key): string => (string) $key, array_keys($this->data));
    }

    /**
     * Whether $key holds a list. The counterpart of {@see isSection}, for a field that accepts either one
     * number for every axis or one per axis — `gap_m: 0.02` against `gap_m: [0.02, 0, 0.10]`.
     */
    public function isList(string $key): bool
    {
        return $this->has($key) && is_array($this->data[$key]) && array_is_list($this->data[$key]);
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
     * A list whose entries are each **either** a bare string or a nested mapping.
     *
     * The counterpart of {@see isList} one level down, and it exists for the same reason: a field where the
     * common case wants no ceremony and the awkward case needs keys. `stack.from` is that field — most
     * entries are just a device id, and the occasional one needs a count or an alignment — and neither
     * {@see stringList} (all strings) nor {@see sectionList} (all mappings) can read a list holding both.
     *
     * Strings come back as strings; mappings come back as readers, so their own errors keep the full key path.
     *
     * @return list<string|self>
     */
    public function entryList(string $key): array
    {
        if (!$this->has($key)) {
            return [];
        }
        $value = $this->data[$key];
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidSpecException("{$this->keyPath($key)}: expected a list");
        }

        $entries = [];
        foreach ($value as $index => $entry) {
            if (is_string($entry)) {
                $entries[] = $entry;
                continue;
            }
            if (is_array($entry) && !array_is_list($entry)) {
                $entries[] = new self($entry, "{$this->keyPath($key)}[{$index}]");
                continue;
            }

            throw new InvalidSpecException("{$this->keyPath($key)}[{$index}]: expected a name or a mapping");
        }

        return $entries;
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
     * A list of numbers of any length, e.g. a scene's `at: [x, y]`.
     *
     * @return list<float>
     */
    public function numberList(string $key): array
    {
        if (!$this->has($key)) {
            return [];
        }
        $value = $this->data[$key];
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidSpecException("{$this->keyPath($key)}: expected a list of numbers");
        }

        $numbers = [];
        foreach ($value as $index => $entry) {
            if (!is_int($entry) && !is_float($entry)) {
                throw new InvalidSpecException("{$this->keyPath($key)}[{$index}]: expected a number");
            }
            $numbers[] = (float) $entry;
        }

        return $numbers;
    }

    /**
     * A list of two-number points, e.g. a removal's `section_m: [[0.0, 0.1], [0.2, 0.1], ...]`.
     *
     * @return list<array{float, float}>
     */
    public function pointList(string $key): array
    {
        if (!$this->has($key)) {
            return [];
        }
        $value = $this->data[$key];
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidSpecException("{$this->keyPath($key)}: expected a list of [a, b] points");
        }

        $points = [];
        foreach ($value as $index => $point) {
            if (!is_array($point) || !array_is_list($point) || 2 !== count($point)
                || (!is_int($point[0]) && !is_float($point[0])) || (!is_int($point[1]) && !is_float($point[1]))) {
                throw new InvalidSpecException("{$this->keyPath($key)}[{$index}]: expected [a, b]");
            }
            $points[] = [(float) $point[0], (float) $point[1]];
        }

        return $points;
    }

    /**
     * Exactly three numbers, used for `position_m: [x, y, z]`.
     *
     * @return array{float, float, float}
     */
    public function requireVector3(string $key): array
    {
        $value = $this->data[$key] ?? null;
        if (!is_array($value) || !array_is_list($value) || 3 !== count($value)) {
            throw new InvalidSpecException("{$this->keyPath($key)}: expected [x, y, z]");
        }
        $vector = [];
        foreach ($value as $index => $component) {
            if (!is_int($component) && !is_float($component)) {
                throw new InvalidSpecException("{$this->keyPath($key)}[{$index}]: expected a number");
            }
            $vector[] = (float) $component;
        }

        /** @var array{float, float, float} $vector */
        return $vector;
    }

    /**
     * Enum value by its backed string, listing the accepted values on failure — the most
     * common spec mistake is a plausible-but-wrong word like `centre` or `measured-ish`.
     *
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     *
     * @return T
     */
    public function requireEnum(string $key, string $enum): object
    {
        $raw = $this->requireString($key);
        $case = $enum::tryFrom($raw);
        if (null === $case) {
            $allowed = implode(', ', array_column($enum::cases(), 'value'));
            throw new InvalidSpecException("{$this->keyPath($key)}: unknown value '{$raw}' (allowed: {$allowed})");
        }

        return $case;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enum
     * @param T $default
     *
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
        return '' === $this->path ? $key : "{$this->path}.{$key}";
    }
}
