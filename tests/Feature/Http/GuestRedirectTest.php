<?php

namespace Datalogix\Guardian\Tests\Feature\Http;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Support\Auth\GuestRedirect;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

class GuestRedirectTest extends TestCase
{
    protected function defineDashboard(): void
    {
        Route::middleware(['web', 'auth'])->get('/dashboard', fn () => 'dashboard');
    }

    public function test_a_guest_of_an_application_route_is_sent_to_the_login_page(): void
    {
        $this->defineDashboard();

        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_the_page_the_guest_asked_for_is_kept_for_after_the_login(): void
    {
        $this->defineDashboard();

        $this->get('/dashboard');

        $this->assertSame(url('/dashboard'), session('url.intended'));
    }

    public function test_a_json_request_still_gets_a_401(): void
    {
        $this->defineDashboard();

        $this->getJson('/dashboard')->assertUnauthorized();
    }

    public function test_a_destination_chosen_by_the_application_is_kept(): void
    {
        // What redirectGuestsTo() in bootstrap/app.php does, before Guardian boots.
        Authenticate::redirectUsing(fn () => '/entrar');
        GuestRedirect::wrap();

        $this->defineDashboard();

        $this->get('/dashboard')->assertRedirect('/entrar');
    }

    public function test_a_login_route_of_the_application_is_kept(): void
    {
        Route::get('/my-login', fn () => 'login')->name('login');
        Route::getRoutes()->refreshNameLookups();
        $this->defineDashboard();

        $this->get('/dashboard')->assertRedirect('/my-login');
    }

    public function test_every_class_laravel_gives_the_destination_to_is_wrapped(): void
    {
        foreach ([Authenticate::class, AuthenticateSession::class, AuthenticationException::class] as $class) {
            $callback = (fn () => static::$redirectToCallback)->bindTo(null, $class)();

            $this->assertInstanceOf(GuestRedirect::class, $callback, $class);
            $this->assertSame(url('/login'), $callback(Request::create('/dashboard')), $class);
        }
    }

    public function test_wrapping_again_does_not_wrap_the_wrapper(): void
    {
        $before = (fn () => static::$redirectToCallback)->bindTo(null, Authenticate::class)();

        GuestRedirect::wrap();

        $this->assertSame($before, (fn () => static::$redirectToCallback)->bindTo(null, Authenticate::class)());
    }

    protected function withoutLogin(): array
    {
        return [Fortress::make()->basic()->login(false)];
    }

    #[WithFortresses('withoutLogin')]
    public function test_without_a_login_page_the_missing_route_still_fails(): void
    {
        $this->expectException(RouteNotFoundException::class);

        (new GuestRedirect(fn () => route('login')))(Request::create('/dashboard'));
    }
}
