# Laravel Guardian

[![Latest Stable Version](https://poser.pugx.org/datalogix/laravel-guardian/version)](https://packagist.org/packages/datalogix/laravel-guardian)
[![Total Downloads](https://poser.pugx.org/datalogix/laravel-guardian/downloads)](https://packagist.org/packages/datalogix/laravel-guardian)
[![tests](https://github.com/datalogix/laravel-guardian/workflows/tests/badge.svg)](https://github.com/datalogix/laravel-guardian/actions)
[![StyleCI](https://github.styleci.io/repos/1040982393/shield?style=flat)](https://github.styleci.io/repos/1040982393)
[![codecov](https://codecov.io/gh/datalogix/laravel-guardian/branch/main/graph/badge.svg)](https://codecov.io/gh/datalogix/laravel-guardian)
[![License](https://poser.pugx.org/datalogix/laravel-guardian/license)](https://packagist.org/packages/datalogix/laravel-guardian)

> Authentication for Laravel: login, sign-up, password reset, e-mail verification, two-factor authentication and social login, with a Livewire or an Inertia front-end.

## Installation

```bash
composer require datalogix/laravel-guardian
```

Then install the front-end you use, and Socialite if you enable social login:

```bash
composer require livewire/livewire          # Livewire front-end (default)
composer require inertiajs/inertia-laravel  # Inertia front-end
composer require laravel/socialite          # social login
```

The bundled Livewire views use [`datalogix/tallkit`](https://github.com/datalogix/tallkit). Install it, or publish the views and write your own.

## Features

- 🔑 **Login & logout** with remember-me and rate limiting
- 📝 **Sign-up** by e-mail, username, CPF or CNPJ
- 🔁 **Password reset & confirmation**
- ✉️ **E-mail verification**
- 🔒 **Two-factor authentication**: authenticator app, e-mail or SMS, recovery codes and trusted devices
- 🌐 **Social login** with any [Laravel Socialite](https://laravel.com/docs/socialite) provider
- 🏰 **Multiple fortresses**: independent flows per guard, domain or path
- 🇧🇷 **CPF and CNPJ** validation rules

## Quick start

Register a fortress, an authentication flow bound to a guard, in a service provider:

```php
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;

public function boot(): void
{
    Guardian::registerFortress(Fortress::make()->basic());
}
```

Run the migrations (only those of the features you enable):

```bash
php artisan migrate
```

### Presets

| Preset      | Includes                                       |
| ----------- | ---------------------------------------------- |
| `basic()`   | Login, logout, password reset and confirmation |
| `admin()`   | `basic()` under `/admin`                       |
| `product()` | `basic()` with sign-up and e-mail verification |

Enable more on top of a preset:

```php
Fortress::make()->product()->twoFactor()->oauth(providers: ['google']);
```

Several fortresses can run side by side, each with its own `path()` or `domain()`:

```php
Guardian::registerFortress(Fortress::make()->basic());
Guardian::registerFortress(Fortress::make()->admin());
```

## Front-ends

**Livewire** is the default. To change the pages of a fortress, point it to a folder of views; pages missing from it keep the bundled ones:

```php
Fortress::make()->livewire(views: 'admin.auth')->admin();
```

**Inertia** ships default pages for Vue and React. Add your `HandleInertiaRequests` middleware and publish the pages:

```php
Fortress::make()->inertia()->product()->middleware([HandleInertiaRequests::class]);
```

```bash
php artisan vendor:publish --tag=guardian-inertia-vue    # or guardian-inertia-react
```

## Configuration

Most options are set per fortress (`twoFactor()`, `oauth()`, `signUp()`...). The global ones are in the config file:

```bash
php artisan vendor:publish --tag=guardian-config
php artisan vendor:publish --tag=guardian-lang
php artisan vendor:publish --tag=guardian-views        # Livewire views
php artisan vendor:publish --tag=guardian-migrations   # then set guardian.migrations to false
```

## In production

- **Trust your proxies.** Rate limits count per IP: behind a load balancer or Cloudflare, configure `trustProxies()` in `bootstrap/app.php`, or every visitor shares the same limit.
- **Use a shared cache store** (redis, database...) for `guardian.cache_store`, so the rate limits hold across servers.
- **Social login only creates accounts for verified e-mails.** Socialite's GitHub driver sends no verified claim, so tell Guardian with `emailVerifiedUsing`:

    ```php
    ->oauth(providers: ['github'], emailVerifiedUsing: fn ($user, array $raw, string $provider) => $provider === 'github')
    ```

- **Laravel Fortify** uses the same two-factor column names with another format. If you use it, set other names in `guardian.columns`.
