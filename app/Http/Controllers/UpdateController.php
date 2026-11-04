<?php

namespace App\Http\Controllers;

use App\Services\UpdateService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * System Software Updates & Patch Management Controller.
 */
class UpdateController extends Controller
{
    public function __construct(private readonly UpdateService $updateService) {}

    /**
     * Display update status and available releases.
     */
    public function index(): View
    {
        $updateInfo = $this->updateService->checkForUpdates();
        $history = $this->updateService->getUpdateHistory();

        return view('updates.index', compact('updateInfo', 'history'));
    }

    /**
     * Trigger fresh check for updates.
     */
    public function check(): JsonResponse
    {
        $info = $this->updateService->checkForUpdates(forceRefresh: true);

        return response()->json($info);
    }

    /**
     * Execute safe atomic update.
     */
    public function apply(Request $request): RedirectResponse
    {
        try {
            $result = $this->updateService->applyUpdate();

            return redirect()->route('updates.index')
                ->with('success', $result['message']);
        } catch (Exception $e) {
            return redirect()->route('updates.index')
                ->with('error', $e->getMessage());
        }
    }
}
