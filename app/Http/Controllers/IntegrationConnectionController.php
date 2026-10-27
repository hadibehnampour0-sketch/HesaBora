<?php

namespace App\Http\Controllers;

use App\Models\CustomerGroup;
use App\Models\IntegrationConnection;
use App\Models\Warehouse;
use App\Services\Integration\IntegrationInventoryPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings screen for external-platform connections (WooCommerce and Digikala).
 */
class IntegrationConnectionController extends Controller
{
    private const SOURCE_SYSTEMS = ['woocommerce', 'digikala'];

    public function index(): View
    {
        return view('integrations.connections.index', [
            'connections' => IntegrationConnection::orderByDesc('id')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'customerGroups' => CustomerGroup::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $sourceSystem = $request->input('source_system');

        $rules = [
            'source_system' => ['required', 'string', Rule::in(self::SOURCE_SYSTEMS)],
            'name' => ['required', 'string', 'max:150'],
            'default_warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('company_id', getActiveCompany())],
            'default_customer_group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('company_id', getActiveCompany())],
            'price_tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];

        if ($sourceSystem === 'digikala') {
            $rules['api_key'] = ['required', 'string', 'max:255'];
            $rules['seller_id'] = ['required', 'string', 'max:100'];
            $rules['site_url'] = ['nullable', 'url', 'max:255'];
            $rules['webhook_secret'] = ['nullable', 'string', 'max:255'];
        } else {
            $rules['site_url'] = ['required', 'url', 'max:255'];
            $rules['consumer_key'] = ['required', 'string', 'max:255'];
            $rules['consumer_secret'] = ['required', 'string', 'max:255'];
            $rules['webhook_secret'] = ['required', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);

        $credentials = $sourceSystem === 'digikala'
            ? [
                'site_url' => $validated['site_url'] ?? 'https://seller.digikala.com',
                'api_key' => $validated['api_key'],
                'seller_id' => $validated['seller_id'],
                'webhook_secret' => $validated['webhook_secret'] ?? '',
            ]
            : [
                'site_url' => $validated['site_url'],
                'consumer_key' => $validated['consumer_key'],
                'consumer_secret' => $validated['consumer_secret'],
                'webhook_secret' => $validated['webhook_secret'],
            ];

        IntegrationConnection::create([
            'source_system' => $validated['source_system'],
            'name' => $validated['name'],
            'credentials' => $credentials,
            'default_warehouse_id' => $validated['default_warehouse_id'] ?? null,
            'default_customer_group_id' => $validated['default_customer_group_id'] ?? null,
            'price_tolerance_percent' => $validated['price_tolerance_percent'] ?? 2.00,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', __('Connection created successfully.'));
    }

    public function update(Request $request, IntegrationConnection $connection): RedirectResponse
    {
        $validated = $request->validate([
            'default_warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('company_id', getActiveCompany())],
            'default_customer_group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('company_id', getActiveCompany())],
            'price_tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $connection->update($validated + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('Connection updated successfully.'));
    }

    public function destroy(IntegrationConnection $connection): RedirectResponse
    {
        $connection->delete();

        return back()->with('success', __('Connection removed successfully.'));
    }

    public function pushInventory(IntegrationConnection $connection, IntegrationInventoryPushService $service): RedirectResponse
    {
        $result = $service->pushAll($connection);

        $message = trans_choice(':count product\'s stock pushed to the platform.|:count products\' stock pushed to the platform.', $result['pushed'], ['count' => $result['pushed']]);
        if ($result['failed'] > 0) {
            $message .= ' '.trans_choice(':count failed.|:count failed.', $result['failed'], ['count' => $result['failed']]);
        }

        return back()->with($result['failed'] > 0 ? 'error' : 'success', $message);
    }

    public function testConnection(IntegrationConnection $connection): RedirectResponse
    {
        try {
            if ($connection->source_system === 'woocommerce') {
                $credentials = $connection->credentials;
                $url = rtrim($credentials['site_url'], '/').'/wp-json/wc/v3/products?per_page=1';
                $response = Http::timeout(6)
                    ->withBasicAuth($credentials['consumer_key'] ?? '', $credentials['consumer_secret'] ?? '')
                    ->get($url);

                if ($response->successful()) {
                    return back()->with('success', __('WooCommerce connection tested successfully. Status: :status', ['status' => $response->status()]));
                }

                return back()->with('error', __('WooCommerce connection failed. HTTP :status: :body', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 100),
                ]));
            }

            if ($connection->source_system === 'digikala') {
                $credentials = $connection->credentials;
                $baseUrl = rtrim($credentials['site_url'] ?? 'https://seller.digikala.com', '/');
                $response = Http::timeout(6)
                    ->withHeaders([
                        'api-key' => $credentials['api_key'] ?? '',
                        'Accept' => 'application/json',
                    ])
                    ->get("{$baseUrl}/open-api/v1/variants/?size=1");

                if ($response->successful()) {
                    return back()->with('success', __('Digikala connection tested successfully. Status: :status', ['status' => $response->status()]));
                }

                return back()->with('error', __('Digikala connection failed. HTTP :status: :body', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 100),
                ]));
            }

            return back()->with('error', __('Unsupported source system for test.'));
        } catch (\Throwable $e) {
            return back()->with('error', __('Connection test error: :error', ['error' => $e->getMessage()]));
        }
    }
}
