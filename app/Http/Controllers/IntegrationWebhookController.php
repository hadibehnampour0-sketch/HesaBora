<?php

namespace App\Http\Controllers;

use App\Models\IntegrationConnection;
use App\Services\Integration\Adapters\WooCommerceAdapter;
use App\Services\Integration\IntegrationHubFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

/**
 * Unauthenticated inbound endpoint (a platform cannot log in) -- the `{token}` in the URL is
 * this connection's only credential besides the HMAC signature the adapter itself verifies.
 * Reachable outside a logged-in session, so the active-company context must be set explicitly
 * from the resolved connection (same reasoning as EvaluateTaxAlertsJob's active-company swap),
 * not assumed from a cookie that does not exist here.
 */
class IntegrationWebhookController extends Controller
{
    public function __construct(private readonly IntegrationHubFactory $hubFactory) {}

    public function woocommerce(Request $request, string $token): Response
    {
        $connection = IntegrationConnection::withoutGlobalScopes()
            ->where('webhook_token', $token)
            ->where('source_system', 'woocommerce')
            ->where('is_active', true)
            ->first();

        if (! $connection) {
            return response('', 404);
        }

        $previousActiveCompany = config('active-company-id');
        config(['active-company-id' => $connection->company_id]);

        try {
            $adapter = new WooCommerceAdapter($connection);
            $events = $adapter->normalize($request->all(), $this->flattenHeaders($request), $request->getContent());

            $hub = $this->hubFactory->make();
            foreach ($events as $event) {
                $hub->ingest($event);
            }

            return response('', 200);
        } catch (InvalidArgumentException $e) {
            return response($e->getMessage(), 422);
        } finally {
            config(['active-company-id' => $previousActiveCompany]);
        }
    }

    /** @return array<string, string> lower-cased single-value header map, matching what the adapter expects */
    private function flattenHeaders(Request $request): array
    {
        $flat = [];
        foreach ($request->headers->all() as $name => $values) {
            $flat[strtolower($name)] = $values[0] ?? '';
        }

        return $flat;
    }
}
