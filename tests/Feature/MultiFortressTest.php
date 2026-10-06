<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

class MultiFortressTest extends TestCase
{
    protected function fortresses(): array
    {
        return [
            Fortress::make()->basic(),
            Fortress::make()->admin(),
        ];
    }

    public function test_both_fortresses_are_registered(): void
    {
        $this->assertCount(2, Guardian::getFortresses());
        $this->assertSame('default', Guardian::getDefaultFortress()->getId());
    }

    public function test_each_fortress_registers_its_own_routes_under_its_own_path(): void
    {
        $this->assertTrue(Route::has('auth.login'));
        $this->assertTrue(Route::has('guardian.admin.auth.login'));
    }

    public function test_the_default_fortress_serves_the_root_login_path(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_the_admin_fortress_serves_its_prefixed_login_path(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
    }

    public function test_fortresses_sharing_the_same_guard_share_authentication_state(): void
    {
        // basic() and admin() both default to the "web" guard, so a session
        // authenticated under the default fortress is also seen as authenticated
        // when browsing under the admin fortress's path.
        $user = $this->createUser(['password' => Hash::make('secret123')]);
        $this->actingAs($user);

        $response = $this->get('/admin/login');

        $response->assertRedirect();
    }

    public function test_admin_and_default_fortresses_use_independent_route_names(): void
    {
        $this->assertNotSame(route('auth.login'), route('guardian.admin.auth.login'));
        $this->assertStringContainsString('/admin/login', route('guardian.admin.auth.login'));
        $this->assertStringNotContainsString('/admin', route('auth.login'));
    }

    public function test_setting_the_current_fortress_changes_what_guardian_forwards_to(): void
    {
        $this->assertSame('default', Guardian::getId());

        Guardian::setCurrentFortress(Guardian::getFortress('admin'));

        $this->assertSame('admin', Guardian::getId());

        Guardian::resetCurrentFortress();

        $this->assertSame('default', Guardian::getId());
    }
}
