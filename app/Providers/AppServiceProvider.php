<?php

namespace App\Providers;

use App\Events\ChequeStatusChanged;
use App\Events\InvoiceStatusChanged;
use App\Faker\PersianProductProvider;
use App\Faker\PersianServiceProvider;
use App\Models\User;
use App\Models\WarehouseProductStock;
use App\Observers\WarehouseProductStockObserver;
use App\Services\ActivityLogService;
use App\Services\WebhookService;
use Faker\Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;
use App\Listeners\SendSmsOnDomainEventListener;
use App\Models\Payment;
use App\Observers\PaymentSmsObserver;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(ActivityLogService::class);
        $this->app->bind(
            \App\Services\Inquiry\Contracts\InquiryProviderInterface::class,
            \App\Services\Inquiry\Providers\SandboxInquiryProvider::class
        );
        $this->app->bind(
            \App\Services\AiVision\Contracts\InvoiceVisionParserInterface::class,
            \App\Services\AiVision\Parsers\GeminiVisionInvoiceParser::class
        );

        $this->app->afterResolving(Generator::class, function (Generator $faker) {
            $registered = [];
            foreach ($faker->getProviders() as $provider) {
                $registered[get_class($provider)] = true;
            }

            if (! isset($registered[PersianProductProvider::class])) {
                $faker->addProvider(new PersianProductProvider($faker));
            }

            if (! isset($registered[PersianServiceProvider::class])) {
                $faker->addProvider(new PersianServiceProvider($faker));
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (env('NATIVEPHP_RUNNING')) {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => env('NATIVEPHP_DATABASE_PATH'),
            ]);
        }

        Paginator::defaultView('vendor.pagination.daisyui');
        Paginator::defaultSimpleView('vendor.pagination.daisyui-simple');

        App::setLocale(config('app.locale', 'fa'));

        Gate::before(function ($user, $ability) {
            // Company-scoped Spatie roles() cannot resolve this globally --
            // see User::isSuperAdmin().
            if ($user->isSuperAdmin()) {
                return true;
            }

            // access-super-admin-panel / update-global-configs are the only
            // two permissions excluded from every company-scoped role
            // (RolesAndPermissionsSeeder::seedRoles()) -- they are inherently
            // cross-company, so a direct grant of one must not be hidden by
            // whichever company happens to be active. See
            // User::hasGlobalPermission().
            if (in_array($ability, ['access-super-admin-panel', 'update-global-configs'], true)
                && $user->hasGlobalPermission($ability)) {
                return true;
            }
        });

        // P6 (API v1 Platform): Dedoc\Scramble's RestrictedDocsAccess
        // middleware only auto-allows /docs/api in the local environment;
        // everywhere else it requires this Gate. Reuses the same
        // cross-company admin permission the Gate::before check above
        // already treats as global, rather than inventing a new one.
        Gate::define('viewApiDocs', fn (User $user) => $user->hasGlobalPermission('access-super-admin-panel'));

        foreach (['created', 'updated', 'deleted'] as $modelEvent) {
            Event::listen("eloquent.{$modelEvent}: *", function (string $eventName, array $payload) use ($modelEvent): void {
                if (isset($payload[0]) && $payload[0] instanceof Model) {
                    app(ActivityLogService::class)->recordModelEvent($modelEvent, $payload[0]);
                }
            });
        }

        // Organization Authorization phase (r12, Workstream Q): role
        // assignment/removal goes through raw pivot-table queries
        // (attach/detach/sync on model_has_roles), never firing an Eloquent
        // model event -- the listener above never sees it. Spatie's own
        // RoleAttached/RoleDetached events (enabled via
        // config('permission.events_enabled')) are the only hook available.
        Event::listen(RoleAttached::class, fn (RoleAttached $event) => $this->logRoleEvent('role_attached', $event->model, $event->rolesOrIds));
        Event::listen(RoleDetached::class, fn (RoleDetached $event) => $this->logRoleEvent('role_detached', $event->model, $event->rolesOrIds));

        // Workstream Q closure: direct permission grants (givePermissionTo/
        // revokePermissionTo on a User, distinct from role assignment above)
        // went through the same raw-pivot mechanism and were the one
        // documented remaining gap in D-091's audit coverage -- previously
        // test-only (see RolesAndPermissionsSeeder's comments on
        // access-super-admin-panel), now real product usage as of the
        // Security Hardening pass's own test suites. Same event-based
        // approach as Role, mirrored exactly.
        Event::listen(PermissionAttached::class, fn (PermissionAttached $event) => $this->logPermissionEvent('permission_attached', $event->model, $event->permissionsOrIds));
        Event::listen(PermissionDetached::class, fn (PermissionDetached $event) => $this->logPermissionEvent('permission_detached', $event->model, $event->permissionsOrIds));

        // P6 (API v1 Platform): translates internal domain events into the
        // webhook platform's own event-type vocabulary. Kept as a thin
        // translation layer here rather than inside the services
        // themselves, so InvoiceService/ChequeService stay unaware that
        // webhooks exist at all -- they only fire a plain domain event.
        Event::listen(InvoiceStatusChanged::class, function (InvoiceStatusChanged $event) {
            app(WebhookService::class)->dispatchEvent('invoice.status_changed', [
                'invoice_id' => $event->invoice->id,
                'previous_status' => $event->previousStatus->name,
                'new_status' => $event->newStatus->name,
            ], $event->invoice->company_id);
        });

        Event::listen(ChequeStatusChanged::class, function (ChequeStatusChanged $event) {
            app(WebhookService::class)->dispatchEvent('cheque.status_changed', [
                'cheque_id' => $event->cheque->id,
                'action' => $event->action,
                'previous_status' => $event->previousStatus->name,
                'new_status' => $event->newStatus->name,
            ], $event->cheque->company_id);
        });

        // Phase 18.1 Smart SMS Event Automation
        Event::listen(InvoiceStatusChanged::class, [SendSmsOnDomainEventListener::class, 'handleInvoiceStatusChanged']);
        Payment::observe(PaymentSmsObserver::class);

        // Phase 19.1 Customer Loyalty Points Accrual
        Event::listen(InvoiceStatusChanged::class, function (InvoiceStatusChanged $event) {
            if ($event->invoice->status->isApprovedOrSettled()) {
                app(\App\Services\Loyalty\CustomerLoyaltyWalletService::class)->accruePointsFromInvoice($event->invoice);
            }
        });

        // Integration Hub two-way stock sync, live half (Dakhl_INTEGRATION_PLATFORMS_AND_POLICY_PLAN_2026-09-28.md §3).
        WarehouseProductStock::observe(WarehouseProductStockObserver::class);
    }

    private function logRoleEvent(string $event, Model $model, mixed $rolesOrIds): void
    {
        if (! $model instanceof User || ! config('activitylog.enabled')) {
            return;
        }

        $roleNames = collect(is_iterable($rolesOrIds) ? $rolesOrIds : [$rolesOrIds])
            ->map(fn ($role) => $role instanceof Role ? $role->name : (Role::find($role)?->name ?? $role))
            ->values()
            ->all();

        activity('security')
            ->causedBy(auth()->user())
            ->performedOn($model)
            ->withProperties([
                'roles' => $roleNames,
                'company_id' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
            ])
            ->log($event);
    }

    private function logPermissionEvent(string $event, Model $model, mixed $permissionsOrIds): void
    {
        if (! $model instanceof User || ! config('activitylog.enabled')) {
            return;
        }

        $permissionNames = collect(is_iterable($permissionsOrIds) ? $permissionsOrIds : [$permissionsOrIds])
            ->map(fn ($permission) => $permission instanceof Permission ? $permission->name : (Permission::find($permission)?->name ?? $permission))
            ->values()
            ->all();

        activity('security')
            ->causedBy(auth()->user())
            ->performedOn($model)
            ->withProperties([
                'permissions' => $permissionNames,
                'company_id' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
            ])
            ->log($event);
    }
}
