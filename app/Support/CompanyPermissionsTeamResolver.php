<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;
use Spatie\Permission\PermissionRegistrar;

/**
 * Organization Authorization phase (r12): resolves Spatie's team context to
 * the request's active Company by default, deferring to the SAME
 * `getActiveCompany()` helper every other company-scoping mechanism in this
 * app already uses (FiscalYearScope, DefaultCompany, SetApiCompany,
 * AuthenticateDevice). This keeps permission scoping consistent with data
 * scoping instead of introducing a second, stricter notion of "no company"
 * that the rest of the app doesn't share -- and it means the many existing
 * tests/console paths that call `$user->assignRole(...)` without an HTTP
 * request establishing company context keep working exactly as they did
 * pre-phase, via the same pre-existing fallback (first persisted company,
 * or id 1 on a brand-new database).
 *
 * `setPermissionsTeamId()` still allows an explicit, temporary override for
 * code that deliberately targets a DIFFERENT company than the active one in
 * a single request (see UserController::syncCompanyScopedRoles() and
 * CompanyController::createCompany()) -- once set, the override wins until
 * explicitly cleared by calling `setPermissionsTeamId(null)`, or the
 * request ends (this class is request-scoped). Passing `null` is the ONLY
 * way back to fallback (getActiveCompany()) mode -- restoring a previously
 * *resolved* numeric value instead of clearing would re-pin that stale
 * value even after the ambient active company later changes. Every
 * override-taking call site must clear with `null` in a `finally` block
 * once its narrow, deliberate window ends -- never "restore" a captured
 * numeric value.
 *
 * CRITICAL (Workstream T, found empirically): this app has
 * `enable_wildcard_permission = true`, which makes `hasPermissionTo()`/
 * `can()` resolve through Spatie's WILDCARD permission checker instead of
 * the plain hasRole()/hasDirectPermission() path. That checker builds a
 * per-user "wildcard permission index"
 * (`PermissionRegistrar::$wildcardPermissionsIndex`, keyed by [class][id])
 * and caches it on the registrar SINGLETON, and Spatie never re-derives it
 * when the team context changes. Empirically verified: without invalidating
 * it, checking a user's permissions in Company A, then switching to Company
 * B and checking again, silently returns Company A's permission set for
 * Company B. `hasRole()` alone is unaffected (it queries fresh every time);
 * only the wildcard-backed `can()`/`hasPermissionTo()` path is.
 *
 * This risk is not limited to an explicit setPermissionsTeamId() call --
 * the AMBIENT fallback (getActiveCompany(), driven by config/cookie) can
 * also change between two calls to getPermissionsTeamId() within the same
 * process without anyone calling setPermissionsTeamId() at all (middleware
 * mutates config directly). A container that is torn down and rebuilt
 * every request (standard PHP-FPM) never observes this, since the
 * registrar singleton -- and this resolver -- start fresh each time. It IS
 * observable within a single PHPUnit test that chains multiple `$this->get()`
 * calls sharing one container (proven by
 * tests/Feature/CompanyAuthorizationIsolationTest.php), and would be
 * equally real under Octane or a persistent queue worker, should this app
 * ever adopt one (currently it does not --
 * `config('permission.register_octane_reset_listener')` is `false`).
 * getPermissionsTeamId() therefore invalidates on EVERY resolution whose
 * result differs from the last one returned, not only on an explicit
 * setPermissionsTeamId() call.
 */
class CompanyPermissionsTeamResolver implements PermissionsTeamResolver
{
    private int|string|null $override = null;

    private bool $hasOverride = false;

    private int|string|null $lastResolved = null;

    private bool $hasResolvedOnce = false;

    public function setPermissionsTeamId($id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        if ($id === null) {
            $this->override = null;
            $this->hasOverride = false;
        } else {
            $this->override = $id;
            $this->hasOverride = true;
        }

        $this->resolveAndTrack();
    }

    public function getPermissionsTeamId(): int|string|null
    {
        return $this->resolveAndTrack();
    }

    private function resolveAndTrack(): int|string|null
    {
        $resolved = $this->hasOverride ? $this->override : getActiveCompany();

        if (! $this->hasResolvedOnce || $resolved !== $this->lastResolved) {
            app(PermissionRegistrar::class)->forgetWildcardPermissionIndex();
        }

        $this->hasResolvedOnce = true;
        $this->lastResolved = $resolved;

        return $resolved;
    }
}
