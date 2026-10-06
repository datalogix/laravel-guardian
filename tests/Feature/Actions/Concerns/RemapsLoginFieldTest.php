<?php

namespace Datalogix\Guardian\Tests\Feature\Actions\Concerns;

use Datalogix\Guardian\Actions\Concerns\RemapsLoginField;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;

class RemapsLoginFieldTest extends TestCase
{
    protected function harness(): object
    {
        return new class
        {
            use RemapsLoginField;

            public function remap(array $data, string $field = 'login'): array
            {
                return $this->remapLoginField($data, $field);
            }
        };
    }

    public function test_it_returns_the_data_unchanged_when_the_field_is_missing(): void
    {
        $data = ['password' => 'secret'];

        $this->assertSame($data, $this->harness()->remap($data));
    }

    public function test_it_remaps_login_to_the_configured_identifier_column(): void
    {
        $result = $this->harness()->remap(['login' => 'user@example.com']);

        $this->assertSame(['email' => 'user@example.com'], $result);
    }

    protected function identifiedByLogin(): array
    {
        return [Fortress::make()->basic()->identifierKey(IdentifierKey::Login)];
    }

    #[WithFortresses('identifiedByLogin')]
    public function test_it_leaves_the_data_unchanged_when_the_identifier_column_already_matches(): void
    {
        $harness = new class
        {
            use RemapsLoginField;

            public function remap(array $data): array
            {
                return $this->remapLoginField($data);
            }
        };

        $data = ['login' => 'jdoe-account'];

        $this->assertSame($data, $harness->remap($data));
    }
}
