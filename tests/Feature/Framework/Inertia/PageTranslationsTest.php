<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Framework\Inertia\Controllers\LoginController;
use Datalogix\Guardian\Framework\Inertia\PageTranslations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Inertia\ResponseFactory;
use PHPUnit\Framework\Attributes\Group;

#[Group('inertia')]
class PageTranslationsTest extends InertiaTestCase
{
    public function test_the_pages_get_their_lines_in_the_language_of_the_request(): void
    {
        app()->setLocale('pt_BR');

        $translations = $this->inertiaGet('/login')->json('props.translations');

        $this->assertSame('Entrar', $translations['Sign in']);
        $this->assertSame('Lembrar deste dispositivo por :days dias', $translations['Remember this device for :days days']);
    }

    public function test_nothing_is_sent_for_a_language_whose_lines_stay_the_same(): void
    {
        app()->setLocale('en');

        $this->assertSame([], $this->inertiaGet('/login')->json('props.translations'));
    }

    public function test_the_lines_the_application_translates_itself_win(): void
    {
        app()->setLocale('pt_BR');
        Lang::addLines(['*.Sign in' => 'Acessar'], 'pt_BR');

        $this->assertSame('Acessar', $this->inertiaGet('/login')->json('props.translations')['Sign in']);
    }

    public function test_a_language_the_application_adds_is_followed(): void
    {
        app()->setLocale('es');
        Lang::addLines(['*.Sign in' => 'Iniciar sesión'], 'es');

        $this->assertSame(['Sign in' => 'Iniciar sesión'], $this->inertiaGet('/login')->json('props.translations'));
    }

    public function test_only_the_lines_the_pages_show_are_sent(): void
    {
        app()->setLocale('pt_BR');

        $translations = $this->inertiaGet('/login')->json('props.translations');

        $this->assertArrayHasKey('Sign in', $translations);
        // A line of the e-mails, which no page shows.
        $this->assertArrayNotHasKey('Your two-factor authentication code', $translations);
    }

    protected function requireOnceProps(): void
    {
        if (! method_exists(ResponseFactory::class, 'once')) {
            $this->markTestSkipped('Once props need Inertia 3.');
        }
    }

    public function test_a_browser_that_has_the_lines_of_a_language_does_not_get_them_again(): void
    {
        $this->requireOnceProps();
        app()->setLocale('pt_BR');

        $first = $this->inertiaGet('/login');
        $this->assertArrayHasKey('guardian.translations.pt_BR', $first->json('onceProps'));

        $again = $this->get('/login', ['X-Inertia' => 'true', 'X-Inertia-Except-Once-Props' => 'guardian.translations.pt_BR']);

        $this->assertArrayNotHasKey('translations', $again->json('props'));
    }

    public function test_a_browser_switching_language_gets_the_lines_of_the_new_one(): void
    {
        $this->requireOnceProps();
        app()->setLocale('es');
        Lang::addLines(['*.Sign in' => 'Iniciar sesión'], 'es');

        $response = $this->get('/login', ['X-Inertia' => 'true', 'X-Inertia-Except-Once-Props' => 'guardian.translations.pt_BR']);

        $this->assertSame(['Sign in' => 'Iniciar sesión'], $response->json('props.translations'));
    }

    public function test_without_once_props_every_page_gets_the_lines(): void
    {
        // What Inertia 2 does, since once props came with Inertia 3.
        app()->setLocale('pt_BR');
        $request = Request::create('/login', 'GET', server: ['HTTP_X_INERTIA' => 'true']);
        $controller = new class extends LoginController
        {
            protected function supportsOnceProps(): bool
            {
                return false;
            }
        };

        $page = $controller($request)->toResponse($request)->getData(true);

        $this->assertSame('Entrar', $page['props']['translations']['Sign in']);
        $this->assertArrayNotHasKey('guardian.translations.pt_BR', $page['onceProps'] ?? []);
    }

    public function test_an_application_adds_the_lines_of_the_pages_it_edits(): void
    {
        app()->setLocale('pt_BR');
        // The language's lang files load first, as in an application.
        __('Sign in');
        Lang::addLines(['*.Welcome back' => 'Bem-vindo de volta'], 'pt_BR');
        $this->app->bind(PageTranslations::class, fn () => new class extends PageTranslations
        {
            protected static function lines(): array
            {
                return [...parent::lines(), 'Welcome back'];
            }
        });

        $translations = $this->inertiaGet('/login')->json('props.translations');

        $this->assertSame('Bem-vindo de volta', $translations['Welcome back']);
        $this->assertSame('Entrar', $translations['Sign in']);
    }
}
