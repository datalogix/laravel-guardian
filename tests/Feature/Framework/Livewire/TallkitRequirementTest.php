<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Livewire;

use Datalogix\Guardian\Exceptions\FrameworkConfigurationException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\FortressRegistry;
use Datalogix\Guardian\Framework\Inertia\InertiaAdapter;
use Datalogix\Guardian\Framework\Livewire\LivewireAdapter;
use Datalogix\Guardian\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

#[Group('livewire')]
class TallkitRequirementTest extends TestCase
{
    protected function useTheBundledViews(): void
    {
        $this->app['view']->replaceNamespace('guardian', dirname(__DIR__, 4).'/src/Framework/Livewire/resources/views');
        // The finder keeps the views it already found, with the test ones.
        $this->app['view']->getFinder()->flush();
    }

    protected function validate(Fortress $fortress): void
    {
        app(LivewireAdapter::class)->validateFortress($fortress);
    }

    public function test_the_bundled_views_need_tallkit(): void
    {
        $this->useTheBundledViews();

        $this->expectException(FrameworkConfigurationException::class);
        $this->expectExceptionMessage(FrameworkConfigurationException::tallkitMissing('default')->getMessage());

        $this->validate(Fortress::make()->livewire()->basic());
    }

    public function test_the_application_does_not_boot_with_them(): void
    {
        $this->useTheBundledViews();
        $registry = new FortressRegistry;
        $registry->register(Fortress::make()->livewire()->basic());

        $this->expectException(FrameworkConfigurationException::class);

        $registry->validate();
    }

    public function test_published_views_do_not(): void
    {
        $this->validate(Fortress::make()->livewire()->basic());

        $this->assertTrue(true);
    }

    public function test_views_and_a_layout_of_the_fortress_do_not(): void
    {
        $this->useTheBundledViews();

        $this->validate(
            Fortress::make()->livewire(views: 'guardian-tests::guardian')->basic()->layout('guardian-tests::guardian.layouts.simple')
        );

        $this->assertTrue(true);
    }

    public function test_views_of_the_fortress_with_the_bundled_layout_do(): void
    {
        $this->useTheBundledViews();

        $this->expectException(FrameworkConfigurationException::class);

        $this->validate(Fortress::make()->livewire(views: 'guardian-tests::guardian')->basic());
    }

    public function test_an_inertia_fortress_is_not_concerned(): void
    {
        $this->useTheBundledViews();

        app(InertiaAdapter::class)->validateFortress(Fortress::make()->inertia()->basic());

        $this->assertTrue(true);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_the_bundled_views_are_fine_with_tallkit_installed(): void
    {
        require_once dirname(__DIR__, 3).'/Fixtures/stubs/TALLKitServiceProvider.php';
        $this->useTheBundledViews();

        $this->validate(Fortress::make()->livewire()->basic());

        $this->assertTrue(true);
    }
}
