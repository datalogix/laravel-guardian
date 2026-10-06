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
    ) {}

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
                return $this->resolveEmailCollision($provider, $existingUser, $oauthUser);
            }
        }

        if (! Guardian::shouldCreateOAuthUserIfMissing()) {
            return null;
        }

        return $this->createUserFromOAuth($provider, $oauthUser, $providerUserId);
    }

    protected function isOAuthEmailVerified(string $provider, ProviderUser $oauthUser): bool
    {
        $raw = method_exists($oauthUser, 'getRaw') ? (array) $oauthUser->getRaw() : [];
        $emailVerifiedUsing = Guardian::getOAuthEmailVerifiedUsing();

        if ($emailVerifiedUsing instanceof Closure) {
            return (bool) $emailVerifiedUsing($oauthUser, $raw, $provider);
        }

        // "verified" alone means a verified account for some providers, not the e-mail.
        foreach (['email_verified', 'verified_email'] as $key) {
            if (array_key_exists($key, $raw)) {
                return filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN);
            }
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

        return Guardian::authProvider()->retrieveById($linkedId);
    }

    protected function findUserByEmail(string $email): ?Authenticatable
    {
        $modelClass = Guardian::authModelClass();

        return $modelClass::query()->where('email', IdentifierKey::Email->normalize($email))->first();
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

        // An unverified e-mail would let anyone pre-register someone else's account.
        if (! $this->isOAuthEmailVerified($provider, $oauthUser)) {
            $this->warnAboutIgnoredVerifiedClaim($provider, $oauthUser);

            throw OAuthException::emailNotVerified();
        }

        $modelClass = Guardian::authModelClass();
        $name = $oauthUser->getName() ?: $oauthUser->getNickname() ?: Str::headline($provider).' User';

        $attributes = [
            'name' => $name,
            'email' => IdentifierKey::Email->normalize($email),
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
                email: IdentifierKey::Email->normalize($email),
                name: $name,
                avatar: $oauthUser->getAvatar(),
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

        $guardianAttributes = $this->hasEmailVerifiedColumn($modelClass) ? ['email_verified_at' => now()] : [];

        $user = $this->createAuthenticatableUser(
            $modelClass,
            $attributes,
            OAuthException::cannotAccess(...),
            fn () => $this->findUserByEmail($email) ? OAuthException::emailAlreadyExists() : OAuthException::unableToAuthenticate(),
            OAuthException::unableToAuthenticate(...),
            guardianAttributes: $guardianAttributes,
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

        return $modelClass::query()->where($column, $candidate)->exists() ? null : $candidate;
    }

    protected function resolveEmailCollision(string $provider, Authenticatable $user, ProviderUser $oauthUser): ?Authenticatable
    {
        return match (Guardian::getOAuthEmailCollisionPolicy()) {
            OAuthEmailCollisionPolicy::LinkExisting => $this->linkExistingIfAllowed($provider, $user, $oauthUser),
            OAuthEmailCollisionPolicy::DenyWithError => throw OAuthException::emailAlreadyExists(),
            OAuthEmailCollisionPolicy::RequireManualLink => throw OAuthException::manualLinkRequired(),
        };
    }

    protected function linkExistingIfAllowed(string $provider, Authenticatable $user, ProviderUser $oauthUser): Authenticatable
    {
        if (! Guardian::shouldAutoLinkOAuthByEmail()) {
            throw OAuthException::emailAlreadyExists();
        }

        if (! $this->isOAuthEmailVerified($provider, $oauthUser)) {
            $this->warnAboutIgnoredVerifiedClaim($provider, $oauthUser);

            throw OAuthException::emailAlreadyExists();
        }

        return $user;
    }

    protected function warnAboutIgnoredVerifiedClaim(string $provider, ProviderUser $oauthUser): void
    {
        $raw = method_exists($oauthUser, 'getRaw') ? (array) $oauthUser->getRaw() : [];

        if (Guardian::getOAuthEmailVerifiedUsing() !== null || ! array_key_exists('verified', $raw)) {
            return;
        }

        logger()->warning(
            "Guardian did not link or create a user for the [{$provider}] account, because its e-mail is not verified. ".
            'The provider sent a [verified] claim, which Guardian does not trust on its own: if it means the e-mail is verified, '.
            'say so with the emailVerifiedUsing option of oauth().'
        );
    }
}
