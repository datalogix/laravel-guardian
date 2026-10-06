<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;

class GuardianServiceProviderNoTwoFactorTest extends TestCase
{
    public function test_two_factor_and_oauth_migrations_are_not_loaded_when_unused(): void
    {
        $paths = implode('|', $this->app->make('migrator')->paths());

        $this->assertStringNotContainsString('add_two_factor_columns_to_users_table', $paths);
        $this->assertStringNotContainsString('create_two_factor_trusted_devices_table', $paths);
        $this->assertStringNotContainsString('create_oauth_identities_table', $paths);
    }

    public function test_trusted_device_pruning_is_not_scheduled(): void
    {
        $events = collect($this->app->make(Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? $event->description ?? '')
            ->implode('|');

        $this->assertStringNotContainsString('guardian:prune-trusted-devices', $events);
    }
}
