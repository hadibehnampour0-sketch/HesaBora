<?php

namespace App\Http\Controllers;

use App\Models\CustomerGroup;
use App\Models\IntegrationConnection;
use App\Models\Warehouse;
use App\Services\Integration\IntegrationInventoryPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings screen for external-platform connections (WooCommerce today; the same screen works
 * for a future adapter -- only `source_system` and the credential fields it needs differ).
 */
class IntegrationConnectionController extends Controller
{
    private const SOURCE_SYSTEMS = ['woocommerce'];

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
        $validated = $request->validate([
            'source_system' => ['required', 'string', Rule::in(self::SOURCE_SYSTEMS)],
            'name' => ['required', 'string', 'max:150'],
            'site_url' => ['required', 'url', 'max:255'],
            'consumer_key' => ['required', 'string', 'max:255'],
            'consumer_secret' => ['required', 'string', 'max:255'],
            'webhook_secret' => ['required', 'string', 'max:255'],
            'default_warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('company_id', getActiveCompany())],
            'default_customer_group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('company_id', getActiveCompany())],
            'price_tolerance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        IntegrationConnection::create([
            'source_system' => $validated['source_system'],
            'name' => $validated['name'],
            'credentials' => [
                'site_url' => $validated['site_url'],
                'consumer_key' => $validated['consumer_key'],
                'consumer_secret' => $validated['consumer_secret'],
                'webhook_secret' => $validated['webhook_secret'],
            ],
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
}
