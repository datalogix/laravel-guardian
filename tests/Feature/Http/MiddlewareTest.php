<?php

namespace Datalogix\Guardian\Tests\Feature\Http;

use Datalogix\Guardian\Events\ServingGuardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class MiddlewareTest extends TestCase
{
    public function test_guests_are_redirected_to_login_for_authenticated_routes(): void
    {
        $response = $this->post('/logout');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function test_authenticated_users_can_access_authenticated_routes(): void
    {
        $response = $this->actingAs($this->createUser())->post('/logout');

        $response->assertRedirect();
        $this->assertGuest();
    }

    public function test_users_without_fortress_access_are_forbidden(): void
    {
        $user = $this->createUser(['can_access' => false]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertForbidden();
    }

    public function test_authenticated_users_are_redirected_away_from_the_login_page(): void
    {
        $response = $this->actingAs($this->createUser())->get('/login');

        $response->assertRedirect();
    }

    public function test_guests_can_view_the_login_page(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_serving_guardian_event_is_dispatched_on_every_request(): void
    {
        Event::fake([ServingGuardian::class]);

        $this->get('/login');

        Event::assertDispatched(ServingGuardian::class);
    }
}
