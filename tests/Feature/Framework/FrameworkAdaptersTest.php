<?php

namespace Datalogix\Guardian\Tests\Feature\Framework;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Exceptions\FrameworkConfigurationException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Inertia\InertiaAdapter;
use Datalogix\Guardian\Framework\Livewire\LivewireAdapter;
use Datalogix\Guardian\Framework\Livewire\Pages\Login;
use Datalogix\Guardian\Framework\Livewire\Pages\OAuthCompleteRegistration;
use Datalogix\Guardian\Tests\Fixtures\Adapters\UninstalledInertiaAdapter;
use Datalogix\Guardian\Tests\Fixtures\Adapters\UninstalledLivewireAdapter;
use Datalogix\Guardian\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

class FrameworkAdaptersTest extends TestCase
{
    public function test_each_adapter_knows_its_framework_and_package(): void
    {
        $this->assertSame(Framework::Livewire, (new LivewireAdapter)->framework());
        $this->assertSame('livewire/livewire', (new LivewireAdapter)->package());
        $this->assertSame(Framework::Inertia, (new InertiaAdapter)->framework());
        $this->assertSame('inertiajs/inertia-laravel', (new InertiaAdapter)->package());
    }

    public function test_an_adapter_refuses_to_provide_pages_when_its_package_is_missing(): void
    {
        $this->expectException(FrameworkConfigurationException::class);
        $this->expectExceptionMessage('livewire/livewire');

        (new UninstalledLivewireAdapter)->pageAction('login');
    }

    public function test_the_inertia_adapter_names_its_package_when_it_is_missing(): void
    {
        $this->expectException(FrameworkConfigurationException::class);
        $this->expectExceptionMessage('inertiajs/inertia-laravel');

        (new UninstalledInertiaAdapter)->pageAction('login');
    }

    public function test_a_missing_livewire_leaves_the_fortress_untouched(): void
    {
        $fortress = Fortress::make()->id('plain')->middleware(['custom'], isPersistent: true);

        $adapter = new UninstalledLivewireAdapter;

        $adapter->registerFortress($fortress);
        $adapter->cacheComponents($fortress);

        $this->assertSame(['custom'], $fortress->pullPersistentMiddleware());
        $this->assertFileDoesNotExist($adapter->componentCachePath($fortress));
    }

    #[Group('livewire')]
    public function test_the_livewire_adapter_registers_every_bundled_page_component(): void
    {
        $fortress = Fortress::make()->livewire()->basic('pages')->oauth(providers: ['github']);

        $components = (new LivewireAdapter)->components($fortress);

        $this->assertContains(Login::class, $components);
        $this->assertContains(OAuthCompleteRegistration::class, $components);
        $this->assertSame([], $fortress->pullPersistentMiddleware());
    }

    #[Group('livewire')]
    public function test_the_livewire_adapter_only_registers_the_components_of_enabled_features(): void
    {
        $fortress = Fortress::make()->livewire()->id('login-only')->login();

        $this->assertSame([Login::class], array_values((new LivewireAdapter)->components($fortress)));
    }

    public function test_the_default_redirect_is_a_plain_http_redirect(): void
    {
        $adapter = new UninstalledLivewireAdapter;

        $this->assertSame(url('/to'), $adapter->redirect('/to')->getTargetUrl());

        session()->put('url.intended', url('/back-to'));
        $this->assertSame(url('/back-to'), $adapter->redirect('/to', intended: true)->getTargetUrl());
    }
}
