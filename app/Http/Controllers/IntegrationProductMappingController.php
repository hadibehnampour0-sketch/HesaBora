<?php

namespace App\Http\Controllers;

use App\Models\ExternalReference;
use App\Models\IntegrationConnection;
use App\Models\Product;
use App\Services\Integration\ExternalReferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Bulk product mapping for one connection: map many Dakhl products to their platform ids in one
 * screen, instead of only reactively from the review queue's one-row-at-a-time form.
 */
class IntegrationProductMappingController extends Controller
{
    public function index(IntegrationConnection $connection): View
    {
        $products = Product::orderBy('name')->limit(500)->get(['id', 'name', 'code']);

        $mapped = ExternalReference::where('source_system', $connection->source_system)
            ->where('entity_type', 'product')
            ->whereIn('entity_id', $products->pluck('id'))
            ->get()
            ->keyBy('entity_id');

        return view('integrations.connections.mappings', [
            'connection' => $connection,
            'products' => $products,
            'mapped' => $mapped,
        ]);
    }

    public function store(Request $request, IntegrationConnection $connection, ExternalReferenceService $externalReferenceService): RedirectResponse
    {
        $validated = $request->validate([
            'external_ids' => ['required', 'array'],
            'external_ids.*' => ['nullable', 'string', 'max:120'],
        ]);

        $updated = 0;

        foreach ($validated['external_ids'] as $productId => $externalId) {
            $productId = (int) $productId;
            $externalId = trim((string) $externalId);

            if (! Product::where('id', $productId)->exists()) {
                continue;
            }

            $current = ExternalReference::where('source_system', $connection->source_system)
                ->where('entity_type', 'product')
                ->where('entity_id', $productId)
                ->first();

            if ($externalId === '') {
                // Blank clears an existing mapping; nothing to do if it was already unmapped.
                $current?->delete();

                continue;
            }

            if ($current && $current->external_id === $externalId) {
                continue; // unchanged
            }

            // A product being remapped to a different platform id -- drop the stale row first
            // so ExternalReferenceService::link()'s own collision guard checks the NEW id only.
            $current?->delete();
            $externalReferenceService->link($connection->source_system, $externalId, 'product', $productId);
            $updated++;
        }

        return back()->with('success', trans_choice(':count mapping updated.|:count mappings updated.', $updated, ['count' => $updated]));
    }
}
