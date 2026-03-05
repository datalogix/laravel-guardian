<?php

namespace Datalogix\Guardian\Concerns;

use Closure;
use Datalogix\Guardian\Enums\Layout;
use Datalogix\Guardian\Events\TwoFactorTrustedDeviceRemembered;
use Datalogix\Guardian\Events\TwoFactorTrustedDeviceRevoked;
use Datalogix\Guardian\Events\TwoFactorTrustedDevicesRevokedAll;
use Datalogix\Guardian\Features\TwoFactorChallengeFeature;
use Datalogix\Guardian\Features\TwoFactorSetupFeature;
use Datalogix\Guardian\Support\TrustedDevices;
use Datalogix\Guardian\Support\TwoFactorUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;

trait HasTwoFactor
{
    protected ?TwoFactorChallengeFeature $twoFactorChallengeFeature = null;

    protected ?TwoFactorSetupFeature $twoFactorSetupFeature = null;

    protected int|false|null $twoFactorChallengeTtl = null;

    protected int|false|null $twoFactorSetupTtl = null;

    protected ?bool $rememberTwoFactorOnDevice = null;

    protected ?int $rememberTwoFactorForDays = null;

    protected ?Closure $twoFactorRequirementPolicy = null;

    protected ?int $twoFactorGracePeriodDays = null;

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
        $this->rememberTwoFactorOnDevice = $rememberOnDevice ?? false;
        $this->rememberTwoFactorForDays = $rememberForDays ?? 30;
        $this->twoFactorRequirementPolicy = $requireWhen;
        $this->twoFactorGracePeriodDays = $gracePeriodDays;
        $this->twoFactorSetupTtl = $setupTtl ?? 600;

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

    public function startTwoFactorChallenge(Model $user, bool $remember = true): void
    {
        Session::put($this->getTwoFactorChallengeSessionKey(), [
            'user_id' => $user->getAuthIdentifier(),
            'remember' => $remember,
            'remember_device' => false,
            'guard' => $this->getGuard(),
            'started_at' => now()->timestamp,
        ]);
    }

    public function getTwoFactorChallengeSession(): ?array
    {
        $challenge = Session::get($this->getTwoFactorChallengeSessionKey());

        if (! is_array($challenge)) {
            return null;
        }

        if ($this->isTwoFactorSessionExpired($challenge, $this->getTwoFactorChallengeTtl())) {
            $this->clearTwoFactorChallenge();

            return null;
        }

        return $challenge;
    }

    public function hasPendingTwoFactorChallenge(): bool
    {
        return filled($this->getTwoFactorChallengeSession()['user_id'] ?? null);
    }

    public function clearTwoFactorChallenge(): void
    {
        Session::forget($this->getTwoFactorChallengeSessionKey());
    }

    public function getPendingTwoFactorChallengeUser(): ?Authenticatable
    {
        $challenge = $this->getTwoFactorChallengeSession();

        if (! $challenge) {
            return null;
        }

        $auth = $this->auth();

        if (! method_exists($auth, 'getProvider')) {
            return null;
        }

        return $auth->getProvider()->retrieveById($challenge['user_id'] ?? null);
    }

    public function getTwoFactorChallengeRemember(): bool
    {
        return (bool) ($this->getTwoFactorChallengeSession()['remember'] ?? false);
    }

    public function setTwoFactorChallengeRememberDevice(bool $rememberDevice): void
    {
        $challenge = $this->getTwoFactorChallengeSession();

        if (! $challenge) {
            return;
        }

        $challenge['remember_device'] = $rememberDevice;

        Session::put($this->getTwoFactorChallengeSessionKey(), $challenge);
    }

    public function shouldRememberTwoFactorOnDevice(): bool
    {
        return $this->rememberTwoFactorOnDevice;
    }

    public function twoFactorRequireUsing(?Closure $policy): static
    {
        $this->twoFactorRequirementPolicy = $policy;

        return $this;
    }

    public function twoFactorGracePeriod(?int $days): static
    {
        $this->twoFactorGracePeriodDays = $days;

        return $this;
    }

    public function getRememberTwoFactorForDays(): int
    {
        return max(1, $this->rememberTwoFactorForDays);
    }

    public function rememberTwoFactorOnCurrentDevice(Model $user): void
    {
        if (! $this->shouldRememberTwoFactorOnDevice()) {
            return;
        }

        $secret = app(TwoFactorUser::class)->getTwoFactorSecret($user, $this);

        if (! is_string($secret) || blank($secret)) {
            return;
        }

        $issued = app(TrustedDevices::class)->issue($this, $user, $this->getRememberTwoFactorForDays());

        if (! is_array($issued)) {
            return;
        }

        Cookie::queue(Cookie::make(
            $this->getTwoFactorRememberDeviceCookieName(),
            "{$issued['id']}|{$issued['token']}",
            $this->getRememberTwoFactorForDays() * 1440,
            null,
            null,
            request()->isSecure(),
            true,
            false,
            'lax',
        ));

        event(new TwoFactorTrustedDeviceRemembered($this, $user, (int) $issued['id']));
    }

    public function forgetRememberedTwoFactorDevice(): void
    {
        Cookie::queue(Cookie::forget($this->getTwoFactorRememberDeviceCookieName()));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTrustedTwoFactorDevices(Model $user): array
    {
        return app(TrustedDevices::class)->list($this, $user);
    }

    public function revokeTrustedTwoFactorDevice(Model $user, int $deviceId): bool
    {
        $revoked = app(TrustedDevices::class)->revoke($deviceId, $this, $user);

        if ($revoked) {
            event(new TwoFactorTrustedDeviceRevoked($this, $user, $deviceId));
        }

        return $revoked;
    }

    public function revokeAllTrustedTwoFactorDevices(Model $user): int
    {
        $count = app(TrustedDevices::class)->revokeAll($this, $user);

        if ($count > 0) {
            event(new TwoFactorTrustedDevicesRevokedAll($this, $user, $count));
        }

        return $count;
    }

    public function getTwoFactorChallengeSessionKey(): string
    {
        return "guardian.{$this->getId()}.two-factor.challenge";
    }

    public function startTwoFactorSetup(string $secret): void
    {
        Session::put($this->getTwoFactorSetupSessionKey(), [
            'secret' => $secret,
            'started_at' => now()->timestamp,
        ]);
    }

    public function getTwoFactorSetupSession(): ?array
    {
        $setup = Session::get($this->getTwoFactorSetupSessionKey());

        if (! is_array($setup)) {
            return null;
        }

        if ($this->isTwoFactorSessionExpired($setup, $this->getTwoFactorSetupTtl())) {
            $this->clearTwoFactorSetup();

            return null;
        }

        return $setup;
    }

    public function getTwoFactorSetupSecret(): ?string
    {
        return $this->getTwoFactorSetupSession()['secret'] ?? null;
    }

    public function clearTwoFactorSetup(): void
    {
        Session::forget($this->getTwoFactorSetupSessionKey());
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

    protected function isTwoFactorSessionExpired(array $session, int|false|null $ttl): bool
    {
        if (! is_int($ttl) || $ttl <= 0) {
            return false;
        }

        $startedAt = $session['started_at'] ?? null;

        if (! is_int($startedAt)) {
            return true;
        }

        return ($startedAt + $ttl) < now()->timestamp;
    }

    protected function shouldSkipTwoFactorForRememberedDevice(Model $user): bool
    {
        if (! $this->shouldRememberTwoFactorOnDevice()) {
            return false;
        }

        $cookie = request()->cookie($this->getTwoFactorRememberDeviceCookieName());

        if (! is_string($cookie) || blank($cookie)) {
            return false;
        }

        $parts = explode('|', $cookie, 2);

        if (count($parts) !== 2) {
            $this->forgetRememberedTwoFactorDevice();

            return false;
        }

        [$deviceId, $token] = $parts;

        if (! ctype_digit($deviceId) || blank($token)) {
            $this->forgetRememberedTwoFactorDevice();

            return false;
        }

        $trusted = app(TrustedDevices::class)->touchIfValid($this, $user, (int) $deviceId, $token);

        if (! $trusted) {
            $this->forgetRememberedTwoFactorDevice();

            return false;
        }

        return true;
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
