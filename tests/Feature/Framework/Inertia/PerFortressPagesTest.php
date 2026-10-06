<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Fortress;
use PHPUnit\Framework\Attributes\Group;

/**
 * A customer app and an admin panel on Inertia, each with its own screens.
 */
#[Group('inertia')]
class PerFortressPagesTest extends InertiaTestCase
{
    protected function fortresses(): array
    {
        return [
            Fortress::make()->inertia()->product()->default(),
            Fortress::make()->inertia(prefix: 'Admin', pages: ['sign-up' => 'Admin/Join'])->product('admin')->path('admin'),
        ];
    }

    public function test_each_fortress_renders_the_components_it_asked_for(): void
    {
        $this->assertPage($this->inertiaGet('/login'), 'Guardian/Login');
        $this->assertPage($this->inertiaGet('/admin/login'), 'Admin/Login');
        $this->assertPage($this->inertiaGet('/sign-up'), 'Guardian/SignUp');
        $this->assertPage($this->inertiaGet('/admin/sign-up'), 'Admin/Join');
    }

    public function test_each_page_submits_to_the_endpoints_of_its_own_fortress(): void
    {
        $this->assertPage($this->inertiaGet('/login'), 'Guardian/Login', ['endpoints.submit' => url('/login')]);
        $this->assertPage($this->inertiaGet('/admin/login'), 'Admin/Login', ['endpoints.submit' => url('/admin/login')]);
    }

    public function test_the_admin_fortress_signs_users_in(): void
    {
        $user = $this->createUser(['email' => 'admin@example.com']);

        $this->inertiaPost('/admin/login', ['login' => 'admin@example.com', 'password' => 'password'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }
}
