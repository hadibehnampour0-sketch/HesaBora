<?php

namespace App\Http\Controllers;

use App\Models\IntegrationEvent;
use App\Services\Integration\ExternalReferenceService;
use App\Services\Integration\IntegrationHub;
use App\Services\Integration\IntegrationHubFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The "review queue" Dakhl_INTEGRATION_PLATFORMS_AND_POLICY_PLAN_2026-09-28.md §2 promises:
 * every imported order IntegrationHub could not auto-approve without guessing stays here,
 * visible, with its reason, until a human resolves it and retries -- same pattern already
 * proven by the Moadian work queue (Entry 104).
 */
class IntegrationQueueController extends Controller
{
    public function __construct(private readonly IntegrationHubFactory $hubFactory) {}

    public function index(): View
    {
        $events = IntegrationEvent::whereIn('status', [IntegrationHub::PENDING_DEPENDENCY, IntegrationHub::FAILED_RETRY, IntegrationHub::UNHANDLED])
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('integrations.queue.index', compact('events'));
    }

    public function retry(IntegrationEvent $event): RedirectResponse
    {
        $result = $this->hubFactory->make()->retryEvent($event);

        return back()->with($result['outcome'] === IntegrationHub::PROCESSED ? 'success' : 'error', $this->outcomeMessage($result['outcome']));
    }

    public function mapProduct(Request $request, ExternalReferenceService $externalReferenceService): RedirectResponse
    {
        $validated = $request->validate([
            'source_system' => ['required', 'string'],
            'external_id' => ['required', 'string'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', getActiveCompany())],
            'event_id' => ['required', 'integer', Rule::exists('integration_events', 'id')],
        ]);

        $externalReferenceService->link($validated['source_system'], $validated['external_id'], 'product', $validated['product_id']);

        $event = IntegrationEvent::findOrFail($validated['event_id']);
        $result = $this->hubFactory->make()->retryEvent($event);

        return back()->with($result['outcome'] === IntegrationHub::PROCESSED ? 'success' : 'error', $this->outcomeMessage($result['outcome']));
    }

    private function outcomeMessage(string $outcome): string
    {
        return match ($outcome) {
            IntegrationHub::PROCESSED => __('Resolved and processed successfully.'),
            default => __('Still not resolved -- see the reason shown for this row.'),
        };
    }
}
