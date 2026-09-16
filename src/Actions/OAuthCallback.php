<?php

namespace Datalogix\Guardian\Actions;

use Closure;
use Datalogix\Guardian\Actions\Concerns\CreatesAuthenticatableUser;
use Datalogix\Guardian\Actions\Concerns\HasEmailVerifiedColumn;
use Datalogix\Guardian\Actions\Concerns\ResolvesOAuthProvider;
use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Enums\OAuthEmailCollisionPolicy;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Exceptions\UnsupportedAuthGuardException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\PostAuthenticationFlow;
use Datalogix\Guardian\Support\OAuth\OAuthIdentities;
use Datalogix\Guardian\Support\OAuth\OAuthTokenPayload;
use Datalogix\Guardian\Support\OAuth\SocialiteDriverResolver;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Throwable;

class OAuthCallback
{
    use CreatesAuthenticatableUser;
    use HasEmailVerifiedColumn;
    use ResolvesOAuthProvider;

    public function __construct(
        protected PostAuthenticationFlow $postAuthenticationFlow,
        protected OAuthIdentities $oauthIdentities,
        protected OAuthTokenPayload $oauthTokenPayload,
        protected SocialiteDriverResolver $driverResolver,
    ) {
        //
    }

    public function __invoke(string $provider, bool $remember = true): AuthFlowResult
    {
        $provider = $this->resolveEnabledOAuthProvider($provider);

        $oauthUser = $this->retrieveOAuthUser($provider);
        $providerUserId = (string) $oauthUser->getId();

        if (blank($providerUserId)) {
            throw OAuthException::unableToAuthenticate();
        }

        $linkedId = $this->oauthIdentities->findAuthenticatableId(
            Guardian::getCurrentOrDefaultFortress(),
            $provider,
            $providerUserId,
            Guardian::authModelClass(),
        );

        $user = $this->resolveUser($provider, $oauthUser, $providerUserId, $linkedId);

        if (! $user) {
            if (Guardian::hasPendingOAuthRegistration()) {
                return AuthFlowResult::OAuthRegistrationRequired;
            }

            throw OAuthException::noAccountFound();
        }

        if ($user instanceof Model && Guardian::cannotAccess($user)) {
            throw OAuthException::cannotAccess();
        }

        $this->storeIdentity($provider, $providerUserId, $oauthUser, $user);

        return $this->postAuthenticationFlow->handle($user, $remember);
    }

    protected function resolveUser(string $provider, ProviderUser $oauthUser, string $providerUserId, string|int|null $linkedId): ?Authenticatable
    {
        $user = $this->findLinkedUser($linkedId);

        if ($user) {
            return $user;
        }

        $email = $oauthUser->getEmail();

        if (filled($email)) {
            $existingUser = $this->findUserByEmail($email);

            if ($existingUser) {
                return $this->resolveEmailCollision($existingUser, $oauthUser);
            }
        }

        if (! Guardian::shouldCreateOAuthUserIfMissing()) {
            return null;
        }

        return $this->createUserFromOAuth($provider, $oauthUser, $providerUserId);
    }

    protected function isOAuthEmailVerified(ProviderUser $oauthUser): bool
    {
        $raw = method_exists($oauthUser, 'getRaw') ? (array) $oauthUser->getRaw() : [];

        foreach (['email_verified', 'verified_email', 'verified'] as $key) {
            if (array_key_exists($key, $raw)) {
                return filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        $emailVerifiedUsing = Guardian::getOAuthEmailVerifiedUsing();

        if ($emailVerifiedUsing instanceof Closure) {
            return (bool) $emailVerifiedUsing($oauthUser, $raw);
        }

        return false;
    }

    protected function retrieveOAuthUser(string $provider): ProviderUser
    {
        try {
            $callbackUrl = Guardian::getOAuthFeature()->getCallbackUrl($provider);

            return $this->driverResolver->resolve($provider, $callbackUrl)->user();
        } catch (Throwable $exception) {
            report($exception);

            throw OAuthException::unableToAuthenticate();
        }
    }

    protected function findLinkedUser(string|int|null $linkedId): ?Authenticatable
    {
        if (blank($linkedId)) {
            return null;
        }

        try {
            return Guardian::authProvider()->retrieveById($linkedId);
        } catch (UnsupportedAuthGuardException) {
            return null;
        }
    }

    protected function findUserByEmail(string $email): ?Authenticatable
    {
        $modelClass = Guardian::authModelClass();

        return $modelClass::query()->whereRaw('LOWER(email) = ?', [Str::lower($email)])->first();
    }

    protected function createUserFromOAuth(
        string $provider,
        ProviderUser $oauthUser,
        string $providerUserId,
    ): ?Authenticatable {
        $email = $oauthUser->getEmail();

        if (blank($email)) {
            return null;
        }

        $modelClass = Guardian::authModelClass();
        $name = $oauthUser->getName() ?: $oauthUser->getNickname() ?: Str::headline($provider).' User';

        $attributes = [
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(64)),
        ];

        $identifierKey = Guardian::getIdentifierKey();

        if (in_array($identifierKey, [IdentifierKey::CPF, IdentifierKey::CNPJ], true)) {
            $accessToken = null;
            $refreshToken = null;
            $tokenExpiresAt = null;

            if (Guardian::shouldStoreOAuthTokens()) {
                $tokenPayload = $this->oauthTokenPayload->fromProviderUser($oauthUser);
                $accessToken = $tokenPayload['access_token'];
                $refreshToken = $tokenPayload['refresh_token'];
                $tokenExpiresAt = $tokenPayload['token_expires_at'];
            }

            Guardian::startPendingOAuthRegistration(
                provider: $provider,
                providerUserId: $providerUserId,
                email: $email,
                name: $name,
                avatar: $oauthUser->getAvatar(),
                emailVerified: $this->isOAuthEmailVerified($oauthUser),
                accessToken: $accessToken,
                refreshToken: $refreshToken,
                tokenExpiresAt: $tokenExpiresAt,
            );

            return null;
        }

        if ($identifierKey !== IdentifierKey::Email) {
            $attributes[$identifierKey->value] = $this->generateUsername(
                $oauthUser,
                $provider,
                $providerUserId,
                $modelClass,
                $identifierKey->value,
            );
        }

        if ($this->hasEmailVerifiedColumn($modelClass) && $this->isOAuthEmailVerified($oauthUser)) {
            $attributes['email_verified_at'] = now();
        }

        $user = $this->createAuthenticatableUser(
            $modelClass,
            $attributes,
            OAuthException::cannotAccess(...),
            fn () => $this->findUserByEmail($email) ? OAuthException::emailAlreadyExists() : OAuthException::unableToAuthenticate(),
            OAuthException::unableToAuthenticate(...),
        );

        $this->fireUserRegistered($user);

        return $user;
    }

    protected function storeIdentity(string $provider, string $providerUserId, ProviderUser $oauthUser, Authenticatable $user): void
    {
        if (! $user instanceof Model) {
            return;
        }

        $accessToken = null;
        $refreshToken = null;
        $tokenExpiresAt = null;

        if (Guardian::shouldStoreOAuthTokens()) {
            $tokenPayload = $this->oauthTokenPayload->fromProviderUser($oauthUser);
            $accessToken = $tokenPayload['access_token'];
            $refreshToken = $tokenPayload['refresh_token'];
            $tokenExpiresAt = $tokenPayload['token_expires_at'];
        }

        try {
            $this->oauthIdentities->link(
                Guardian::getCurrentOrDefaultFortress(),
                $user,
                $provider,
                $providerUserId,
                $oauthUser->getEmail(),
                $oauthUser->getName(),
                $oauthUser->getAvatar(),
                $accessToken,
                $refreshToken,
                $tokenExpiresAt,
            );
        } catch (QueryException $exception) {
            report($exception);

            throw OAuthException::identityAlreadyLinked();
        }
    }

    protected function generateUsername(ProviderUser $oauthUser, string $provider, string $providerUserId, string $modelClass, string $column = 'username'): string
    {
        $base = Str::lower((string) ($oauthUser->getNickname() ?: $oauthUser->getName() ?: Str::before((string) $oauthUser->getEmail(), '@')));
        $base = preg_replace('/[^a-z0-9_]/', '', $base ?? '') ?: Str::lower($provider);
        $base = Str::limit($base, 16, '');

        $username = $this->finalizeUsernameCandidate($base.'_'.Str::lower(substr(sha1($providerUserId), 0, 6)), $modelClass, $column);

        if ($username !== null) {
            return $username;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $username = $this->finalizeUsernameCandidate($base.'_'.Str::lower(Str::random(6)), $modelClass, $column);

            if ($username !== null) {
                return $username;
            }
        }

        return Str::limit('user_'.Str::lower(Str::random(16)), 20, '');
    }

    protected function finalizeUsernameCandidate(string $candidate, string $modelClass, string $column): ?string
    {
        $candidate = Str::limit($candidate, 20, '');

        if (strlen($candidate) < 5) {
            $candidate = str_pad($candidate, 5, '0');
        }

        return $modelClass::query()->where($column, $candidate)->exists() ? null : $candidate;
    }

    protected function resolveEmailCollision(Authenticatable $user, ProviderUser $oauthUser): ?Authenticatable
    {
        return match (Guardian::getOAuthEmailCollisionPolicy()) {
            OAuthEmailCollisionPolicy::LinkExisting => $this->linkExistingIfAllowed($user, $oauthUser),
            OAuthEmailCollisionPolicy::DenyWithError => throw OAuthException::emailAlreadyExists(),
            OAuthEmailCollisionPolicy::RequireManualLink => throw OAuthException::manualLinkRequired(),
        };
    }

    protected function linkExistingIfAllowed(Authenticatable $user, ProviderUser $oauthUser): Authenticatable
    {
        if (! Guardian::shouldAutoLinkOAuthByEmail() || ! $this->isOAuthEmailVerified($oauthUser)) {
            throw OAuthException::emailAlreadyExists();
        }

        return $user;
    }
}
