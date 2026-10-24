<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RouteCrawlAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_parameter_free_get_routes_respond_without_500_errors(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company->id);

        // Grant all permissions so permission checks do not block
        $allPermissions = Permission::all();
        $user->syncPermissions($allPermissions);

        $routes = Route::getRoutes();
        $tested = 0;
        $failures = [];

        $ignoredUris = [
            'login', 'logout', 'register', 'verify', 'forgot-password',
            'impersonation/leave',
        ];

        foreach ($routes as $route) {
            if (in_array('GET', $route->methods()) && !str_contains($route->uri(), '{')) {
                $uri = $route->uri();
                if (str_starts_with($uri, '_') || str_starts_with($uri, 'api/')) {
                    continue;
                }
                if (in_array($uri, $ignoredUris)) {
                    continue;
                }

                $tested++;
                try {
                    $response = $this->actingAs($user)
                        ->withSession([
                            'company_id' => $company->id,
                            'fiscal_year' => 1405,
                        ])
                        ->get('/' . ltrim($uri, '/'));

                    $status = $response->getStatusCode();
                    if ($status >= 500) {
                        $failures[] = "[$status] /$uri (route: " . ($route->getName() ?? 'unnamed') . ")";
                    }
                } catch (\Throwable $e) {
                    $failures[] = "[Exception: {$e->getMessage()}] /$uri";
                }
            }
        }

        $this->assertEmpty($failures, "Found 500 errors on routes:\n" . implode("\n", $failures));
        $this->assertGreaterThan(50, $tested, "Expected to test at least 50 routes, tested: $tested");
    }
}
