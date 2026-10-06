<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Inertia\Controllers\LoginController;
use Datalogix\Guardian\Framework\Inertia\Controllers\TwoFactorSetupController;
use Datalogix\Guardian\Framework\Inertia\InertiaAdapter;
use Datalogix\Guardian\Framework\Livewire\LivewireAdapter;
use Datalogix\Guardian\Framework\Livewire\Pages\Login as LivewireLogin;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;

#[Group('inertia')]
class InertiaRoutesTest extends TestCase
{
    protected function fortresses(): array
    {
        return [
            Fortress::make()->inertia()->basic()->twoFactor(),
        ];
    }

    public function test_page_routes_point_to_the_inertia_controllers(): void
    {
        $route = Route::getRoutes()->getByName('auth.login');

        $this->assertSame(LoginController::class, $route->getActionName());
        $this->assertSame(['GET', 'HEAD'], $route->methods());
    }

    public function test_pages_get_their_submit_endpoint(): void
    {
        $submit = Route::getRoutes()->getByName('auth.login.submit');

        $this->assertNotNull($submit);
        $this->assertSame(['POST'], $submit->methods());
        $this->assertSame('login', $submit->uri());
        $this->assertSame(LoginController::class.'@submit', $submit->getActionName());

        foreach (['auth.password.request', 'auth.password.reset', 'auth.password.confirm', 'auth.two-factor.challenge'] as $page) {
            $this->assertNotNull(Route::getRoutes()->getByName("{$page}.submit"), "{$page}.submit is not registered");
        }
    }

    public function test_the_reset_password_endpoint_is_not_behind_the_signed_middleware(): void
    {
        $page = Route::getRoutes()->getByName('auth.password.reset');
        $submit = Route::getRoutes()->getByName('auth.password.reset.submit');

        $this->assertContains('signed', $page->middleware());
        $this->assertNotContains('signed', $submit->middleware());
        $this->assertSame('reset-password', $submit->uri());
    }

    public function test_two_factor_setup_registers_all_of_its_endpoints(): void
    {
        foreach (array_keys(TwoFactorSetupController::endpoints()) as $endpoint) {
            $this->assertNotNull(
                Route::getRoutes()->getByName("auth.two-factor.setup.{$endpoint}"),
                "auth.two-factor.setup.{$endpoint} is not registered"
            );
        }

        $destroy = Route::getRoutes()->getByName('auth.two-factor.setup.trusted-devices.destroy');

        $this->assertSame(['device' => '[0-9]+'], $destroy->wheres);
        $this->assertSame(['DELETE'], $destroy->methods());
    }

    public function test_endpoints_keep_the_page_middleware(): void
    {
        $page = Route::getRoutes()->getByName('auth.two-factor.setup');
        $prepare = Route::getRoutes()->getByName('auth.two-factor.setup.prepare');

        $this->assertSame($page->middleware(), $prepare->middleware());
    }

    #[Group('livewire')]
    public function test_the_framework_can_be_chosen_after_the_preset(): void
    {
        $inertia = Fortress::make()->basic('later')->inertia();
        $livewire = Fortress::make()->inertia()->basic('first')->livewire();

        $this->assertSame(LoginController::class, $inertia->getLoginFeature()->getRouteAction());
        $this->assertSame(LivewireLogin::class, $livewire->getLoginFeature()->getRouteAction());
    }

    public function test_each_fortress_resolves_the_adapter_of_its_own_framework(): void
    {
        $this->assertInstanceOf(InertiaAdapter::class, Guardian::getDefaultFortress()->getFrameworkAdapter());
        $this->assertInstanceOf(LivewireAdapter::class, Fortress::make()->livewire()->getFrameworkAdapter());
    }

    public function test_a_custom_route_action_gets_no_bundled_endpoints(): void
    {
        $fortress = Fortress::make()->id('custom')->inertia()->login(routeAction: fn () => 'custom');

        Route::name('custom.')->group(fn () => $fortress->loginRoutes());
        Route::getRoutes()->refreshNameLookups();

        $this->assertNotNull(Route::getRoutes()->getByName('custom.auth.login'));
        $this->assertNull(Route::getRoutes()->getByName('custom.auth.login.submit'));
    }

    public function test_page_components_are_named_after_the_prefix_and_the_page(): void
    {
        $default = Fortress::make()->inertia();

        $this->assertSame('Guardian/Login', InertiaAdapter::component('login'));
        $this->assertSame('Guardian/Login', InertiaAdapter::component('login', $default));
        $this->assertSame('Guardian/SignUp', InertiaAdapter::component('sign-up', $default));
        $this->assertSame('Guardian/TwoFactorSetup', InertiaAdapter::component('two-factor-setup', $default));
        $this->assertSame('Guardian/OAuthCompleteRegistration', InertiaAdapter::component('oauth-complete-registration', $default));
    }

    public function test_a_fortress_can_change_the_prefix_of_its_components(): void
    {
        $admin = Fortress::make()->inertia(prefix: 'Admin/');

        $this->assertSame('Admin/Login', InertiaAdapter::component('login', $admin));
        $this->assertSame('Admin/SignUp', InertiaAdapter::component('sign-up', $admin));
    }

    public function test_a_fortress_can_point_single_pages_elsewhere(): void
    {
        $fortress = Fortress::make()->inertia(prefix: 'Auth', pages: ['login' => 'Custom/Entrar']);

        $this->assertSame('Custom/Entrar', InertiaAdapter::component('login', $fortress));
        $this->assertSame('Auth/SignUp', InertiaAdapter::component('sign-up', $fortress));
    }
}
