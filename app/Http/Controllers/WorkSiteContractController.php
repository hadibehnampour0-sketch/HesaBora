<?php

namespace App\Http\Controllers;

use App\Models\WorkSite;
use App\Models\WorkSiteContract;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkSiteContractController extends Controller
{
    public function index()
    {
        $search = request('search');

        $query = WorkSiteContract::with('workSites')
            ->whereHas('workSites', fn ($q) => $q->where('company_id', getActiveCompany()))
            ->orderBy('name');

        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%");
        }

        $contracts = $query->paginate(15);

        return view('work-site-contracts.index', compact('contracts', 'search'));
    }

    public function create()
    {
        $workSites = WorkSite::orderBy('name')->get();

        return view('work-site-contracts.create', compact('workSites'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'work_site_id' => ['required', 'integer', Rule::exists('work_sites', 'id')->where('company_id', getActiveCompany())],
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:20|unique:work_site_contracts,code',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        WorkSiteContract::create($validated);

        return redirect()->route('salary.work-site-contracts.index')
            ->with('success', __('Work site contract created successfully.'));
    }

    public function show(WorkSiteContract $workSiteContract)
    {
        $this->ensureOwnedByActiveCompany($workSiteContract);
        $workSiteContract->load('workSites');

        return view('work-site-contracts.show', compact('workSiteContract'));
    }

    public function edit(WorkSiteContract $workSiteContract)
    {
        $this->ensureOwnedByActiveCompany($workSiteContract);
        $workSites = WorkSite::orderBy('name')->get();

        return view('work-site-contracts.edit', compact('workSiteContract', 'workSites'));
    }

    public function update(Request $request, WorkSiteContract $workSiteContract)
    {
        $this->ensureOwnedByActiveCompany($workSiteContract);

        $validated = $request->validate([
            'work_site_id' => ['required', 'integer', Rule::exists('work_sites', 'id')->where('company_id', getActiveCompany())],
            'name' => 'required|string|max:200',
            'code' => 'required|string|max:20|unique:work_site_contracts,code,'.$workSiteContract->id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        $workSiteContract->update($validated);

        return redirect()->route('salary.work-site-contracts.index')
            ->with('success', __('Work site contract updated successfully.'));
    }

    public function destroy(WorkSiteContract $workSiteContract)
    {
        $this->ensureOwnedByActiveCompany($workSiteContract);

        $workSiteContract->delete();

        return redirect()->route('salary.work-site-contracts.index')
            ->with('success', __('Work site contract deleted successfully.'));
    }

    /**
     * `work_site_contracts` has no `company_id`/`FiscalYearScope` of its own
     * -- company is only derivable via the parent WorkSite. Found
     * adversarially while building the permission matrix (Workstream J/U):
     * every route here is standalone (not nested under a scoped
     * `{work_site}`), and `index()`/`store()`/`update()` did not scope by
     * company at all, so any company could list, view, create against
     * another company's work site, edit or delete another company's
     * contract by id.
     */
    private function ensureOwnedByActiveCompany(WorkSiteContract $workSiteContract): void
    {
        abort_unless($workSiteContract->workSites?->company_id === getActiveCompany(), 404);
    }
}
