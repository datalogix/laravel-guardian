<?php

namespace Datalogix\Guardian\Tests\Feature\TwoFactor;

use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\TwoFactor\Totp;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorChallengeVerifier;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeVerifierTest extends TestCase
{
    protected function fortress()
    {
        return Guardian::getCurrentOrDefaultFortress();
    }

    protected function enableTwoFactorFor($user, string $secret): void
    {
        app(TwoFactorUser::class)->saveTwoFactorSecret($user, $this->fortress(), $secret);
    }

    public function test_a_valid_totp_code_is_accepted(): void
    {
        $secret = app(Totp::class)->generateSecret();
        $user = $this->createUser();
        $this->enableTwoFactorFor($user, $secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $result = app(TwoFactorChallengeVerifier::class)->verify($user->fresh(), $this->fortress(), $code);

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->usedRecoveryCode());
    }

    public function test_the_same_totp_code_cannot_be_replayed(): void
    {
        $secret = app(Totp::class)->generateSecret();
        $user = $this->createUser();
        $this->enableTwoFactorFor($user, $secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $verifier = app(TwoFactorChallengeVerifier::class);

        $first = $verifier->verify($user->fresh(), $this->fortress(), $code);
        $second = $verifier->verify($user->fresh(), $this->fortress(), $code);

        $this->assertTrue($first->isValid());
        $this->assertFalse($second->isValid());
    }

    public function test_a_recovery_code_is_accepted_when_totp_fails(): void
    {
        $secret = app(Totp::class)->generateSecret();
        $user = $this->createUser();
        $this->enableTwoFactorFor($user, $secret);
        app(TwoFactorUser::class)->saveTwoFactorRecoveryCodes($user, $this->fortress(), ['recovery-code-1']);

        $result = app(TwoFactorChallengeVerifier::class)->verify($user->fresh(), $this->fortress(), 'recovery-code-1');

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->usedRecoveryCode());
    }

    public function test_invalid_code_is_rejected(): void
    {
        $secret = app(Totp::class)->generateSecret();
        $user = $this->createUser();
        $this->enableTwoFactorFor($user, $secret);

        $result = app(TwoFactorChallengeVerifier::class)->verify($user->fresh(), $this->fortress(), '000000');

        $this->assertFalse($result->isValid());
        $this->assertFalse($result->usedRecoveryCode());
    }

    public function test_the_same_totp_code_sent_twice_at_the_same_time_signs_in_once(): void
    {
        $secret = app(Totp::class)->generateSecret();
        $user = $this->createUser();
        $this->enableTwoFactorFor($user, $secret);

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $verifier = app(TwoFactorChallengeVerifier::class);
        $lastUsed = implode(':', ['guardian:two-factor:totp-last-used', $this->fortress()->getId(), $user::class, $user->getKey()]);

        $this->assertTrue($verifier->verify($user->fresh(), $this->fortress(), $code)->isValid());

        // The second request read the last used step before the first one stored it.
        Cache::forget($lastUsed);

        $this->assertFalse($verifier->verify($user->fresh(), $this->fortress(), $code)->isValid());
    }
}
