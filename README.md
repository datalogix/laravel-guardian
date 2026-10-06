# Laravel Guardian

[![Latest Stable Version](https://poser.pugx.org/datalogix/laravel-guardian/version)](https://packagist.org/packages/datalogix/laravel-guardian)
[![Total Downloads](https://poser.pugx.org/datalogix/laravel-guardian/downloads)](https://packagist.org/packages/datalogix/laravel-guardian)
[![tests](https://github.com/datalogix/laravel-guardian/workflows/tests/badge.svg)](https://github.com/datalogix/laravel-guardian/actions)
[![StyleCI](https://github.styleci.io/repos/1040982393/shield?style=flat)](https://github.styleci.io/repos/1040982393)
[![codecov](https://codecov.io/gh/datalogix/laravel-guardian/branch/main/graph/badge.svg)](https://codecov.io/gh/datalogix/laravel-guardian)
[![License](https://poser.pugx.org/datalogix/laravel-guardian/license)](https://packagist.org/packages/datalogix/laravel-guardian)

> Laravel Guardian is an extensible authentication package providing login, sign-up, password reset, email verification, two-factor authentication and OAuth social login — with a Livewire or an Inertia front-end.

## Installation

You can install the package via composer:

```bash
composer require datalogix/laravel-guardian
```

The package will automatically register itself.

### Front-end and optional dependencies

Guardian ships two front-ends. Install the one you use — neither is required
by the package itself:

| Front-end | Package | Enable it with |
|-----------|---------|----------------|
| Livewire (default) | `livewire/livewire` | `Fortress::make()->livewire()` |
| Inertia | `inertiajs/inertia-laravel` | `Fortress::make()->inertia()` |

```bash
composer require livewire/livewire          # Livewire front-end
composer require inertiajs/inertia-laravel  # Inertia front-end
```

The bundled Livewire views are built with the `<tk:>` Blade components of
[`datalogix/tallkit`](https://github.com/datalogix/tallkit), which is also
optional. Install it to use them as they are, or publish the views
(`guardian-views`) and replace them with your own. When it is installed,
notifications are shown through its alerts instead of the `status` session
flash. The Inertia front-end does not use these views — it ships its own
default pages instead, styled with Tailwind CSS, that you publish for
`@inertiajs/vue3` or `@inertiajs/react` (see [Publishing the pages](#publishing-the-pages)).

```bash
composer require datalogix/tallkit  # UI kit of the bundled Livewire views
```

You can also run headless, without either of them, by giving every feature you
enable its own `routeAction` and using Guardian only for the actions, routes,
middleware and responses.

The `SignUp` action passes to your user model only the fields of its `rules()`,
whatever else it is given, so a model with `$guarded = []` cannot be filled with
fields the form does not ask for. To collect more fields, extend the action,
add them to `rules()` and bind your class in the container:

```php
class SignUp extends \Datalogix\Guardian\Actions\SignUp
{
    public static function rules(): array
    {
        return [...parent::rules(), 'phone' => ['required', 'string']];
    }
}

$this->app->bind(\Datalogix\Guardian\Actions\SignUp::class, SignUp::class);
```

OAuth / social login is built on [Laravel Socialite](https://laravel.com/docs/socialite),
which is not installed with Guardian either. Install it only if you enable the
`oauth()` feature:

```bash
composer require laravel/socialite
```

Enabling a feature whose package is missing fails fast on boot with a clear
error that names the package to install.

## Quick start

Register at least one "fortress" — a self-contained authentication flow bound
to a guard and, optionally, a domain or path prefix — from a service
provider's `boot` method:

```php
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;

public function boot(): void
{
    Guardian::registerFortress(Fortress::make()->basic());
}
```

Then run the migrations. Guardian only loads the migrations it actually
needs, based on which features you enabled (two-factor columns, trusted
devices, OAuth identities):

```bash
php artisan migrate
```

You can register more than one fortress to run independent auth flows side
by side — for example a customer-facing app and an admin panel:

```php
Guardian::registerFortress(Fortress::make()->basic());
Guardian::registerFortress(Fortress::make()->admin());
```

## Features

- 🔑 **Login &amp; Logout** – Session-based authentication with rate limiting and remember-me support.
- 📝 **Sign-up** – Self-service registration with configurable identifier (email, username, CPF or CNPJ).
- 🔁 **Password Reset &amp; Confirmation** – Forgot-password flow and password re-confirmation for sensitive actions.
- ✉️ **Email Verification** – Signed, expiring verification links.
- 🔒 **Two-Factor Authentication** – TOTP (authenticator app), email or SMS codes, recovery codes and trusted devices.
- 🌐 **OAuth / Social Login** – Sign in with any [Laravel Socialite](https://laravel.com/docs/socialite) provider, with automatic account linking.
- 🏰 **Multiple Fortresses** – Run independent authentication flows per guard, domain or path (e.g. customer app + admin panel).
- 🚦 **Rate Limiting** – Configurable throttling on every sensitive action out of the box.
- 🇧🇷 **CPF/CNPJ Validation** – Ready-to-use validation rules for Brazilian documents.

## Configuration

All features are optional and configurable per fortress through the fluent
`Fortress` API (see `src/Concerns` for the full list of available methods,
e.g. `twoFactor()`, `oauth()`, `signUp()`, `emailVerification()`).

You can publish the package config, views and translations with:

```bash
php artisan vendor:publish --provider="Datalogix\Guardian\GuardianServiceProvider" --tag="guardian-config"
php artisan vendor:publish --provider="Datalogix\Guardian\GuardianServiceProvider" --tag="guardian-lang"
php artisan vendor:publish --tag="guardian-views" # the Livewire views
```

Publishing the config creates a `config/guardian.php` file:

```php
// config/guardian.php

return [
    'framework' => Framework::tryFrom(env('GUARDIAN_FRAMEWORK')) ?? Framework::Livewire,

    'two_factor_codes' => [
        'connection' => env('GUARDIAN_TWO_FACTOR_CODES_CONNECTION'),
        'queue' => env('GUARDIAN_TWO_FACTOR_CODES_QUEUE'),
    ],
];
```

`framework` is the default front-end of every fortress; a fortress can override
it with `->livewire()` or `->inertia()`, so a customer app on Livewire and an
admin panel on Inertia can live side by side. Call it either before or after a
preset such as `basic()`.

The Livewire component cache path (see `guardian:cache-components`) can be
changed in the same file:

```php
'livewire' => [
    'cache_path' => base_path('bootstrap/cache/guardian'),
],
```

The e-mail and SMS two-factor codes are queued notifications, encrypted in the
queue since they carry the code, and sent in the language of the request that
asked for them. A code expires shortly, so `two_factor_codes` gives them a queue
that slower jobs do not hold up, or sends them right away with the `sync`
connection.

A new user gets the e-mail verification e-mail once: Laravel 11+ applications
send it on the `Registered` event out of the box, so Guardian sends it itself
only when that listener is gone. For the same reason, a fortress that creates
users (`signUp()`, or `oauth()` creating them) whose model implements
`MustVerifyEmail` needs `emailVerification()`, or the e-mail would link to a
page that does not exist; Guardian fails on boot when it is missing.

The password reset and e-mail verification e-mails are sent by your user model.
When they are queued, have the model implement `HasLocalePreference` to send
them in the language of the user rather than the default one of the queue
worker.

The options that change from one fortress to another — which screens a fortress
uses — are given to the fortress itself, see [Livewire pages](#livewire-pages)
and [Inertia](#inertia).

## Password reset and account privacy

The forgot password form answers the same thing whether or not an account
exists for the login, so it cannot be used to find out who your users are:

> If an account exists for that login, we have emailed a password reset link.

This covers the forgot password form only. The sign-up form still says when an
e-mail or login is taken, and social login when an account already uses the
e-mail of the provider, as they must for the user to go on. Hiding that too
would take a sign-up confirmed by e-mail before the account exists, which
Guardian does not do. The attempts on those forms are rate limited, which slows
down anyone trying many addresses, but does not stop them from checking one.

Resetting the password signs out every session opened with the old one, so a
user who resets a stolen password also signs out whoever used it. The presets
(`basic()`, `admin()`, `product()`) protect their pages with Guardian's
`AuthenticateSession` for that; a fortress with its own `authMiddleware()`
should list it after `Authenticate`.

To tell visitors when there is no account (the Laravel default) opt in on the
fortress:

```php
Fortress::make()->basic()->passwordReset(revealsAccounts: true);
```

Requests are limited per login (`forgotPasswordMaxAttempts`, 3 a minute by
default) and per IP (ten times that), so trying a different login on every
request does not get around the limit.

> [!IMPORTANT]
> The limits that count per IP need the IP of the visitor. Behind a load
> balancer, Cloudflare or any other reverse proxy, every request comes from the
> IP of the proxy until you trust it, and the per-IP limit of the forgot
> password form becomes one limit for your whole site: after 30 requests a
> minute nobody can ask for a reset link. Trust your proxies in
> `bootstrap/app.php`:
>
> ```php
> ->withMiddleware(function (Middleware $middleware) {
>     $middleware->trustProxies(at: ['10.0.0.0/8']); // the addresses of your proxies
> })
> ``` Every answer also takes as long as
`auth.timebox_duration` (200 ms by default), so the time it takes does not give
the account away either. The message is sent inside that time: if your mail
driver is slower, queue the reset notification (`ShouldQueue`) or raise
`auth.timebox_duration`.

The links in the password reset and e-mail verification e-mails point to the
pages of the fortress that sent them, also when the notification is queued: a
queued job runs in the fortress of the request that dispatched it. Guardian
builds these links through `ResetPassword::createUrlUsing()` and
`VerifyEmail::createUrlUsing()` when a fortress has those pages; if your
application sets its own callback in a service provider, Guardian keeps it.

## Social login and verified e-mails

A provider account whose e-mail belongs to an existing user is refused by
default (`OAuthEmailCollisionPolicy::DenyWithError`). With `LinkExisting` it is
linked only when the e-mail is **verified**, which Guardian decides in this
order:

1. the `emailVerifiedUsing` closure, when you give one, decides alone (it
   receives the Socialite user and the raw payload of the provider);
2. otherwise, the `email_verified` or `verified_email` claim of the provider;
3. otherwise, the e-mail is not verified.

A key such as `verified` is never trusted on its own, because for some
providers it means a verified account rather than a verified e-mail. If your
provider does mean the e-mail, say so. The closure also receives the name of
the provider, so each one can be treated as it deserves:

```php
Fortress::make()->basic()->oauth(
    providers: ['discord', 'github'],
    emailCollisionPolicy: OAuthEmailCollisionPolicy::LinkExisting,
    emailVerifiedUsing: fn ($user, array $raw, string $provider) => match ($provider) {
        'discord' => (bool) ($raw['verified'] ?? false),
        default => (bool) ($raw['email_verified'] ?? false),
    },
);
```

If you relied on `verified` before this rule, your users are refused when they
sign in with an account whose e-mail matches an existing user, and Guardian
logs a warning naming the provider and the option to configure.

With `storeTokens: true` the access and refresh tokens of the provider are kept
encrypted, both on the linked identity and in the session while a sign-up waits
for the user to complete it (a CPF or CNPJ identifier).

## Architecture

The core (actions, features, fortresses, middleware, responses) does not know
which front-end renders the pages. Each front-end lives in its own directory
under `src/Framework` and depends only on the core, so it can be lifted into a
package of its own:

| Directory | Contains |
|-----------|----------|
| `src/Framework` | The `FrameworkAdapter` contract, its base class and the `FrameworkResolver` |
| `src/Framework/Livewire` | Adapter, service provider, page components, Blade views and layouts, cache commands and config |
| `src/Framework/Inertia` | Adapter, service provider and page controllers |

A front-end plugs in through its service provider, which registers its adapter
in the `FrameworkResolver`; the adapter decides how the bundled pages are
routed, how redirects and notifications are sent and what has to be registered
for each fortress.

## Livewire pages

Each page renders a Blade view inside a layout, both of which can be different
for each fortress.

The **layout** wraps the page. Set it for the whole fortress, for one page or
when enabling a feature:

```php
Fortress::make()->livewire()->admin()
    ->layout('admin.layouts.auth')                    // every page
    ->layoutForPage('login', 'admin.layouts.login')   // a single page
    ->signUp(layout: Layout::Split);                  // when enabling a feature
```

The **view** is the content of the page. Give the fortress a folder of views;
a page that folder does not have keeps using the bundled view, so you only
write the pages you want to change:

```php
Guardian::registerFortress(Fortress::make()->livewire()->basic());
Guardian::registerFortress(Fortress::make()->livewire(views: 'admin.auth')->admin());
// resources/views/admin/auth/login.blade.php replaces the login of the admin
```

The folder can be namespaced (`views: 'admin::auth'`). A view must have a single
root HTML tag, like any Livewire component. Publishing the bundled views
(`guardian-views`) changes them for every fortress.

For a page that behaves differently, not just looks different, extend the
bundled component and give it to the feature. Keep `$pageName`, or the view and
the layout hints of the page are looked up under the class name instead:

```php
class AdminLogin extends \Datalogix\Guardian\Framework\Livewire\Pages\Login
{
    protected string $pageName = 'login';
}

Fortress::make()->livewire()->admin()->login(routeAction: AdminLogin::class);
```

Livewire sends every public property of a page back to the browser, so the
bundled pages clear the password and two-factor code fields once an attempt is
over, also when it failed. A page of your own that takes a secret should do the
same, for instance with the `forgettingSecrets()` helper of the base `Page`:

```php
public function submit()
{
    return $this->forgettingSecrets(function () {
        // ...
    }, 'password');
}
```

## Inertia

With `->inertia()`, Guardian registers a controller for every page and ships
a default Inertia component for each one — the same idea as the bundled
Livewire pages, just publishable instead of resolved from the package. Add
your `HandleInertiaRequests` middleware to the fortress:

```php
Guardian::registerFortress(
    Fortress::make()
        ->inertia()
        ->product()
        ->middleware([HandleInertiaRequests::class])
);
```

Every page receives `status` (the flashed notification, if any),
`endpoints` (the URLs it submits to) and `translations` (its lines in the
language of the request). Forms are submitted to `endpoints.*`
with the fields the same action expects on Livewire, and validation errors
come back through the usual Inertia `errors` prop.

| Component | Props | Endpoints |
|-----------|-------|-----------|
| `Guardian/Login` | `identifierKey`, `forgotPasswordUrl`, `signUpUrl`, `oauthProviders` | `submit` (`login`, `password`, `remember`) |
| `Guardian/SignUp` | `identifierKey`, `loginUrl`, `oauthProviders` | `submit` (`name`, `login`, `password`, `password_confirmation`, `terms`, plus `email` when the identifier isn't already the e-mail) |
| `Guardian/ForgotPassword` | `identifierKey`, `loginUrl` | `submit` (`login`) |
| `Guardian/ResetPassword` | `identifierKey`, `token`, `login` | `submit` (`token`, `login`, `password`, `password_confirmation`) |
| `Guardian/ConfirmPassword` | — | `submit` (`password`) |
| `Guardian/EmailVerificationPrompt` | `logoutUrl` | `submit` (resends the link) |
| `Guardian/TwoFactorChallenge` | `method`, `canRememberDevice`, `rememberDeviceDays` | `submit` (`code`, `remember_device`), `resend` |
| `Guardian/TwoFactorSetup` | `enabled`, `method`, `secret`, `uri`, `qrSvg`, `recoveryCodes`, `recoveryCodesCount`, `canManageRecoveryCodes`, `trustedDevices`, `awaitingContinueAfterSetup`, `secretUnreadable` | `prepare`, `enable` (`code`), `continue`, `disable`, `recovery-codes`, `trusted-devices.destroy-all`; each trusted device carries its own `revokeUrl` |
| `Guardian/OAuthCompleteRegistration` | `identifierKey`, `provider`, `email` | `submit` (`login`) |

`remember` on the login form is off unless the field is sent as `true`.

`oauthProviders` is a list of `{ name, url }`; link to `url` with a plain
`<a href>` (not an Inertia visit) so the browser can follow the redirect to
the provider.

### Publishing the pages

The default pages are TypeScript, styled with Tailwind, and come in two
stacks — publish whichever one your app uses:

```sh
php artisan vendor:publish --tag=guardian-inertia-vue    # @inertiajs/vue3
php artisan vendor:publish --tag=guardian-inertia-react   # @inertiajs/react
```

Both publish to `resources/js/pages/Guardian/`, the same components folder
included, ready to import by whichever bundler your app already builds
`resources/js/pages/` with. From there they're your files: edit them, restyle
them, or replace one while leaving the rest bundled.

The pages show their lines through `t()` (`components/translate.ts`), which
looks them up in the `translations` prop: the lines the bundled pages show,
translated into the language of the request, Brazilian Portuguese included.
With Inertia 3 the browser keeps them for the rest of the visit instead of
getting them with every page. To change a line or add a language, translate its
English text in your app's JSON lang file, as for any other `__()` line:

```json
// lang/es.json
{
    "Sign in": "Iniciar sesión",
    "Remember this device for :days days": "Recordar este dispositivo durante :days días"
}
```

A page you edit may show lines of its own. Add them to the ones Guardian sends
by extending `PageTranslations` and binding your class in the container:

```php
class PageTranslations extends \Datalogix\Guardian\Framework\Inertia\PageTranslations
{
    protected static function lines(): array
    {
        return [...parent::lines(), 'Welcome back'];
    }
}

$this->app->bind(\Datalogix\Guardian\Framework\Inertia\PageTranslations::class, PageTranslations::class);
```

If a fortress uses a custom `prefix` (see below), copy the published folder
to match it — a `pages: [...]` override works too, and is the only option
for a page the `vendor:publish` layout doesn't cover.

The component names are `{prefix}/{Page}` (`Guardian/Login` by default). A
fortress can change the prefix, or point a single page somewhere else, so a
customer app and an admin panel can have different screens:

```php
Guardian::registerFortress(Fortress::make()->inertia()->product());   // Guardian/Login, ...
Guardian::registerFortress(
    Fortress::make()
        ->inertia(prefix: 'Admin', pages: ['sign-up' => 'Admin/Join'])
        ->product('admin')
        ->path('admin')
);                                                                   // Admin/Login, Admin/Join, ...
```

`pages` is keyed by the page name (`login`, `sign-up`, `forgot-password`,
`reset-password`, `confirm-password`, `email-verification-prompt`,
`two-factor-challenge`, `two-factor-setup`, `oauth-complete-registration`).

To render a page yourself, pass your own `routeAction` to the feature (for
example `->login(routeAction: MyLoginController::class)`); Guardian then leaves
that page's endpoints to you.
