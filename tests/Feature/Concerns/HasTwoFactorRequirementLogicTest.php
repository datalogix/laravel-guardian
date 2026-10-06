<?php

namespace Datalogix\Guardian\Tests\Feature\Concerns;

use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\Login;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\Fixtures\NonModelTwoFactorUser;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;
use PragmaRX\Google2FA\Google2FA;

class HasTwoFactorRequirementLogicTest extends TestCase
{
    protected function fortresses(): array
    {
        return [Fortress::make()->basic()->twoFactor()];
    }

    public function test_requires_challenge_is_false_for_a_null_user(): void
    {
        $this->assertFalse(Guardian::requiresTwoFactorChallenge(null));
    }

    public function test_requires_setup_is_false_for_a_null_user_when_required_on_login(): void
    {
        $fortress = Fortress::make()->basic()->twoFactor(requireSetupOnLogin: true);
        Guardian::setCurrentFortress($fortress);

        $this->assertFalse(Guardian::requiresTwoFactorSetup(null));
    }

    public function test_requires_setup_is_false_when_the_setup_feature_is_disabled(): void
    {
        $fortress = Fortress::make()->basic()->twoFactor(setupRouteAction: false, requireSetupOnLogin: true);
        Guardian::setCurrentFortress($fortress);

        $this->assertFalse(Guardian::requiresTwoFactorSetup($this->createUser()));
    }

    protected function enableTwoFactorFor($user): void
    {
        $this->actingAs($user);
        session()->put('auth.password_confirmed_at', time());
        $setup = app(PrepareTwoFactorSetup::class)($user);
        $code = app(Google2FA::class)->getCurrentOtp($setup['secret']);
        app(EnableTwoFactor::class)($user, ['code' => $code]);
        $this->app['auth']->guard()->logout();
        session()->flush();
    }

    public function test_a_custom_requirement_policy_overrides_the_default_decision(): void
    {
        $user = $this->createUser();
        $this->enableTwoFactorFor($user);

        $fortress = Fortress::make()->basic()->twoFactor(requireWhen: fn ($user, $fortress, $isEnabled) => false);
        Guardian::setCurrentFortress($fortress);

        $this->assertFalse(Guardian::requiresTwoFactorChallenge($user->fresh()));
    }

    public function test_a_policy_requiring_two_factor_sends_a_user_without_it_to_set_it_up(): void
    {
        $user = $this->createUser();

        $fortress = Fortress::make()->basic()->twoFactor(requireWhen: fn ($user, $fortress, $isEnabled) => true);
        Guardian::setCurrentFortress($fortress);

        // There is no code to ask for yet, so a challenge could never be passed.
        $this->assertFalse(Guardian::requiresTwoFactorChallenge($user));
        $this->assertTrue(Guardian::requiresTwoFactorSetup($user));
    }

    public function test_a_policy_requiring_two_factor_challenges_a_user_with_it(): void
    {
        $user = $this->createUser();
        $this->enableTwoFactorFor($user);

        $fortress = Fortress::make()->basic()->twoFactor(requireWhen: fn ($user, $fortress, $isEnabled) => true);
        Guardian::setCurrentFortress($fortress);

        $this->assertTrue(Guardian::requiresTwoFactorChallenge($user->fresh()));
        $this->assertFalse(Guardian::requiresTwoFactorSetup($user->fresh()));
    }

    public function test_a_policy_leaving_the_decision_does_not_require_setup(): void
    {
        $fortress = Fortress::make()->basic()->twoFactor(requireWhen: fn ($user, $fortress, $isEnabled) => null);
        Guardian::setCurrentFortress($fortress);

        $this->assertFalse(Guardian::requiresTwoFactorSetup($this->createUser()));
    }

    public function test_a_user_required_by_policy_signs_in_through_the_setup(): void
    {
        $user = $this->createUser(['password' => bcrypt('secret')]);

        $fortress = Fortress::make()->basic()->twoFactor(requireWhen: fn ($user, $fortress, $isEnabled) => true);
        Guardian::setCurrentFortress($fortress);

        $this->assertSame(AuthFlowResult::SetupRequired, app(Login::class)(['login' => $user->email, 'password' => 'secret']));
        $this->assertFalse(Guardian::isAuthenticated());
    }

    public function test_a_recently_confirmed_grace_period_skips_the_challenge(): void
    {
        $user = $this->createUser();
        $this->enableTwoFactorFor($user);

        $fortress = Fortress::make()->basic()->twoFactor(gracePeriodDays: 7);
        Guardian::setCurrentFortress($fortress);

        $this->assertFalse(Guardian::requiresTwoFactorChallenge($user->fresh()));
    }

    public function test_get_two_factor_setup_secret_reads_the_pending_setup_session(): void
    {
        $this->assertNull(Guardian::getTwoFactorSetupSecret());

        Guardian::startTwoFactorSetup('a-secret-value');

        $this->assertSame('a-secret-value', Guardian::getTwoFactorSetupSecret());
    }

    public function test_the_grace_period_does_not_apply_without_a_confirmed_at_timestamp(): void
    {
        // A non-Model user has no confirmed-at timestamp.
        $user = new NonModelTwoFactorUser;

        $fortress = Fortress::make()->basic()->twoFactor(gracePeriodDays: 7);
        Guardian::setCurrentFortress($fortress);

        $this->assertTrue(Guardian::requiresTwoFactorChallenge($user));
    }

    protected function requiringSetupOnLogin(): array
    {
        return [Fortress::make()->basic()->twoFactor(requireSetupOnLogin: true)];
    }

    #[WithFortresses('requiringSetupOnLogin')]
    public function test_requires_setup_is_false_when_the_user_cannot_store_a_secret(): void
    {
        // No secret column and no storage contracts.
        $user = new class extends Model implements AuthenticatableContract
        {
            use Authenticatable;

            protected $table = 'oauth_identities';

            protected $guarded = [];
        };

        $this->assertFalse(Guardian::requiresTwoFactorSetup($user));
    }
}
