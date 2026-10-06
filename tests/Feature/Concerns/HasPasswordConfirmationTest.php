<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\TestCase;

class HasPasswordConfirmationTest extends TestCase
{
    public function test_middleware_and_url_are_null_when_the_feature_is_disabled(): void
    {
        $fortress = Fortress::make()->id('example');

        $this->assertNull($fortress->getPasswordConfirmationMiddleware());
        $this->assertNull($fortress->passwordConfirm());
        $this->assertNull($fortress->passwordConfirmationUrl());
    }

    public function test_middleware_and_url_are_available_when_the_feature_is_enabled(): void
    {
        $fortress = Fortress::make()->basic();

        $this->assertSame('password.confirm:auth.password.confirm', $fortress->getPasswordConfirmationMiddleware());
        $this->assertSame($fortress->getPasswordConfirmationMiddleware(), $fortress->passwordConfirm());
        $this->assertStringContainsString('/confirm-password', $fortress->passwordConfirmationUrl());
    }

    public function test_middleware_name_can_be_customized(): void
    {
        $fortress = Fortress::make()->basic()->passwordConfirmation(middlewareName: 'custom.confirm');

        $this->assertSame('custom.confirm', $fortress->getPasswordConfirmationMiddlewareName());
    }
}
