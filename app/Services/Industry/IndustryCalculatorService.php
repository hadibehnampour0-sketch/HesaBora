<?php

namespace App\Services\Industry;

use Illuminate\Validation\ValidationException;

/**
 * Informational calculators a profile definition declares (resources/
 * industry-profiles/*.json). The definition chooses a calculator TYPE and
 * wires its parameters to the company's settings; this class only knows a
 * handful of generic types, so new trades reuse them without PHP changes.
 *
 * Results are advisory: nothing here posts, saves, or feeds the ledger.
 */
class IndustryCalculatorService
{
    public function __construct(
        private readonly IndustryProfileRegistry $registry,
        private readonly IndustrySettingsService $settings,
    ) {}

    public function run(string $profileKey, string $calculatorKey, array $inputs): array
    {
        $calculator = $this->calculator($profileKey, $calculatorKey);
        $values = $this->validatedInputs($calculator, $inputs);

        return match ($calculator['type']) {
            'purity_normalize' => $this->purityNormalize($profileKey, $calculator, $values),
            'layered_price' => $this->layeredPrice($profileKey, $calculator, $values),
            'unit_convert' => $this->unitConvert($profileKey, $calculator, $values),
            'divide_by_factor' => $this->divideByFactor($profileKey, $calculator, $values),
            'cylinder_weight' => $this->cylinderWeight($profileKey, $calculator, $values),
            default => throw ValidationException::withMessages(['calculator' => __('Unknown calculator type.')]),
        };
    }

    private function calculator(string $profileKey, string $calculatorKey): array
    {
        $profile = $this->registry->find($profileKey);

        foreach ($profile['calculators'] ?? [] as $calculator) {
            if ($calculator['key'] === $calculatorKey) {
                return $calculator;
            }
        }

        throw ValidationException::withMessages(['calculator' => __('Unknown calculator.')]);
    }

    private function validatedInputs(array $calculator, array $inputs): array
    {
        $values = [];
        $errors = [];

        foreach ($calculator['inputs'] ?? [] as $input) {
            $key = $input['key'];
            $raw = $inputs[$key] ?? null;

            if (($raw === null || $raw === '') && ($input['optional'] ?? false)) {
                continue;
            }

            if (isset($input['options'])) {
                if (! in_array((string) $raw, array_map('strval', $input['options']), true)) {
                    $errors["inputs.{$key}"] = __('Invalid option.');
                } else {
                    $values[$key] = (string) $raw;
                }

                continue;
            }

            if (! is_numeric($raw)) {
                $errors["inputs.{$key}"] = __('Value must be a number.');

                continue;
            }

            $number = (float) $raw;

            if (isset($input['min']) && $number < $input['min']) {
                $errors["inputs.{$key}"] = __('Value is out of the allowed range.');
            } elseif (isset($input['max']) && $number > $input['max']) {
                $errors["inputs.{$key}"] = __('Value is out of the allowed range.');
            } else {
                $values[$key] = $number;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $values;
    }

    private function purityNormalize(string $profileKey, array $calculator, array $values): array
    {
        $params = $calculator['params'];
        $base = (float) $this->settings->value($profileKey, $params['base_setting']);
        $decimals = (int) $this->settings->value($profileKey, $params['decimals_setting']);
        $mode = (string) $this->settings->value($profileKey, $params['rounding_setting']);

        if ($base <= 0) {
            throw ValidationException::withMessages(['calculator' => __('The standard purity setting must be greater than zero.')]);
        }

        $equivalent = $this->round($values['weight'] * $values['purity'] / $base, $decimals, $mode);

        return [
            'lines' => [
                ['label' => __('Equivalent weight at standard purity'), 'value' => $equivalent, 'emphasis' => true],
                ['label' => __('Standard purity used'), 'value' => $base],
            ],
            'total' => $equivalent,
        ];
    }

    private function layeredPrice(string $profileKey, array $calculator, array $values): array
    {
        $moneyDecimals = (int) ($calculator['params']['money_decimals'] ?? 0);
        $amounts = [];
        $lines = [];
        $total = 0.0;

        foreach ($calculator['components'] as $component) {
            if ($component['type'] === 'product') {
                $amount = 1.0;

                foreach ($component['factors'] as $factor) {
                    $factorValue = $values[$factor];

                    if (isset($component['subtract'][$factor])) {
                        $factorValue -= (float) $this->settings->value($profileKey, $component['subtract'][$factor]);
                    }

                    $amount *= $factorValue;
                }

                if (isset($component['divide_by_setting'])) {
                    $divisor = (float) $this->settings->value($profileKey, $component['divide_by_setting']);

                    if ($divisor <= 0) {
                        throw ValidationException::withMessages(['calculator' => __('A divisor setting must be greater than zero.')]);
                    }

                    $amount /= $divisor;
                }

                $percent = null;
            } else {
                $of = $component['of'] ?? $this->ofBySetting($profileKey, $component['of_by_setting']);
                $base = array_sum(array_map(fn ($k) => $amounts[$k] ?? 0.0, $of));
                $percent = isset($component['input'], $values[$component['input']])
                    ? (float) $values[$component['input']]
                    : (float) $this->settings->value($profileKey, $component['setting']);
                $amount = $base * $percent / 100;
            }

            $amount = round($amount, $moneyDecimals);
            $amounts[$component['key']] = $amount;

            if (! ($component['exclude_from_total'] ?? false)) {
                $total += $amount;
            }

            $lines[] = [
                'label' => IndustryProfileRegistry::text($component['title']),
                'value' => $amount,
                'percent' => $percent,
            ];
        }

        $lines[] = ['label' => __('Total'), 'value' => $total, 'emphasis' => true];

        return ['lines' => $lines, 'total' => $total];
    }

    /** @return list<string> component keys a percentage applies to, chosen by a setting's current value */
    private function ofBySetting(string $profileKey, array $spec): array
    {
        $current = $this->settings->value($profileKey, $spec['setting']);
        $key = is_bool($current) ? ($current ? '1' : '0') : (string) $current;

        return $spec['map'][$key] ?? throw ValidationException::withMessages(['calculator' => __('Unsupported setting value for this calculator.')]);
    }

    private function unitConvert(string $profileKey, array $calculator, array $values): array
    {
        $factor = function (string $unit) use ($profileKey, $calculator): float {
            $spec = $calculator['params']['units'][$unit];

            return (float) (is_array($spec) ? $this->settings->value($profileKey, $spec['setting']) : $spec);
        };

        $from = $factor($values['from']);
        $to = $factor($values['to']);

        if ($to <= 0) {
            throw ValidationException::withMessages(['calculator' => __('A unit factor must be greater than zero.')]);
        }

        $result = round($values['value'] * $from / $to, (int) ($calculator['params']['decimals'] ?? 4));

        return ['lines' => [['label' => __('Converted value'), 'value' => $result, 'emphasis' => true]], 'total' => $result];
    }

    private function divideByFactor(string $profileKey, array $calculator, array $values): array
    {
        $factor = $calculator['params']['factor'];
        $divisor = 1.0;

        foreach ($factor['multiply'] as $setting) {
            $divisor *= (float) $this->settings->value($profileKey, $setting);
        }

        foreach ($factor['divide'] ?? [] as $setting) {
            $denominator = (float) $this->settings->value($profileKey, $setting);

            if ($denominator <= 0) {
                throw ValidationException::withMessages(['calculator' => __('A divisor setting must be greater than zero.')]);
            }

            $divisor /= $denominator;
        }

        if ($divisor <= 0) {
            throw ValidationException::withMessages(['calculator' => __('The conversion factor must be greater than zero.')]);
        }

        $result = round($values['value'] / $divisor, (int) ($calculator['params']['decimals'] ?? 0));

        return [
            'lines' => [
                ['label' => __('Converted price'), 'value' => $result, 'emphasis' => true],
                ['label' => __('Conversion factor'), 'value' => round($divisor, 6)],
            ],
            'total' => $result,
        ];
    }

    private function cylinderWeight(string $profileKey, array $calculator, array $values): array
    {
        $density = (float) $this->settings->value($profileKey, $calculator['params']['density_setting']);
        $radius = $values['diameter_mm'] / 2000;
        $perPiece = M_PI * $radius ** 2 * $values['length_m'] * $density;
        $totalKg = round($perPiece * $values['count'], 3);

        return [
            'lines' => [
                ['label' => __('Weight per piece (kg)'), 'value' => round($perPiece, 3)],
                ['label' => __('Total weight (kg)'), 'value' => $totalKg, 'emphasis' => true],
                ['label' => __('Total weight (ton)'), 'value' => round($totalKg / 1000, 4)],
            ],
            'total' => $totalKg,
        ];
    }

    private function round(float $value, int $decimals, string $mode): float
    {
        $factor = 10 ** $decimals;

        return match ($mode) {
            'half_even' => round($value, $decimals, PHP_ROUND_HALF_EVEN),
            'down' => floor($value * $factor) / $factor,
            'up' => ceil($value * $factor) / $factor,
            default => round($value, $decimals, PHP_ROUND_HALF_UP),
        };
    }
}
