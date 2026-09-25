<?php

namespace App\Services\Industry;

use InvalidArgumentException;

/**
 * Loads industry profile DEFINITIONS from resources/industry-profiles/*.json.
 * A definition says what a trade may configure (sections/fields with type,
 * default, unit, bounds, evidence status) and which calculators it offers.
 * Adding a trade or a setting = adding/editing a JSON file; no PHP change.
 *
 * Evidence status (per field) is about the DEFAULT value, not a company's
 * choice: verified (sourced), assumed (analyst's best estimate), or
 * needs_confirmation (unresolved with real practitioners).
 */
class IndustryProfileRegistry
{
    public const TYPES = ['integer', 'decimal', 'percent', 'boolean', 'select', 'text'];

    public const STATUSES = ['verified', 'assumed', 'needs_confirmation'];

    private ?array $profiles = null;

    public function __construct(private readonly ?string $path = null) {}

    /** @return array<string, array> keyed by profile key */
    public function all(): array
    {
        if ($this->profiles !== null) {
            return $this->profiles;
        }

        $dir = $this->path ?? resource_path('industry-profiles');
        $profiles = [];

        foreach (glob($dir.'/*.json') ?: [] as $file) {
            $definition = json_decode((string) file_get_contents($file), true);

            if (! is_array($definition)) {
                throw new InvalidArgumentException("Industry profile file is not valid JSON: {$file}");
            }

            $this->validate($definition, $file);
            $profiles[$definition['key']] = $definition;
        }

        ksort($profiles);

        return $this->profiles = $profiles;
    }

    public function find(string $key): ?array
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, array> flat map of setting key => field definition (with its section key) */
    public function fields(string $profileKey): array
    {
        $profile = $this->find($profileKey);
        $fields = [];

        foreach ($profile['sections'] ?? [] as $section) {
            foreach ($section['fields'] as $field) {
                $fields[$field['key']] = $field + ['section' => $section['key']];
            }
        }

        return $fields;
    }

    /** Pick the localized string from a {fa,en} map, or return a plain string as is. */
    public static function text(mixed $value): string
    {
        if (is_array($value)) {
            $locale = app()->getLocale();

            return (string) ($value[$locale] ?? $value['en'] ?? reset($value) ?: '');
        }

        return (string) $value;
    }

    private function validate(array $definition, string $file): void
    {
        foreach (['key', 'title', 'sections'] as $required) {
            if (! isset($definition[$required])) {
                throw new InvalidArgumentException("Industry profile {$file} is missing '{$required}'.");
            }
        }

        if (! preg_match('/^[a-z][a-z0-9_]*$/', $definition['key'])) {
            throw new InvalidArgumentException("Industry profile key '{$definition['key']}' is invalid.");
        }

        $seen = [];

        foreach ($definition['sections'] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                foreach (['key', 'type', 'title', 'status'] as $required) {
                    if (! array_key_exists($required, $field)) {
                        throw new InvalidArgumentException("Field in {$file} is missing '{$required}'.");
                    }
                }

                if (! in_array($field['type'], self::TYPES, true)) {
                    throw new InvalidArgumentException("Field '{$field['key']}' in {$file} has unknown type '{$field['type']}'.");
                }

                if (! in_array($field['status'], self::STATUSES, true)) {
                    throw new InvalidArgumentException("Field '{$field['key']}' in {$file} has unknown status '{$field['status']}'.");
                }

                if ($field['type'] === 'select' && empty($field['options'])) {
                    throw new InvalidArgumentException("Select field '{$field['key']}' in {$file} needs options.");
                }

                if (isset($seen[$field['key']])) {
                    throw new InvalidArgumentException("Duplicate setting key '{$field['key']}' in {$file}.");
                }

                $seen[$field['key']] = true;
            }
        }
    }
}
