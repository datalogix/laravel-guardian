<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Actions\Concerns\HasRateLimiter;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\TwoFactorChallengeException;
use Datalogix\Guardian\Exceptions\UnsupportedAuthGuardException;
use Datalogix\Guardian\Features\TwoFactorChallengeFeature;
use Datalogix\Guardian\Features\TwoFactorSetupFeature;
use Datalogix\Guardian\Support\Auth\PostAuthenticationFlow;
use Datalogix\Guardian\Support\TwoFactor\Totp;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorDeliveryManager;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorSessionManager;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorTrustedDeviceManager;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

trait HasTwoFactor
{
    use HasRateLimiter;

    protected ?TwoFactorChallengeFeature $twoFactorChallengeFeature = null;

    protected ?TwoFactorSetupFeature $twoFactorSetupFeature = null;

    protected int|false|null $twoFactorChallengeTtl = null;

    protected int|false|null $twoFactorSetupTtl = null;

    protected int|false|null $twoFactorChallengeResendMaxAttempts = null;

    protected int|false|null $twoFactorChallengeResendDecaySeconds = null;

    protected ?bool $twoFactorRememberOnDevice = null;

    protected ?int $twoFactorRememberForDays = null;

    protected ?Closure $twoFactorRequirementPolicy = null;

    protected ?int $twoFactorGracePeriodDays = null;

    protected ?bool $twoFactorRequireSetupOnLogin = null;

    protected ?TwoFactorMethod $twoFactorMethod = null;

    protected ?Closure $twoFactorSendEmailCodeUsing = null;

    protected ?Closure $twoFactorSendSmsCodeUsing = null;

    protected ?Closure $twoFactorResolveSmsRecipientUsing = null;

    public function getTwoFactorChallengeFeature(): TwoFactorChallengeFeature
    {
        return $this->twoFactorChallengeFeature ??= new TwoFactorChallengeFeature($this);
    }

    public function getTwoFactorSetupFeature(): TwoFactorSetupFeature
    {
        return $this->twoFactorSetupFeature ??= new TwoFactorSetupFeature($this);
    }

    public function hasAnyTwoFactorFeature(): bool
    {
        return $this->getTwoFactorChallengeFeature()->hasFeature()
            || $this->getTwoFactorSetupFeature()->hasFeature();
    }

    public function twoFactor(
        string|Closure|array|false|null $challengeRouteAction = null,
        ?string $challengeRouteSlug = null,
        ?string $challengeRouteName = null,
        string|Closure|null $challengeResponse = null,
        int|false|null $challengeMaxAttempts = null,
        Layout|string|null $challengeLayout = null,
        int|false|null $challengeTtl = null,
        int|false|null $challengeResendMaxAttempts = null,
        int|false|null $challengeResendDecaySeconds = null,
        ?bool $rememberOnDevice = null,
        ?int $rememberForDays = null,
        ?Closure $requireWhen = null,
        ?int $gracePeriodDays = null,
        string|Closure|array|false|null $setupRouteAction = null,
        ?string $setupRouteSlug = null,
        ?string $setupRouteName = null,
        string|Closure|null $setupResponse = null,
        int|false|null $setupMaxAttempts = null,
        Layout|string|null $setupLayout = null,
        int|false|null $setupTtl = null,
        ?bool $requireSetupOnLogin = null,
        ?TwoFactorMethod $method = null,
        ?Closure $sendEmailCodeUsing = null,
        ?Closure $sendSmsCodeUsing = null,
        ?Closure $resolveSmsRecipientUsing = null,
    ): static {
        $this->getTwoFactorChallengeFeature()->configure(
            $challengeRouteAction,
            $challengeRouteSlug,
            $challengeRouteName,
            $challengeResponse,
            $challengeMaxAttempts,
            $challengeLayout,
        );

        $this->getTwoFactorSetupFeature()->configure(
            $setupRouteAction,
            $setupRouteSlug,
            $setupRouteName,
            $setupResponse,
            $setupMaxAttempts,
            $setupLayout,
        );

        $this->twoFactorChallengeTtl = $challengeTtl ?? 600;
        $this->twoFactorChallengeResendMaxAttempts = $challengeResendMaxAttempts ?? 3;
        $this->twoFactorChallengeResendDecaySeconds = $challengeResendDecaySeconds ?? 300;
        $this->twoFactorRememberOnDevice = $rememberOnDevice ?? false;
        $this->twoFactorRememberForDays = $rememberForDays ?? 30;
        $this->twoFactorRequirementPolicy = $requireWhen;
        $this->twoFactorGracePeriodDays = $gracePeriodDays;
        $this->twoFactorSetupTtl = $setupTtl ?? 600;
        $this->twoFactorRequireSetupOnLogin = $requireSetupOnLogin ?? false;
        $this->twoFactorMethod = $method ?? TwoFactorMethod::Totp;
        $this->twoFactorSendEmailCodeUsing = $sendEmailCodeUsing;
        $this->twoFactorSendSmsCodeUsing = $sendSmsCodeUsing;
        $this->twoFactorResolveSmsRecipientUsing = $resolveSmsRecipientUsing;

        return $this;
    }

    public function requiresTwoFactorChallenge(?Authenticatable $user): bool
    {
        if (! $this->getTwoFactorChallengeFeature()->hasFeature()) {
            return false;
        }

        if (! $user) {
            return false;
        }

        $twoFactorUser = app(TwoFactorUser::class);
        $isEnabled = $twoFactorUser->hasTwoFactorEnabled($user, $this);
        $policyResult = $this->twoFactorRequirementPolicy
            ? ($this->twoFactorRequirementPolicy)($user, $this, $isEnabled)
            : null;

        $requiresChallenge = is_bool($policyResult) ? $policyResult : $isEnabled;

        if (! $requiresChallenge) {
            return false;
        }

        if ($isEnabled && $this->isWithinTwoFactorGracePeriod($user, $twoFactorUser)) {
            return false;
        }

        if ($this->shouldSkipTwoFactorForRememberedDevice($user)) {
            return false;
        }

        return true;
    }

    public function requiresTwoFactorSetup(?Authenticatable $user): bool
    {
        if (! $this->shouldRequireTwoFactorSetupOnLogin()) {
            return false;
        }

        if (! $this->getTwoFactorSetupFeature()->hasFeature()) {
            return false;
        }

        if (! $user) {
            return false;
        }

        $twoFactorUser = app(TwoFactorUser::class);

        if (! $twoFactorUser->canStoreTwoFactorSecret($user)) {
            return false;
        }

        return ! $twoFactorUser->hasTwoFactorEnabled($user, $this);
    }

    public function shouldRequireTwoFactorSetupOnLogin(): bool
    {
        return (bool) $this->twoFactorRequireSetupOnLogin;
    }

    public function startTwoFactorChallenge(Authenticatable $user, bool $remember = true): void
    {
        $manager = app(TwoFactorUser::class);
        $method = $manager->getTwoFactorMethod($user, $this);

        $this->twoFactorSessionManager()->startChallenge($this, $user, $remember, $method);

        if (! $method->requiresDelivery()) {
            return;
        }

        $maxAttempts = $this->getTwoFactorChallengeResendMaxAttempts();
        $throttleKey = $this->throttleKey($this->twoFactorChallengeStartThrottleKeyPart($user), includeIp: false);

        $this->ensureIsNotRateLimited(
            $throttleKey,
            $maxAttempts,
            fn (int $seconds) => throw TwoFactorChallengeException::rateLimited($seconds)
        );

        try {
            $this->dispatchTwoFactorChallengeCode($user, $method);
        } finally {
            $this->hitRateLimiterIfThrottled($throttleKey, $maxAttempts, $this->getTwoFactorChallengeResendDecaySeconds() ?: null);
        }
    }

    public function getTwoFactorChallengeSession(): ?array
    {
        return $this->twoFactorSessionManager()->getChallenge($this);
    }

    public function hasPendingTwoFactorChallenge(): bool
    {
        return $this->hasPendingTwoFactorSession($this->getTwoFactorChallengeSession());
    }

    public function clearTwoFactorChallenge(): void
    {
        $this->twoFactorSessionManager()->clearChallenge($this);
    }

    public function getPendingTwoFactorChallengeUser(): ?Authenticatable
    {
        return $this->resolveUserFromTwoFactorSession($this->getTwoFactorChallengeSession());
    }

    public function getTwoFactorChallengeRemember(): bool
    {
        return $this->rememberFromTwoFactorSession($this->getTwoFactorChallengeSession());
    }

    public function getPendingTwoFactorChallengeMethod(): TwoFactorMethod
    {
        return $this->resolveTwoFactorMethodFromSession($this->getTwoFactorChallengeSession(), TwoFactorMethod::Totp);
    }

    public function resendPendingTwoFactorChallengeCode(): bool
    {
        $user = $this->getPendingTwoFactorChallengeUser();

        if (! $user instanceof Model) {
            return false;
        }

        $method = $this->getPendingTwoFactorChallengeMethod();

        if (! $method->requiresDelivery()) {
            return false;
        }

        $maxAttempts = $this->getTwoFactorChallengeResendMaxAttempts();
        $throttleKey = $this->throttleKey($this->twoFactorChallengeResendThrottleKeyPart($user), includeIp: false);

        $this->ensureIsNotRateLimited(
            $throttleKey,
            $maxAttempts,
            fn (int $seconds) => throw TwoFactorChallengeException::rateLimited($seconds)
        );

        try {
            return $this->dispatchTwoFactorChallengeCode($user, $method);
        } finally {
            $this->hitRateLimiterIfThrottled($throttleKey, $maxAttempts, $this->getTwoFactorChallengeResendDecaySeconds() ?: null);
        }
    }

    protected function dispatchTwoFactorChallengeCode(Authenticatable $user, TwoFactorMethod $method): bool
    {
        $secret = app(TwoFactorUser::class)->getTwoFactorSecret($user, $this);

        if (! is_string($secret) || blank($secret)) {
            return false;
        }

        $this->dispatchTwoFactorCode($user, $method, app(Totp::class)->currentCode($secret), 'challenge');

        return true;
    }

    protected function hasPendingTwoFactorSession(?array $session): bool
    {
        return filled($session['user_id'] ?? null);
    }

    protected function resolveUserFromTwoFactorSession(?array $session): ?Authenticatable
    {
        if (! $session) {
            return null;
        }

        try {
            return $this->authProvider()->retrieveById($session['user_id'] ?? null);
        } catch (UnsupportedAuthGuardException) {
            return null;
        }
    }

    protected function rememberFromTwoFactorSession(?array $session): bool
    {
        return (bool) ($session['remember'] ?? false);
    }

    protected function resolveTwoFactorMethodFromSession(?array $session, TwoFactorMethod $fallback): TwoFactorMethod
    {
        $method = TwoFactorMethod::tryFrom((string) ($session['method'] ?? ''));

        return $method ?? $fallback;
    }

    public function startPendingTwoFactorSetup(Authenticatable $user, bool $remember = true, ?TwoFactorMethod $method = null): void
    {
        $method ??= $this->getTwoFactorMethod();

        $this->twoFactorSessionManager()->startPendingSetup($this, $user, $remember, $method);
    }

    public function getPendingTwoFactorSetupSession(): ?array
    {
        return $this->twoFactorSessionManager()->getPendingSetup($this);
    }

    public function hasPendingTwoFactorSetup(): bool
    {
        return $this->hasPendingTwoFactorSession($this->getPendingTwoFactorSetupSession());
    }

    public function getPendingTwoFactorSetupUser(): ?Authenticatable
    {
        return $this->resolveUserFromTwoFactorSession($this->getPendingTwoFactorSetupSession());
    }

    public function getPendingTwoFactorSetupRemember(): bool
    {
        return $this->rememberFromTwoFactorSession($this->getPendingTwoFactorSetupSession());
    }

    public function getPendingTwoFactorSetupMethod(): TwoFactorMethod
    {
        return $this->resolveTwoFactorMethodFromSession($this->getPendingTwoFactorSetupSession(), $this->getTwoFactorMethod());
    }

    public function completePendingTwoFactorSetupLogin(): bool
    {
        $user = $this->getPendingTwoFactorSetupUser();

        if (! $user) {
            $this->clearPendingTwoFactorSetup();

            return false;
        }

        app(PostAuthenticationFlow::class)->finalize($user, $this->getPendingTwoFactorSetupRemember());

        return true;
    }

    public function clearPendingTwoFactorSetup(): void
    {
        $this->twoFactorSessionManager()->clearPendingSetup($this);
    }

    public function getPendingTwoFactorSetupSessionKey(): string
    {
        return $this->twoFactorSessionKey('pending-setup');
    }

    public function setTwoFactorChallengeRememberDevice(bool $rememberDevice): void
    {
        $this->twoFactorSessionManager()->updateChallenge(
            $this,
            function (array $challenge) use ($rememberDevice): array {
                $challenge['remember_device'] = $rememberDevice;

                return $challenge;
            }
        );
    }

    public function shouldTwoFactorRememberOnDevice(): bool
    {
        return (bool) $this->twoFactorRememberOnDevice;
    }

    public function getTwoFactorRememberForDays(): int
    {
        return max(1, $this->twoFactorRememberForDays);
    }

    public function rememberTwoFactorOnCurrentDevice(Model $user): void
    {
        $this->twoFactorTrustedDeviceManager()->remember(
            fortress: $this,
            user: $user,
            enabled: $this->shouldTwoFactorRememberOnDevice(),
            days: $this->getTwoFactorRememberForDays(),
            cookieName: $this->getTwoFactorRememberDeviceCookieName(),
        );
    }

    public function forgetRememberedTwoFactorDevice(): void
    {
        $this->twoFactorTrustedDeviceManager()->forget($this->getTwoFactorRememberDeviceCookieName());
    }

    public function listTrustedTwoFactorDevices(Model $user): array
    {
        return $this->twoFactorTrustedDeviceManager()->list($this, $user);
    }

    public function revokeTrustedTwoFactorDevice(Model $user, int $deviceId): bool
    {
        return $this->twoFactorTrustedDeviceManager()->revoke($this, $user, $deviceId);
    }

    public function revokeAllTrustedTwoFactorDevices(Model $user): int
    {
        return $this->twoFactorTrustedDeviceManager()->revokeAll($this, $user);
    }

    public function getTwoFactorChallengeSessionKey(): string
    {
        return $this->twoFactorSessionKey('challenge');
    }

    public function startTwoFactorSetup(string $secret, ?TwoFactorMethod $method = null): void
    {
        $method ??= $this->getTwoFactorMethod();

        $this->twoFactorSessionManager()->startSetup($this, $secret, $method);
    }

    public function getTwoFactorSetupSession(): ?array
    {
        return $this->twoFactorSessionManager()->getSetup($this);
    }

    public function getTwoFactorSetupSecret(): ?string
    {
        return $this->getTwoFactorSetupSession()['secret'] ?? null;
    }

    public function getTwoFactorSetupMethod(): TwoFactorMethod
    {
        return $this->resolveTwoFactorMethodFromSession($this->getTwoFactorSetupSession(), $this->getTwoFactorMethod());
    }

    public function clearTwoFactorSetup(): void
    {
        $this->twoFactorSessionManager()->clearSetup($this);
    }

    public function getTwoFactorSetupSessionKey(): string
    {
        return $this->twoFactorSessionKey('setup');
    }

    public function getTwoFactorChallengeTtl(): int|false|null
    {
        return $this->twoFactorChallengeTtl;
    }

    public function getTwoFactorSetupTtl(): int|false|null
    {
        return $this->twoFactorSetupTtl;
    }

    public function getTwoFactorMethod(): TwoFactorMethod
    {
        return $this->twoFactorMethod;
    }

    public function dispatchTwoFactorCode(Authenticatable $user, TwoFactorMethod $method, string $code, string $context): void
    {
        $this->twoFactorDeliveryManager()->dispatch(
            fortress: $this,
            user: $user,
            method: $method,
            code: $code,
            context: $context,
            sendEmailCodeUsing: $this->twoFactorSendEmailCodeUsing,
            sendSmsCodeUsing: $this->twoFactorSendSmsCodeUsing,
            resolveSmsRecipientUsing: $this->twoFactorResolveSmsRecipientUsing,
        );
    }

    protected function shouldSkipTwoFactorForRememberedDevice(Authenticatable $user): bool
    {
        return $this->twoFactorTrustedDeviceManager()->shouldSkipChallenge(
            fortress: $this,
            user: $user,
            enabled: $this->shouldTwoFactorRememberOnDevice(),
            cookieName: $this->getTwoFactorRememberDeviceCookieName(),
        );
    }

    protected function isWithinTwoFactorGracePeriod(Authenticatable $user, TwoFactorUser $twoFactorUser): bool
    {
        if (! is_int($this->twoFactorGracePeriodDays) || $this->twoFactorGracePeriodDays <= 0) {
            return false;
        }

        $confirmedAt = $twoFactorUser->getTwoFactorConfirmedAt($user);

        if (! $confirmedAt) {
            return false;
        }

        return now()->lessThanOrEqualTo($confirmedAt->copy()->addDays($this->twoFactorGracePeriodDays));
    }

    protected function getTwoFactorRememberDeviceCookieName(): string
    {
        return "guardian_{$this->getId()}_remember_2fa";
    }

    protected function twoFactorSessionKey(string $suffix): string
    {
        return "guardian.{$this->getId()}.two-factor.{$suffix}";
    }

    protected function getTwoFactorChallengeResendMaxAttempts(): int|false|null
    {
        return $this->twoFactorChallengeResendMaxAttempts;
    }

    protected function getTwoFactorChallengeResendDecaySeconds(): int|false|null
    {
        return $this->twoFactorChallengeResendDecaySeconds;
    }

    protected function twoFactorChallengeResendThrottleKeyPart(Model $user): string
    {
        return implode('|', array_filter([
            '2fa-challenge-resend',
            $this->getId(),
            $this->getGuard(),
            (string) $user->getAuthIdentifier(),
        ]));
    }

    protected function twoFactorChallengeStartThrottleKeyPart(Authenticatable $user): string
    {
        return implode('|', array_filter([
            '2fa-challenge-start',
            $this->getId(),
            $this->getGuard(),
            (string) $user->getAuthIdentifier(),
        ]));
    }

    protected function twoFactorSessionManager(): TwoFactorSessionManager
    {
        return app(TwoFactorSessionManager::class);
    }

    protected function twoFactorDeliveryManager(): TwoFactorDeliveryManager
    {
        return app(TwoFactorDeliveryManager::class);
    }

    protected function twoFactorTrustedDeviceManager(): TwoFactorTrustedDeviceManager
    {
        return app(TwoFactorTrustedDeviceManager::class);
    }

    public function twoFactorRoutes(): static
    {
        $this->getTwoFactorSetupFeature()->registerRoutesIfEnabled();
        $this->getTwoFactorChallengeFeature()->registerRoutesIfEnabled();

        return $this;
    }
}
