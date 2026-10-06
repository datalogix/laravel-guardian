<?php

namespace Datalogix\Guardian\Tests\Feature\Framework;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Inertia\Controllers\LoginController;
use Datalogix\Guardian\Framework\Livewire\Pages\Login;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;

/**
 * A customer app on Livewire and an admin panel on Inertia, in the same app.
 */
#[Group('livewire')]
#[Group('inertia')]
class MultiFrameworkTest extends TestCase
{
    protected function fortresses(): array
    {
        return [
            Fortress::make()->livewire()->basic(),
            Fortress::make()->inertia()->admin(),
        ];
    }

    public function test_each_fortress_routes_to_its_own_front_end(): void
    {
        $this->assertSame(Login::class, Route::getRoutes()->getByName('auth.login')->getActionName());
        $this->assertSame(LoginController::class, Route::getRoutes()->getByName('guardian.admin.auth.login')->getActionName());
        $this->assertNotNull(Route::getRoutes()->getByName('guardian.admin.auth.login.submit'));
        $this->assertNull(Route::getRoutes()->getByName('auth.login.submit'));
    }

    public function test_the_livewire_fortress_renders_a_livewire_page(): void
    {
        $this->get('/login')->assertOk()->assertSee('wire:', false);
    }

    public function test_the_inertia_fortress_answers_with_inertia_pages(): void
    {
        $this->get('/admin/login', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('component', 'Guardian/Login')
            ->assertJsonPath('props.endpoints.submit', url('/admin/login'))
            ->assertJsonPath('props.signUpUrl', null);
    }

    public function test_the_inertia_fortress_signs_users_in(): void
    {
        $user = $this->createUser(['email' => 'admin@example.com']);

        $this->post('/admin/login', ['login' => 'admin@example.com', 'password' => 'password'], ['X-Inertia' => 'true'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(Guardian::getFortress('admin'));
    }
}
