<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\TestCase;
use InvalidArgumentException;

class HasOAuthNormalizeProvidersTest extends TestCase
{
    public function test_it_rejects_a_blank_provider_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('index [1]');

        Fortress::make()->oauth(providers: ['github', '']);
    }

    public function test_it_rejects_a_non_string_provider_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value at index [0]');

        Fortress::make()->oauth(providers: [123]);
    }
}
