<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);

        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(403);

    }
}
