<?php

namespace App\Services\Industry;

use App\Models\IndustryProfileActivation;
use App\Models\IndustrySettingHistory;
use App\Models\IndustrySettingValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Per-company values for industry profile settings. Reads fall back to the
 * definition's default; writes are validated against the definition, so a
 * new setting needs no PHP. Every change (set / reset / confirm) is audited
 * in industry_setting_histories.
 *
 * These are configuration + informational-calculator inputs only. Nothing here
 * posts to the ledger, moves stock, or touches Trade (D-099 §12).
 */
class IndustrySettingsService
{
    public function __construct(private readonly IndustryProfileRegistry $registry) {}

    /** Typed effective value: the company's override if present, else the definition default. */
    public function value(string $profileKey, string $settingKey): mixed
    {
        $field = $this->field($profileKey, $settingKey);
        $row = IndustrySettingValue::where('profile_key', $profileKey)->where('setting_key', $settingKey)->first();

        return $this->cast($field, $row && $row->value !== null ? $row->value : ($field['default'] ?? null));
    }

    /** @return array<string, array{value: mixed, default: mixed, is_overridden: bool, confirmed_at: mixed}> */
    public function effective(string $profileKey): array
    {
        $rows = IndustrySettingValue::where('profile_key', $profileKey)->get()->keyBy('setting_key');
        $result = [];

        foreach ($this->registry->fields($profileKey) as $key => $field) {
            $row = $rows->get($key);
            $overridden = $row !== null && $row->value !== null;

            $result[$key] = [
                'value' => $this->cast($field, $overridden ? $row->value : ($field['default'] ?? null)),
                'default' => $field['default'] ?? null,
                'is_overridden' => $overridden,
                'confirmed_at' => $row?->confirmed_at,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input  setting key => raw value ('' or null resets to the default)
     */
    public function update(string $profileKey, array $input, ?int $userId): int
    {
        $fields = $this->registry->fields($profileKey);
        $errors = [];
        $clean = [];

        foreach ($input as $key => $raw) {
            if (! isset($fields[$key])) {
                $errors["settings.{$key}"] = __('Unknown setting.');

                continue;
            }

            $result = $this->normalize($fields[$key], $raw);

            if (is_string($result) && str_starts_with($result, '!')) {
                $errors["settings.{$key}"] = substr($result, 1);

                continue;
            }

            $clean[$key] = $result;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $changed = 0;

        DB::transaction(function () use ($profileKey, $clean, $userId, &$changed) {
            foreach ($clean as $key => $new) {
                $row = IndustrySettingValue::where('profile_key', $profileKey)->where('setting_key', $key)->lockForUpdate()->first();
                $old = $row?->value;

                if ($old === $new) {
                    continue;
                }

                if ($new === null) {
                    $row?->delete();
                    $this->log($profileKey, $key, 'reset', $old, null, $userId);
                } elseif ($row) {
                    $row->update(['value' => $new, 'updated_by' => $userId, 'confirmed_at' => null, 'confirmed_by' => null]);
                    $this->log($profileKey, $key, 'update', $old, $new, $userId);
                } else {
                    IndustrySettingValue::create([
                        'profile_key' => $profileKey,
                        'setting_key' => $key,
                        'value' => $new,
                        'updated_by' => $userId,
                    ]);
                    $this->log($profileKey, $key, 'update', null, $new, $userId);
                }

                $changed++;
            }
        });

        return $changed;
    }

    /** The company vouches for a setting (its default or its own value) as correct for its trade. */
    public function confirm(string $profileKey, string $settingKey, ?int $userId): void
    {
        $this->field($profileKey, $settingKey);

        DB::transaction(function () use ($profileKey, $settingKey, $userId) {
            $row = IndustrySettingValue::where('profile_key', $profileKey)->where('setting_key', $settingKey)->lockForUpdate()->first();

            if ($row) {
                $row->update(['confirmed_at' => now(), 'confirmed_by' => $userId]);
            } else {
                IndustrySettingValue::create([
                    'profile_key' => $profileKey,
                    'setting_key' => $settingKey,
                    'value' => null,
                    'confirmed_at' => now(),
                    'confirmed_by' => $userId,
                ]);
            }

            $this->log($profileKey, $settingKey, 'confirm', null, null, $userId);
        });
    }

    public function isActive(string $profileKey): bool
    {
        return IndustryProfileActivation::where('profile_key', $profileKey)->exists();
    }

    /** @return array<string, bool> profile key => active */
    public function activeMap(): array
    {
        return IndustryProfileActivation::pluck('profile_key')->flip()->map(fn () => true)->all();
    }

    public function setActive(string $profileKey, bool $active, ?int $userId): void
    {
        if (! $this->registry->find($profileKey)) {
            throw ValidationException::withMessages(['profile' => __('Unknown industry profile.')]);
        }

        DB::transaction(function () use ($profileKey, $active, $userId) {
            $row = IndustryProfileActivation::where('profile_key', $profileKey)->lockForUpdate()->first();

            if ($active && ! $row) {
                IndustryProfileActivation::create(['profile_key' => $profileKey, 'activated_at' => now()]);
                $this->log($profileKey, '*', 'activate', null, null, $userId);
            } elseif (! $active && $row) {
                $row->delete();
                $this->log($profileKey, '*', 'deactivate', null, null, $userId);
            }
        });
    }

    /**
     * Settings that still need a human decision: definition default is
     * needs_confirmation (or assumed) and this company has not confirmed or
     * overridden it.
     *
     * @return array<string, int> counts: needs_confirmation / assumed
     */
    public function pendingCounts(string $profileKey): array
    {
        $effective = $this->effective($profileKey);
        $counts = ['needs_confirmation' => 0, 'assumed' => 0];

        foreach ($this->registry->fields($profileKey) as $key => $field) {
            $handled = $effective[$key]['confirmed_at'] !== null || $effective[$key]['is_overridden'];

            if (! $handled && isset($counts[$field['status']])) {
                $counts[$field['status']]++;
            }
        }

        return $counts;
    }

    /** @return string|null normalized string to store (null = reset), or '!message' on validation failure */
    private function normalize(array $field, mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $type = $field['type'];

        if ($type === 'boolean') {
            return in_array($raw, [true, 1, '1', 'true', 'on'], true) ? '1' : '0';
        }

        if ($type === 'select') {
            $options = array_map(fn ($o) => is_array($o) ? (string) $o['value'] : (string) $o, $field['options']);

            return in_array((string) $raw, $options, true) ? (string) $raw : '!'.__('Invalid option.');
        }

        if ($type === 'text') {
            $text = trim((string) $raw);

            return mb_strlen($text) <= 255 ? $text : '!'.__('Value is too long.');
        }

        if (! is_numeric($raw)) {
            return '!'.__('Value must be a number.');
        }

        $number = (float) $raw;

        if ($type === 'integer' && floor($number) != $number) {
            return '!'.__('Value must be a whole number.');
        }

        $min = $field['min'] ?? ($type === 'percent' ? 0 : null);
        $max = $field['max'] ?? ($type === 'percent' ? 100 : null);

        if (($min !== null && $number < $min) || ($max !== null && $number > $max)) {
            return '!'.__('Value is out of the allowed range.');
        }

        return $type === 'integer' ? (string) (int) $number : rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
    }

    private function cast(array $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field['type']) {
            'boolean' => in_array($value, [true, 1, '1', 'true'], true),
            'integer' => (int) $value,
            'decimal', 'percent' => (float) $value,
            default => (string) $value,
        };
    }

    private function field(string $profileKey, string $settingKey): array
    {
        $fields = $this->registry->fields($profileKey);

        if (! isset($fields[$settingKey])) {
            throw ValidationException::withMessages(['setting' => __('Unknown setting.')]);
        }

        return $fields[$settingKey];
    }

    private function log(string $profileKey, string $settingKey, string $action, ?string $old, ?string $new, ?int $userId): void
    {
        IndustrySettingHistory::create([
            'profile_key' => $profileKey,
            'setting_key' => $settingKey,
            'action' => $action,
            'old_value' => $old,
            'new_value' => $new,
            'changed_by' => $userId,
            'created_at' => now(),
        ]);
    }
}
