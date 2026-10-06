<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class HasRoutesCustomRoutesTest extends TestCase
{
    protected function fortresses(): array
    {
        return [
            Fortress::make()->basic()
                ->routes(fn () => Route::get('custom-public', fn () => 'public-ok')->name('custom-public'))
                ->routes(fn () => Route::get('custom-auth', fn () => 'auth-ok')->name('custom-auth'), requiresAuth: true),
        ];
    }

    public function test_a_custom_public_route_is_registered(): void
    {
        $response = $this->get('/custom-public');

        $response->assertOk();
        $response->assertSeeText('public-ok');
    }

    public function test_a_custom_authenticated_route_requires_login(): void
    {
        $response = $this->get('/custom-auth');

        $response->assertRedirect();
    }

    public function test_a_custom_authenticated_route_is_reachable_once_logged_in(): void
    {
        $this->actingAs($this->createUser());

        $response = $this->get('/custom-auth');

        $response->assertOk();
        $response->assertSeeText('auth-ok');
    }
}
