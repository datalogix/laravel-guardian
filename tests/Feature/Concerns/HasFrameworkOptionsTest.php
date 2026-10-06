<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\Inertia\InertiaAdapter;
use Datalogix\Guardian\Tests\TestCase;

class HasFrameworkOptionsTest extends TestCase
{
    public function test_a_fortress_has_no_framework_options_by_default(): void
    {
        $this->assertSame([], Fortress::make()->getFrameworkOptions());
        $this->assertNull(Fortress::make()->getFrameworkOption('prefix'));
        $this->assertSame('fallback', Fortress::make()->getFrameworkOption('prefix', 'fallback'));
    }

    public function test_inertia_keeps_its_options_on_the_fortress(): void
    {
        $fortress = Fortress::make()->inertia(prefix: 'Admin', pages: ['login' => 'Auth/Entrar']);

        $this->assertSame(Framework::Inertia, $fortress->getFramework());
        $this->assertSame(['prefix' => 'Admin', 'pages' => ['login' => 'Auth/Entrar']], $fortress->getFrameworkOptions());
        $this->assertSame('Admin', $fortress->getFrameworkOption('prefix'));
    }

    public function test_livewire_keeps_its_options_on_the_fortress(): void
    {
        $fortress = Fortress::make()->livewire(views: 'admin.auth');

        $this->assertSame(Framework::Livewire, $fortress->getFramework());
        $this->assertSame(['views' => 'admin.auth'], $fortress->getFrameworkOptions());
    }

    public function test_options_that_were_not_given_are_not_kept(): void
    {
        $this->assertSame([], Fortress::make()->inertia()->getFrameworkOptions());
        $this->assertSame([], Fortress::make()->livewire()->getFrameworkOptions());
    }

    public function test_choosing_another_framework_replaces_the_options(): void
    {
        $this->assertSame([], Fortress::make()->inertia(prefix: 'Admin')->livewire()->getFrameworkOptions());
        $this->assertSame([], Fortress::make()->livewire(views: 'admin.auth')->inertia()->getFrameworkOptions());
    }

    public function test_the_options_can_be_given_after_a_preset(): void
    {
        $fortress = Fortress::make()->basic('admin')->inertia(prefix: 'Admin');

        $this->assertSame('Admin', $fortress->getFrameworkOption('prefix'));
        $this->assertSame('Admin/Login', InertiaAdapter::component('login', $fortress));
    }
}
