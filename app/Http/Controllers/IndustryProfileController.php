<?php

namespace App\Http\Controllers;

use App\Models\IndustrySettingHistory;
use App\Services\Industry\IndustryCalculatorService;
use App\Services\Industry\IndustryProfileRegistry;
use App\Services\Industry\IndustrySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Industry Profiles: one settings + calculators dashboard per trade (gold,
 * iron, ...). What each trade can configure is DATA (resources/industry-
 * profiles/*.json), so nothing here changes when a trade or setting is
 * added. Company isolation comes from the FiscalYearScope on the value /
 * activation / history models -- there is no route-bound model here, the
 * profile key is a validated string looked up in the registry (unknown => 404).
 *
 * Not a Trade Engine (D-099 §12): configuration and informational
 * calculators only; nothing posts to the ledger or touches stock.
 */
class IndustryProfileController extends Controller
{
    public function __construct(
        private readonly IndustryProfileRegistry $registry,
        private readonly IndustrySettingsService $settings,
        private readonly IndustryCalculatorService $calculators,
    ) {}

    public function index(): View
    {
        $active = $this->settings->activeMap();
        $cards = [];

        foreach ($this->registry->all() as $key => $profile) {
            $cards[] = [
                'key' => $key,
                'title' => IndustryProfileRegistry::text($profile['title']),
                'description' => IndustryProfileRegistry::text($profile['description'] ?? ''),
                'active' => isset($active[$key]),
                'pending' => $this->settings->pendingCounts($key),
                'fields' => count($this->registry->fields($key)),
                'calculators' => count($profile['calculators'] ?? []),
            ];
        }

        return view('industry-profiles.index', ['cards' => $cards]);
    }

    public function show(string $profile): View
    {
        $definition = $this->definition($profile);

        return view('industry-profiles.show', [
            'profile' => $definition,
            'effective' => $this->settings->effective($profile),
            'active' => $this->settings->isActive($profile),
            'pending' => $this->settings->pendingCounts($profile),
            'history' => IndustrySettingHistory::where('profile_key', $profile)->latest('id')->limit(15)->get(),
            'fieldDefinitions' => $this->registry->fields($profile),
        ]);
    }

    public function update(Request $request, string $profile): RedirectResponse
    {
        $this->definition($profile);

        $validated = $request->validate([
            'settings' => ['array'],
        ]);

        $changed = $this->settings->update($profile, $validated['settings'] ?? [], $request->user()?->id);

        return redirect()->route('industry-profiles.show', $profile)
            ->with('success', __('Industry settings saved (:count changed).', ['count' => $changed]));
    }

    public function confirm(Request $request, string $profile, string $setting): RedirectResponse
    {
        $this->definition($profile);
        $this->settings->confirm($profile, $setting, $request->user()?->id);

        return redirect()->route('industry-profiles.show', $profile)
            ->with('success', __('Setting confirmed for your trade.'));
    }

    public function toggle(Request $request, string $profile): RedirectResponse
    {
        $this->definition($profile);
        $this->settings->setActive($profile, ! $this->settings->isActive($profile), $request->user()?->id);

        return redirect()->back()->with('success', __('Industry profile status updated.'));
    }

    public function calculate(Request $request, string $profile, string $calculator): JsonResponse
    {
        $this->definition($profile);

        $result = $this->calculators->run($profile, $calculator, (array) $request->input('inputs', []));

        return response()->json($result);
    }

    private function definition(string $profile): array
    {
        return $this->registry->find($profile) ?? abort(404);
    }
}
