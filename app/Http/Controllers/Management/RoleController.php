<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public $rules = [
        'name' => 'required | string | min:3 | max:255',
    ];

    public $searchRules = [
        'search' => 'nullable | string',
    ];

    public $messages = [];

    public function __construct() {}

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $validated = Validator::make($request->all(), $this->searchRules, $this->messages)->validate();

        // Organization Authorization phase (r12): this screen manages only
        // the active company's OWN custom roles. The 6 seeded system roles
        // (company_id IS NULL, shared across every company) are edited
        // nowhere in the UI and must never be listed or mutated here --
        // changing one would silently change that role for every other
        // company using it too.
        $query = Role::where('company_id', getActiveCompany())
            ->where('name', '!=', 'Super-Admin')
            ->withCount(['permissions', 'users'])
            ->orderBy('id', 'desc');

        if (isset($validated['search']) && $search = $validated['search']) {
            $query->where('name', 'like', "%{$search}%");
        }

        $roles = $query->paginate(20)->withQueryString();

        return view('management.roles.index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permissions = Permission::query()->orderBy('name')->get();

        return view('management.roles.create', [
            'role' => null,
            'permissions' => $permissions,
            'syncedPerms' => collect(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), $this->rules, $this->messages)->validate();

        // add additional data in order to store
        $validated['guard_name'] = 'web';
        $validated['company_id'] = getActiveCompany();
        $role = Role::create($validated);

        if ($request->has('permissions')) {
            $permissions = Permission::whereIn('id', $request->permissions)->get();
            $role->syncPermissions($permissions);
        }

        return redirect(route('roles.index'))
            ->with('success', __('Role created successfully.'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        $this->ensureOwnedByActiveCompany($role);

        $permissions = Permission::query()->orderBy('name')->get();
        $syncedPerms = $role->permissions()->pluck('id');

        return view('management.roles.create', [
            'role' => $role,
            'permissions' => $permissions,
            'syncedPerms' => $syncedPerms,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        $this->ensureOwnedByActiveCompany($role);

        $validated = Validator::make($request->all(), $this->rules, $this->messages)->validate();

        if ($request->has('permissions')) {
            $permissions = Permission::whereIn('id', $request->permissions)->get();
            $role->syncPermissions($permissions);
        } else {
            $role->syncPermissions([]);
        }

        if ($role->update($validated)) {
            return redirect()->route('roles.index')
                ->with('success', __('Role updated successfully.'));
        }

        return redirect()->route('roles.index')
            ->with('error', 'An error occurred, Try again.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        $this->ensureOwnedByActiveCompany($role);

        if ($role->delete()) {
            return redirect(route('roles.index'))
                ->with('success', __('Role deleted successfully.'));
        }

        return redirect(route('roles.index'))
            ->with('error', 'An error occurred, Try again.');
    }

    /**
     * A seeded system role (company_id NULL) or another company's custom
     * role must never be reachable via this screen, even by guessing an id
     * in the URL -- see index()'s docblock for why.
     */
    private function ensureOwnedByActiveCompany(Role $role): void
    {
        abort_unless((int) $role->company_id === getActiveCompany(), 404);
    }
}
