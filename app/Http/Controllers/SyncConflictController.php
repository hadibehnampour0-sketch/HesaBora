<?php

namespace App\Http\Controllers;

use App\Enums\SyncConflictResolution;
use App\Models\SyncConflict;
use App\Services\Sync\SyncConflictService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SyncConflictController extends Controller
{
    public function __construct(private readonly SyncConflictService $conflictService) {}

    public function index()
    {
        $conflicts = $this->conflictService->openConflicts(getActiveCompany());

        return view('sync-conflicts.index', ['conflicts' => $conflicts]);
    }

    public function resolve(Request $request, SyncConflict $conflict)
    {
        abort_unless((int) $conflict->company_id === (int) getActiveCompany(), 404);

        $validated = $request->validate([
            'resolution' => ['required', Rule::in(['keep_server', 'keep_local', 'manual_edit'])],
            'payload' => ['required_if:resolution,manual_edit', 'array'],
        ]);

        $resolution = match ($validated['resolution']) {
            'keep_server' => SyncConflictResolution::KEEP_SERVER,
            'keep_local' => SyncConflictResolution::KEEP_LOCAL,
            'manual_edit' => SyncConflictResolution::MANUAL_EDIT,
        };

        $result = $this->conflictService->resolve($request->user(), $conflict, $resolution, $validated['payload'] ?? null);

        return redirect()->route('sync-conflicts.index')->with(
            $result['status'] === 'conflict' ? 'error' : 'success',
            $result['status'] === 'conflict'
                ? __('The resolution itself conflicted with a newer server change -- please review again.')
                : __('Conflict resolved successfully.')
        );
    }
}
