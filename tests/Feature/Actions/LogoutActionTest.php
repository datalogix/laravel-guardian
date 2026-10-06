<?php

namespace Datalogix\Guardian\Tests\Feature\Actions;

use Datalogix\Guardian\Actions\Logout;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;

class LogoutActionTest extends TestCase
{
    public function test_it_logs_the_user_out(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->assertTrue(Guardian::isAuthenticated());

        app(Logout::class)();

        $this->assertFalse(Guardian::isAuthenticated());
    }

    public function test_it_regenerates_the_session_token(): void
    {
        $this->actingAs($this->createUser());

        $originalToken = session()->token();

        app(Logout::class)();

        $this->assertNotSame($originalToken, session()->token());
    }
}
