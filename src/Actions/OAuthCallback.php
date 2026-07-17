<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Enums\AuthFlowResult;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Enums\OAuthEmailCollisionPolicy;
use Datalogix\Guardian\Exceptions\OAuthException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Support\Auth\PostAuthenticationFlow;
use Datalogix\Guardian\Support\OAuth\OAuthIdentities;
use Datalogix\Guardian\Support\OAuth\OAuthTokenPayload;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class OAuthCallback
{
    /** @var array<string, bool> */
    protected static array $emailVerifiedColumnCache = [];

    public function __construct(
        protected PostAuthenticationFlow $postAuthenticationFlow,
        protected OAuthIdentities $oauthIdentities,
        protected OAuthTokenPayload $oauthTokenPayload,
    ) {}

    public function __invoke(string $provider, bool $remember = true): AuthFlowResult
    {
        $provider = Guardian::normalizeOAuthProvider($provider);

        if (! Guardian::hasOAuthProvider($provider)) {
            throw OAuthException::providerNotEnabled();
        }

        $oauthUser = $this->retrieveOAuthUser($provider);
        $providerUserId = (string) $oauthUser->getId();

        if (blank($providerUserId)) {
            throw OAuthException::unableToAuthenticate();
        }

        $user = $this->resolveUser($provider, $oauthUser, $providerUserId);

        if (! $user) {
            throw OAuthException::noAccountFound();
        }

        if ($user instanceof Model && Guardian::cannotAccess($user)) {
            throw OAuthException::cannotAccess();
        }

        $this->storeIdentity($provider, $providerUserId, $oauthUser, $user);

        return $this->postAuthenticationFlow->handle($user, $remember);
    }

    protected function resolveUser(string $provider, ProviderUser $oauthUser, string $providerUserId): ?Authenticatable
    {
        $user = $this->findLinkedUser($provider, $providerUserId);

        if ($user) {
            return $user;
        }

        $email = $oauthUser->getEmail();

        if (filled($email) && Guardian::shouldAutoLinkOAuthByEmail()) {
            $user = $this->findUserByEmail($email);

            if ($user) {
                return $this->resolveEmailCollision($user);
            }
        }

        if (! Guardian::shouldCreateOAuthUserIfMissing()) {
            return null;
        }

        return $this->createUserFromOAuth($provider, $oauthUser, $providerUserId);
    }

    protected function retrieveOAuthUser(string $provider): ProviderUser
    {
        try {
            return $this->driver($provider)->user();
        } catch (Throwable) {
            throw OAuthException::unableToAuthenticate();
        }
    }

    protected function driver(string $provider): Provider
    {
        $callbackUrl = Guardian::getOAuthFeature()->getCallbackUrl($provider);
        config(["services.{$provider}.redirect" => $callbackUrl]);

        $driver = Socialite::driver($provider);

        if (Guardian::isOAuthStateless() && method_exists($driver, 'stateless')) {
            $driver = $driver->stateless();
        }

        return $driver;
    }

    protected function findLinkedUser(string $provider, string $providerUserId): ?Authenticatable
    {
        $id = $this->oauthIdentities->findAuthenticatableId(Guardian::getCurrentOrDefaultFortress(), $provider, $providerUserId);

        if (blank($id)) {
            return null;
        }

        return Guardian::authProvider()->retrieveById($id);
    }

    protected function findUserByEmail(string $email): ?Authenticatable
    {
        $modelClass = Guardian::authModelClass();

        return $modelClass::query()->where('email', $email)->first();
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

        if (Guardian::getIdentifierKey() !== IdentifierKey::Email) {
            $attributes['username'] = $this->generateUsername($oauthUser, $provider, $providerUserId, $modelClass);
        }

        if ($this->hasEmailVerifiedColumn($modelClass)) {
            $attributes['email_verified_at'] = now();
        }

        $user = Guardian::wrapInDatabaseTransaction(fn () => $modelClass::query()->create($attributes));

        event(new Registered($user));

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
    }

    protected function generateUsername(ProviderUser $oauthUser, string $provider, string $providerUserId, string $modelClass): string
    {
        $base = Str::lower((string) ($oauthUser->getNickname() ?: $oauthUser->getName() ?: Str::before((string) $oauthUser->getEmail(), '@')));
        $base = preg_replace('/[^a-z0-9_]/', '', $base ?? '') ?: Str::lower($provider);
        $base = Str::limit($base, 16, '');

        $username = Str::limit($base.'_'.Str::lower(substr(sha1($providerUserId), 0, 6)), 20, '');

        if (strlen($username) < 5) {
            $username = str_pad($username, 5, '0');
        }

        if (! $modelClass::query()->where('username', $username)->exists()) {
            return $username;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = Str::limit($base.'_'.Str::lower(Str::random(6)), 20, '');

            if (strlen($candidate) < 5) {
                $candidate = str_pad($candidate, 5, '0');
            }

            if (! $modelClass::query()->where('username', $candidate)->exists()) {
                return $candidate;
            }
        }

        return Str::limit('user_'.Str::lower(Str::random(16)), 20, '');
    }

    protected function resolveEmailCollision(Authenticatable $user): ?Authenticatable
    {
        return match (Guardian::getOAuthEmailCollisionPolicy()) {
            OAuthEmailCollisionPolicy::LinkExisting => $user,
            OAuthEmailCollisionPolicy::DenyWithError => throw OAuthException::emailAlreadyExists(),
            OAuthEmailCollisionPolicy::RequireManualLink => throw OAuthException::manualLinkRequired(),
        };
    }

    protected function hasEmailVerifiedColumn(string $modelClass): bool
    {
        if (array_key_exists($modelClass, static::$emailVerifiedColumnCache)) {
            return static::$emailVerifiedColumnCache[$modelClass];
        }

        $hasColumn = Schema::hasColumn((new $modelClass)->getTable(), 'email_verified_at');
        static::$emailVerifiedColumnCache[$modelClass] = $hasColumn;

        return $hasColumn;
    }
}
