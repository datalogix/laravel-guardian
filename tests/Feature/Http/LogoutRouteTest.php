<?php

namespace Datalogix\Guardian\Tests\Feature\Http;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Signing out works from a plain link (GET) as well as from a form (POST).
 */
class LogoutRouteTest extends TestCase
{
    public static function methods(): array
    {
        return [
            'a link' => ['GET'],
            'a form' => ['POST'],
        ];
    }

    #[DataProvider('methods')]
    public function test_the_user_is_signed_out(string $method): void
    {
        $this->actingAs($this->createUser());

        $this->call($method, Guardian::getLogoutFeature()->getUrl())
            ->assertRedirect(Guardian::getLoginFeature()->getUrl());

        $this->assertGuest();
    }
}
