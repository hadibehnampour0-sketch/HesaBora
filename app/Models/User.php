<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\UserVerificationNotification;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, Impersonate, MustVerifyEmailTrait, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'ui_mode',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_otp',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'email_verification_otp_expires_at' => 'datetime',
        'ui_mode' => \App\Enums\UiMode::class,
    ];

    /**
     * Determine if the user has verified their email address.
     */
    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $otp = (string) random_int(100000, 999999);

        $this->forceFill([
            'email_verification_otp' => Hash::make($otp),
            'email_verification_otp_expires_at' => now()->addMinutes(config('app.verification_expire', 1)),
        ])->save();

        $this->notify(new UserVerificationNotification($otp));
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Memoizes whether this user holds the Super-Admin role, checked
     * WITHOUT Spatie's company-scoped `roles()` relation.
     *
     * Super-Admin is a system-wide oversight role (see AppServiceProvider's
     * Gate::before, which grants every ability to a Super-Admin regardless
     * of which company is active) assigned via the reserved sentinel
     * company id 0 in `model_has_roles` (Organization Authorization phase,
     * r12 -- see the r12 migration's docblock for why 0, not NULL, since
     * this column is NOT NULL and part of a composite primary key). Spatie's
     * own `roles()`/`hasRole()` filter the assignment pivot by the CURRENT
     * team/company context with a strict equality match, so
     * `hasRole('Super-Admin')` would incorrectly return false whenever a
     * specific company other than the sentinel is active. This bypasses
     * that filtering entirely, matching on role name regardless of
     * company_id, so it is also safe against ad-hoc test/seed data that
     * assigns the role under an arbitrary ambient company context.
     */
    private ?bool $isSuperAdminCache = null;

    public static function booted(): void
    {
        // The `ui_mode` column's DB-level default ('advanced') applies at
        // INSERT time, but Eloquent does not re-sync a default value back
        // into the in-memory model afterward -- a freshly `create()`d
        // instance would otherwise report `ui_mode === null` until
        // explicitly refreshed, even though the stored row is correct.
        static::creating(function (self $user) {
            $user->ui_mode ??= \App\Enums\UiMode::ADVANCED->value;
        });
    }

    /**
     * P8.1 (Zero-Knowledge UX). A per-USER preference (not per-company),
     * matching how `ui_mode` is a plain column on `users` -- the same
     * person sees the same mode regardless of which company they're
     * currently acting in.
     */
    public function isSimpleMode(): bool
    {
        return $this->ui_mode === \App\Enums\UiMode::SIMPLE;
    }

    public function isSuperAdmin(): bool
    {
        if ($this->isSuperAdminCache !== null) {
            return $this->isSuperAdminCache;
        }

        return $this->isSuperAdminCache = DB::table(config('permission.table_names.model_has_roles'))
            ->join('roles', 'roles.id', '=', config('permission.table_names.model_has_roles').'.role_id')
            ->where(config('permission.table_names.model_has_roles').'.model_id', $this->getKey())
            ->where(config('permission.table_names.model_has_roles').'.model_type', static::class)
            ->where('roles.name', 'Super-Admin')
            ->exists();
    }

    /**
     * Checks a directly-granted (not role-based) permission WITHOUT
     * Spatie's company-scoped `permissions()` relation -- for the handful
     * of permissions that are inherently cross-company (currently
     * `access-super-admin-panel` and `update-global-configs`; see
     * RolesAndPermissionsSeeder::seedRoles(), which excludes both from
     * every company-scoped role). Matches by permission name regardless of
     * the assignment row's company_id, exactly like isSuperAdmin().
     */
    public function hasGlobalPermission(string $permissionName): bool
    {
        return DB::table(config('permission.table_names.model_has_permissions'))
            ->join('permissions', 'permissions.id', '=', config('permission.table_names.model_has_permissions').'.permission_id')
            ->where(config('permission.table_names.model_has_permissions').'.model_id', $this->getKey())
            ->where(config('permission.table_names.model_has_permissions').'.model_type', static::class)
            ->where('permissions.name', $permissionName)
            ->exists();
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    public function canImpersonate(): bool
    {
        return $this->can('users.impersonate') || $this->can('users.*');
    }

    public function canBeImpersonated(): bool
    {
        return $this->companies()->exists() && ! $this->can('access-super-admin-panel');
    }

    public function canImpersonateUser(User $user): bool
    {
        return $this->canImpersonate() && ! $this->is($user) && $user->canBeImpersonated();
    }
}
