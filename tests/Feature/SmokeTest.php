<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\FortressRegistry;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class SmokeTest extends TestCase
{
    public function test_application_boots_with_a_default_fortress_registered(): void
    {
        $this->assertCount(1, Guardian::getFortresses());
        $this->assertTrue(Guardian::getDefaultFortress()->isDefault());
        $this->assertSame('default', Guardian::getDefaultFortress()->getId());
    }

    public function test_users_table_migration_ran(): void
    {
        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_login_route_is_registered(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_fortress_registry_is_resolved_as_singleton(): void
    {
        $this->assertSame(app(FortressRegistry::class), app(FortressRegistry::class));
    }
}
