<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreWebhookRequest;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6 (API v1 Platform). Genuinely new -- no legacy /api/* equivalent to
 * delegate to, unlike the other V1 controllers.
 */
class WebhookController extends Controller
{
    use ApiResponds;

    public function __construct(private readonly WebhookService $webhookService) {}

    public function index(Request $request, int $company): JsonResponse
    {
        $webhooks = Webhook::where('company_id', $company)
            ->get(['id', 'url', 'event_types', 'is_active', 'created_at']);

        return $this->success($webhooks);
    }

    public function store(StoreWebhookRequest $request, int $company): JsonResponse
    {
        $webhook = $this->webhookService->register(
            $company,
            $request->validated('url'),
            $request->validated('event_types'),
            $request->user()->id
        );

        return $this->success([
            'id' => $webhook->id,
            'url' => $webhook->url,
            'event_types' => $webhook->event_types,
            'secret' => $webhook->secret,
        ], [], 201);
    }

    public function destroy(Request $request, int $company, int $webhook): JsonResponse
    {
        $model = Webhook::where('company_id', $company)->findOrFail($webhook);
        $model->delete();

        return $this->success(['deleted' => true]);
    }
}
