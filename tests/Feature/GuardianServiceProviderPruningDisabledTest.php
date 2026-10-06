<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;

class GuardianServiceProviderPruningDisabledTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor(rememberOnDevice: true)];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('guardian.prune_trusted_devices.enabled', false);
    }

    public function test_trusted_device_pruning_is_not_scheduled_when_disabled(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? '')
            ->implode('|');

        $this->assertStringNotContainsString('guardian:prune-trusted-devices', $events);
    }

    public function test_the_command_is_still_available_to_schedule_by_hand(): void
    {
        $this->assertArrayHasKey('guardian:prune-trusted-devices', $this->app['Illuminate\Contracts\Console\Kernel']->all());
    }
}
