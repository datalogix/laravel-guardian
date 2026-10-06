<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;

class GuardianServiceProviderTrustedDevicesTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor(rememberOnDevice: true)];
    }

    public function test_trusted_devices_migration_is_loaded(): void
    {
        $paths = implode('|', $this->app->make('migrator')->paths());

        $this->assertStringContainsString('add_two_factor_columns_to_users_table', $paths);
        $this->assertStringContainsString('create_two_factor_trusted_devices_table', $paths);
    }

    public function test_trusted_device_pruning_is_scheduled_daily(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? '')
            ->implode('|');

        $this->assertStringContainsString('guardian:prune-trusted-devices', $events);
    }
}
