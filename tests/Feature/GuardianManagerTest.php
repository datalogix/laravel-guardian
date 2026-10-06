<?php

namespace Datalogix\Guardian\Tests\Feature;

use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Events\FortressBootCompleted;
use Datalogix\Guardian\Events\FortressBootFailed;
use Datalogix\Guardian\Events\FortressBootStarting;
use Datalogix\Guardian\Events\ServingGuardian;
use Datalogix\Guardian\Exceptions\FrameworkConfigurationException;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\FortressRegistry;
use Datalogix\Guardian\Framework\FrameworkResolver;
use Datalogix\Guardian\GuardianManager;
use Datalogix\Guardian\Http\Responses\LoginResponse;
use Datalogix\Guardian\Http\Responses\OAuthCompleteRegistrationResponse;
use Datalogix\Guardian\Http\Responses\TwoFactorChallengeResponse;
use Datalogix\Guardian\Http\Responses\TwoFactorSetupResponse;
use Datalogix\Guardian\Tests\Fixtures\Adapters\UninstalledLivewireAdapter;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class GuardianManagerTest extends TestCase
{
    public function test_get_fortresses_returns_registered_fortresses(): void
    {
        $this->assertCount(1, app('guardian')->getFortresses());
    }

    public function test_register_fortress_adds_it_to_the_registry(): void
    {
        app('guardian')->registerFortress(Fortress::make()->id('extra'));

        $this->assertCount(2, app('guardian')->getFortresses());
    }

    public function test_get_current_or_default_fortress_falls_back_to_default(): void
    {
        $this->assertSame('default', app('guardian')->getCurrentOrDefaultFortress()->getId());
    }

    public function test_set_and_get_current_fortress(): void
    {
        $manager = app('guardian');
        $fortress = Fortress::make()->id('secondary');

        $manager->setCurrentFortress($fortress);

        $this->assertSame($fortress, $manager->getCurrentFortress());
        $this->assertSame($fortress, $manager->getCurrentOrDefaultFortress());
    }

    public function test_reset_current_fortress_clears_it(): void
    {
        $manager = app('guardian');
        $manager->setCurrentFortress(Fortress::make()->id('secondary'));

        $manager->resetCurrentFortress();

        $this->assertNull($manager->getCurrentFortress());
    }

    public function test_reset_clears_fortress_and_domain_and_serving_status(): void
    {
        $manager = app('guardian');
        $manager->setCurrentFortress(Fortress::make()->id('secondary'));
        $manager->setCurrentDomain('example.test');
        $manager->setServingStatus();

        $manager->reset();

        $this->assertNull($manager->getCurrentFortress());
        $this->assertNull($manager->getCurrentDomain());
        $this->assertFalse($manager->isServing());
    }

    public function test_get_current_domain_returns_the_explicitly_set_domain(): void
    {
        $manager = app('guardian');
        $manager->setCurrentDomain('example.test');

        $this->assertSame('example.test', $manager->getCurrentDomain());
    }

    public function test_get_current_domain_returns_the_testing_domain_when_running_tests(): void
    {
        $this->assertSame('fallback.test', app('guardian')->getCurrentDomain('fallback.test'));
    }

    public function test_boot_current_fortress_dispatches_lifecycle_events(): void
    {
        Event::fake([FortressBootStarting::class, FortressBootCompleted::class]);

        app('guardian')->bootCurrentFortress();

        Event::assertDispatched(FortressBootStarting::class);
        Event::assertDispatched(FortressBootCompleted::class);
        Event::assertNotDispatched(FortressBootFailed::class);
    }

    public function test_boot_current_fortress_only_boots_once(): void
    {
        $manager = app('guardian');

        $manager->bootCurrentFortress();

        Event::fake([FortressBootStarting::class]);

        $manager->bootCurrentFortress();

        Event::assertNotDispatched(FortressBootStarting::class);
    }

    public function test_is_serving_and_set_serving_status(): void
    {
        $manager = app('guardian');

        $this->assertFalse($manager->isServing());

        $manager->setServingStatus();

        $this->assertTrue($manager->isServing());

        $manager->setServingStatus(false);

        $this->assertFalse($manager->isServing());
    }

    public function test_serving_registers_a_listener_for_the_serving_guardian_event(): void
    {
        $called = false;
        app('guardian')->serving(function () use (&$called) {
            $called = true;
        });

        event(new ServingGuardian(app('guardian')->getCurrentOrDefaultFortress()));

        $this->assertTrue($called);
    }

    public function test_respond_to_auth_flow_resolves_the_correct_response_for_each_result(): void
    {
        $manager = app('guardian');

        // Populates the two-factor/OAuth features' response classes on the current
        // fortress; `basic()` alone never configures them since it enables neither feature.
        $manager->twoFactor();
        $manager->oauth();

        $this->assertInstanceOf(LoginResponse::class, $manager->respondToAuthFlow(AuthFlowResult::Authenticated, LoginResponse::class));
        $this->assertInstanceOf(TwoFactorChallengeResponse::class, $manager->respondToAuthFlow(AuthFlowResult::ChallengeRequired, LoginResponse::class));
        $this->assertInstanceOf(TwoFactorSetupResponse::class, $manager->respondToAuthFlow(AuthFlowResult::SetupRequired, LoginResponse::class));
        $this->assertInstanceOf(OAuthCompleteRegistrationResponse::class, $manager->respondToAuthFlow(AuthFlowResult::OAuthRegistrationRequired, LoginResponse::class));
    }

    public function test_calls_are_forwarded_to_the_current_fortress(): void
    {
        $this->assertSame('default', app('guardian')->getId());
    }

    public function test_manager_is_resolved_as_a_scoped_singleton(): void
    {
        $this->assertSame(app('guardian'), app('guardian'));
        $this->assertInstanceOf(GuardianManager::class, app('guardian'));
    }

    public function test_guardian_helper_returns_the_guardian_manager_singleton(): void
    {
        $this->assertInstanceOf(GuardianManager::class, guardian());
        $this->assertSame(guardian(), guardian());
        $this->assertSame(app('guardian'), guardian());
    }

    public function test_boot_current_fortress_dispatches_failed_event_and_rethrows(): void
    {
        app(FrameworkResolver::class)->register(new UninstalledLivewireAdapter);
        app(FortressRegistry::class)->register(Fortress::make()->livewire()->basic('broken'));

        Event::fake([FortressBootFailed::class]);

        try {
            app('guardian')->bootCurrentFortress();
            $this->fail('Expected boot to throw because the Livewire package is not installed.');
        } catch (FrameworkConfigurationException) {
            // expected
        }

        Event::assertDispatched(FortressBootFailed::class);
    }
}
