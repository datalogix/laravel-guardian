<?php

namespace Datalogix\Guardian\Tests\Unit\Enums;

use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Enums\OAuthEmailCollisionPolicy;
use Datalogix\Guardian\Framework\Livewire\Layout;
use PHPUnit\Framework\TestCase;

class SimpleEnumsTest extends TestCase
{
    public function test_framework_cases(): void
    {
        $this->assertSame(Framework::Inertia, Framework::from('inertia'));
        $this->assertSame(Framework::Livewire, Framework::from('livewire'));
        $this->assertNull(Framework::tryFrom('unknown'));
    }

    public function test_layout_cases_point_to_package_views(): void
    {
        $this->assertSame('guardian::layouts.simple', Layout::Simple->value);
        $this->assertSame('guardian::layouts.split', Layout::Split->value);
    }

    public function test_oauth_email_collision_policy_cases(): void
    {
        $this->assertSame([
            'link_existing', 'deny_with_error', 'require_manual_link',
        ], array_map(fn (OAuthEmailCollisionPolicy $case) => $case->value, OAuthEmailCollisionPolicy::cases()));
    }

    public function test_auth_flow_result_cases(): void
    {
        $this->assertSame([
            'challenge-required', 'setup-required', 'oauth-registration-required', 'authenticated',
        ], array_map(fn (AuthFlowResult $case) => $case->value, AuthFlowResult::cases()));
    }
}
