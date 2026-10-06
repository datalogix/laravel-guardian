<?php

namespace Datalogix\Guardian\Tests\Feature\Framework;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\FortressRegistry;
use Datalogix\Guardian\Framework\FrameworkResolver;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Middleware\Authenticate;
use Datalogix\Guardian\Response\Redirector;
use Datalogix\Guardian\Tests\Fixtures\Adapters\UninstalledInertiaAdapter;
use Datalogix\Guardian\Tests\Fixtures\Adapters\UninstalledLivewireAdapter;
use Datalogix\Guardian\Tests\TestCase;

class HeadlessFortressTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $resolver = $app->make(FrameworkResolver::class);
        $resolver->register(new UninstalledLivewireAdapter);
        $resolver->register(new UninstalledInertiaAdapter);

        parent::getEnvironmentSetUp($app);
    }

    protected function fortresses(): array
    {
        return [
            Fortress::make()
                ->id('default')->default()
                ->middleware(['web'])
                ->authMiddleware([Authenticate::class])
                ->login(routeAction: fn () => 'my own login page')
                ->logout(),
        ];
    }

    public function test_the_app_boots_and_validates_without_any_front_end(): void
    {
        app(FortressRegistry::class)->validate();

        $this->assertFalse(Guardian::getDefaultFortress()->getFrameworkAdapter()->isInstalled());
    }

    public function test_the_custom_login_page_is_served(): void
    {
        $this->get(Guardian::getLoginFeature()->getUrl())->assertOk()->assertSee('my own login page');
    }

    public function test_the_redirector_falls_back_to_plain_http_redirects(): void
    {
        $this->assertSame(url('/somewhere'), Redirector::redirect('/somewhere')->getTargetUrl());
        $this->assertSame(Guardian::getLoginFeature()->getUrl(), Redirector::redirectToLogin()->getTargetUrl());
    }

    public function test_logout_redirects_to_the_login(): void
    {
        $this->actingAs($this->createUser());

        $this->post(Guardian::getLogoutFeature()->getUrl())->assertRedirect(Guardian::getLoginFeature()->getUrl());

        $this->assertGuest();
    }
}
