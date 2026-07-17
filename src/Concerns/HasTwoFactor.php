<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Enums\TwoFactorMethod;
use Datalogix\Guardian\Exceptions\TwoFactorChallengeException;
use Datalogix\Guardian\Features\TwoFactorChallengeFeature;
use Datalogix\Guardian\Features\TwoFactorSetupFeature;
use Datalogix\Guardian\Support\TwoFactor\Totp;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorDeliveryManager;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorSessionManager;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorTrustedDeviceManager;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;

trait HasTwoFactor
{
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

    public function requiresTwoFactorChallenge(?Model $user): bool
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

    public function requiresTwoFactorSetup(?Model $user): bool
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

    public function startTwoFactorChallenge(Model $user, bool $remember = true): void
    {
        $manager = app(TwoFactorUser::class);
        $method = $manager->getTwoFactorMethod($user, $this);

        $this->twoFactorSessionManager()->startChallenge($this, $user, $remember, $method);

        $secret = $manager->getTwoFactorSecret($user, $this);

        if (! is_string($secret) || blank($secret)) {
            return;
        }

        if ($method !== TwoFactorMethod::Totp) {
            $this->dispatchTwoFactorCode($user, $method, app(Totp::class)->currentCode($secret), 'challenge');
        }
    }

    public function getTwoFactorChallengeSession(): ?array
    {
        return $this->twoFactorSessionManager()->getChallenge($this);
    }

    public function hasPendingTwoFactorChallenge(): bool
    {
        return filled($this->getTwoFactorChallengeSession()['user_id'] ?? null);
    }

    public function clearTwoFactorChallenge(): void
    {
        $this->twoFactorSessionManager()->clearChallenge($this);
    }

    public function getPendingTwoFactorChallengeUser(): ?Authenticatable
    {
        $challenge = $this->getTwoFactorChallengeSession();

        if (! $challenge) {
            return null;
        }

        return $this->authProvider()->retrieveById($challenge['user_id'] ?? null);
    }

    public function getTwoFactorChallengeRemember(): bool
    {
        return (bool) ($this->getTwoFactorChallengeSession()['remember'] ?? false);
    }

    public function getPendingTwoFactorChallengeMethod(): TwoFactorMethod
    {
        $method = TwoFactorMethod::tryFrom((string) ($this->getTwoFactorChallengeSession()['method'] ?? ''));

        return $method instanceof TwoFactorMethod && $method === $this->getTwoFactorMethod()
            ? $method
            : TwoFactorMethod::Totp;
    }

    public function resendPendingTwoFactorChallengeCode(): bool
    {
        $user = $this->getPendingTwoFactorChallengeUser();

        if (! $user instanceof Model) {
            return false;
        }

        $method = $this->getPendingTwoFactorChallengeMethod();

        if ($method === TwoFactorMethod::Totp) {
            return false;
        }

        $throttleKey = $this->getTwoFactorChallengeResendThrottleKey($user);
        $challengeResendMaxAttempts = $this->getTwoFactorChallengeResendMaxAttempts();

        if ($challengeResendMaxAttempts && RateLimiter::tooManyAttempts($throttleKey, $challengeResendMaxAttempts)) {
            throw TwoFactorChallengeException::rateLimited(RateLimiter::availableIn($throttleKey));
        }

        $secret = app(TwoFactorUser::class)->getTwoFactorSecret($user, $this);

        if (! is_string($secret) || blank($secret)) {
            return false;
        }

        $this->dispatchTwoFactorCode($user, $method, app(Totp::class)->currentCode($secret), 'challenge');

        $challengeResendDecaySeconds = $this->getTwoFactorChallengeResendDecaySeconds();
        if ($challengeResendDecaySeconds) {
            RateLimiter::hit($throttleKey, $challengeResendDecaySeconds);
        }

        return true;
    }

    public function startPendingTwoFactorSetup(Model $user, bool $remember = true, ?TwoFactorMethod $method = null): void
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
        return filled($this->getPendingTwoFactorSetupSession()['user_id'] ?? null);
    }

    public function getPendingTwoFactorSetupUser(): ?Authenticatable
    {
        $setup = $this->getPendingTwoFactorSetupSession();

        if (! $setup) {
            return null;
        }

        return $this->authProvider()->retrieveById($setup['user_id'] ?? null);
    }

    public function getPendingTwoFactorSetupRemember(): bool
    {
        return (bool) ($this->getPendingTwoFactorSetupSession()['remember'] ?? false);
    }

    public function getPendingTwoFactorSetupMethod(): TwoFactorMethod
    {
        $method = TwoFactorMethod::tryFrom((string) ($this->getPendingTwoFactorSetupSession()['method'] ?? ''));

        return $method instanceof TwoFactorMethod && $method === $this->getTwoFactorMethod()
            ? $method
            : $this->getTwoFactorMethod();
    }

    public function completePendingTwoFactorSetupLogin(): bool
    {
        $user = $this->getPendingTwoFactorSetupUser();

        if (! $user) {
            $this->clearPendingTwoFactorSetup();

            return false;
        }

        $this->auth()->login($user, $this->getPendingTwoFactorSetupRemember());
        $this->clearPendingTwoFactorSetup();

        Session::regenerate();

        return true;
    }

    public function clearPendingTwoFactorSetup(): void
    {
        $this->twoFactorSessionManager()->clearPendingSetup($this);
    }

    public function getPendingTwoFactorSetupSessionKey(): string
    {
        return "guardian.{$this->getId()}.two-factor.pending-setup";
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

    /**
     * @return array<int, array<string, mixed>>
     */
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
        return "guardian.{$this->getId()}.two-factor.challenge";
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
        $method = TwoFactorMethod::tryFrom((string) ($this->getTwoFactorSetupSession()['method'] ?? ''));

        return $method instanceof TwoFactorMethod && $method === $this->getTwoFactorMethod()
            ? $method
            : $this->getTwoFactorMethod();
    }

    public function clearTwoFactorSetup(): void
    {
        $this->twoFactorSessionManager()->clearSetup($this);
    }

    public function getTwoFactorSetupSessionKey(): string
    {
        return "guardian.{$this->getId()}.two-factor.setup";
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

    public function dispatchTwoFactorCode(Model $user, TwoFactorMethod $method, string $code, string $context): void
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

    protected function shouldSkipTwoFactorForRememberedDevice(Model $user): bool
    {
        return $this->twoFactorTrustedDeviceManager()->shouldSkipChallenge(
            fortress: $this,
            user: $user,
            enabled: $this->shouldTwoFactorRememberOnDevice(),
            cookieName: $this->getTwoFactorRememberDeviceCookieName(),
        );
    }

    protected function isWithinTwoFactorGracePeriod(Model $user, TwoFactorUser $twoFactorUser): bool
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

    protected function getTwoFactorChallengeResendMaxAttempts(): int|false|null
    {
        return $this->twoFactorChallengeResendMaxAttempts;
    }

    protected function getTwoFactorChallengeResendDecaySeconds(): int|false|null
    {
        return $this->twoFactorChallengeResendDecaySeconds;
    }

    protected function getTwoFactorChallengeResendThrottleKey(Model $user): string
    {
        return sha1(implode('|', array_filter([
            static::class,
            '2fa-challenge-resend',
            $this->getId(),
            $this->getGuard(),
            (string) $user->getAuthIdentifier(),
            (string) request()->ip(),
        ])));
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
        if ($this->getTwoFactorSetupFeature()->hasFeature()) {
            $this->getTwoFactorSetupFeature()->registerRoutes();
        }

        if ($this->getTwoFactorChallengeFeature()->hasFeature()) {
            $this->getTwoFactorChallengeFeature()->registerRoutes();
        }

        return $this;
    }
}
