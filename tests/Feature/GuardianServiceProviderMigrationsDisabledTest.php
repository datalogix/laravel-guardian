<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('socialite')]
class GuardianServiceProviderMigrationsDisabledTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor(rememberOnDevice: true)->emailVerification()->oauth(providers: ['github'])];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('guardian.migrations', false);
    }

    public function test_the_migrations_are_not_loaded_when_disabled(): void
    {
        $paths = implode('|', $this->app->make('migrator')->paths());

        $this->assertStringNotContainsString('add_two_factor_columns_to_users_table', $paths);
        $this->assertStringNotContainsString('create_two_factor_trusted_devices_table', $paths);
        $this->assertStringNotContainsString('create_oauth_identities_table', $paths);
    }
}
