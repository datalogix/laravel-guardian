# Laravel Guardian

[![Latest Stable Version](https://poser.pugx.org/datalogix/laravel-guardian/version)](https://packagist.org/packages/datalogix/laravel-guardian)
[![Total Downloads](https://poser.pugx.org/datalogix/laravel-guardian/downloads)](https://packagist.org/packages/datalogix/laravel-guardian)
[![tests](https://github.com/datalogix/laravel-guardian/workflows/tests/badge.svg)](https://github.com/datalogix/laravel-guardian/actions)
[![StyleCI](https://github.styleci.io/repos/1040982393/shield?style=flat)](https://github.styleci.io/repos/1040982393)
[![codecov](https://codecov.io/gh/datalogix/laravel-guardian/branch/main/graph/badge.svg)](https://codecov.io/gh/datalogix/laravel-guardian)
[![License](https://poser.pugx.org/datalogix/laravel-guardian/license)](https://packagist.org/packages/datalogix/laravel-guardian)

> Laravel Guardian is an extensible authentication package providing login, sign-up, password reset, email verification, two-factor authentication and OAuth social login — all built on top of Livewire.

## Installation

You can install the package via composer:

```bash
composer require datalogix/laravel-guardian
```

The package will automatically register itself.

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
php artisan vendor:publish --provider="Datalogix\Guardian\GuardianServiceProvider" --tag="guardian-views"
php artisan vendor:publish --provider="Datalogix\Guardian\GuardianServiceProvider" --tag="guardian-lang"
```

Publishing the config creates a `config/guardian.php` file:

```php
// config/guardian.php

return [
    'framework' => Framework::tryFrom(env('GUARDIAN_FRAMEWORK')) ?? Framework::Livewire,
    'cache_path' => base_path('bootstrap/cache/guardian'),
];
```

> **Note:** only the Livewire front-end is currently supported. Inertia is
> present in the API for a future release but is not implemented yet — a
> fortress configured to use it will fail fast on boot with a clear error.
