<?php

namespace Datalogix\Guardian\Tests\Feature\Framework;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Framework\FrameworkResolver;
use Datalogix\Guardian\Framework\Inertia\Controllers\LoginController;
use Datalogix\Guardian\Framework\Inertia\InertiaAdapter;
use Datalogix\Guardian\Framework\Livewire\LivewireAdapter;
use Datalogix\Guardian\Framework\Livewire\Pages\Login;
use Datalogix\Guardian\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;

class FrameworkResolverTest extends TestCase
{
    #[Group('livewire')]
    #[Group('inertia')]
    public function test_resolve_component_uses_the_given_framework(): void
    {
        $resolver = app(FrameworkResolver::class);

        $this->assertSame(Login::class, $resolver->resolveComponent('login', Framework::Livewire));
        $this->assertSame(LoginController::class, $resolver->resolveComponent('login', Framework::Inertia));
    }

    #[Group('livewire')]
    #[Group('inertia')]
    public function test_resolve_component_falls_back_to_the_config_default(): void
    {
        config(['guardian.framework' => Framework::Livewire]);

        $resolver = app(FrameworkResolver::class);

        $this->assertSame(Login::class, $resolver->resolveComponent('login'));

        config(['guardian.framework' => Framework::Inertia]);

        $this->assertSame(LoginController::class, $resolver->resolveComponent('login'));
    }

    #[Group('inertia')]
    public function test_resolve_component_accepts_the_framework_as_a_config_string(): void
    {
        config(['guardian.framework' => 'inertia']);

        $this->assertInstanceOf(InertiaAdapter::class, app(FrameworkResolver::class)->adapter());
    }

    public function test_resolve_component_throws_for_an_unregistered_framework(): void
    {
        $resolver = new FrameworkResolver;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No framework adapter registered for framework [livewire].');

        $resolver->resolveComponent('login', Framework::Livewire);
    }

    public function test_resolve_component_throws_when_no_framework_can_be_determined(): void
    {
        config(['guardian.framework' => 'not-a-real-framework']);

        $resolver = app(FrameworkResolver::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown framework configured');

        $resolver->resolveComponent('login');
    }

    #[Group('livewire')]
    public function test_resolve_component_throws_for_an_unknown_page(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown component [nope].');

        app(FrameworkResolver::class)->resolveComponent('nope', Framework::Livewire);
    }

    public function test_register_adds_an_adapter_for_its_framework(): void
    {
        $resolver = new FrameworkResolver;
        $adapter = new class extends LivewireAdapter
        {
            public function pageAction(string $page): string
            {
                return 'resolved:'.$page;
            }
        };

        $resolver->register($adapter);

        $this->assertSame($adapter, $resolver->adapter(Framework::Livewire));
        $this->assertSame(['livewire' => $adapter], $resolver->adapters());
        $this->assertSame('resolved:login', $resolver->resolveComponent('login', Framework::Livewire));
    }

    #[Group('inertia')]
    public function test_the_service_provider_registers_both_adapters(): void
    {
        $adapters = app(FrameworkResolver::class)->adapters();

        $this->assertInstanceOf(LivewireAdapter::class, $adapters['livewire']);
        $this->assertInstanceOf(InertiaAdapter::class, $adapters['inertia']);
    }
}
