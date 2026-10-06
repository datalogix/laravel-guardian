<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\TestCase;

class FeatureUrlGettersTest extends TestCase
{
    public function test_login_url_returns_the_login_route_url(): void
    {
        $fortress = Fortress::make()->basic();

        $this->assertStringContainsString('/login', $fortress->loginUrl());
    }

    public function test_logout_url_returns_the_logout_route_url(): void
    {
        $fortress = Fortress::make()->basic();

        $this->assertStringContainsString('/logout', $fortress->logoutUrl());
    }

    public function test_sign_up_url_is_null_when_the_feature_is_disabled(): void
    {
        $fortress = Fortress::make()->basic();

        $this->assertNull($fortress->signUpUrl());
    }

    public function test_get_framework_resolves_a_string_config_value_to_the_enum(): void
    {
        config(['guardian.framework' => 'livewire']);

        $this->assertSame(Framework::Livewire, Fortress::make()->getFramework());
    }

    public function test_get_framework_falls_back_to_livewire_for_an_unknown_string_value(): void
    {
        config(['guardian.framework' => 'not-a-real-framework']);

        $this->assertSame(Framework::Livewire, Fortress::make()->getFramework());
    }

    protected function withSignUp(): array
    {
        return [Fortress::make()->product()->default()];
    }

    #[WithFortresses('withSignUp')]
    public function test_sign_up_url_returns_the_sign_up_route_url(): void
    {
        $this->assertStringContainsString('/sign-up', Guardian::signUpUrl());
    }
}
