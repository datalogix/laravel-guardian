<?php

use Datalogix\Guardian\Enums\Framework;

return [
    /**
     * 🖥️ Front-end.
     *
     * Renders the bundled pages: `Framework::Livewire` (default) or `Framework::Inertia`.
     * A fortress can use another one with `->livewire()` or `->inertia()`.
     */
    'framework' => Framework::tryFrom((string) env('GUARDIAN_FRAMEWORK')) ?? Framework::Livewire,

    /**
     * 🧱 Route Middleware.
     *
     * Applied to the routes of every fortress, before the ones of `->middleware()`.
     * Keep `web`: without it the pages lose their session and CSRF protection.
     */
    'middleware' => ['web'],

    /**
     * ⚡️ Livewire Component Cache.
     *
     * Where `php artisan guardian:cache-components` writes the components of each fortress.
     * Run the command again after changing it.
     */
    'livewire' => [
        'cache_path' => env('GUARDIAN_LIVEWIRE_CACHE_PATH', base_path('bootstrap/cache/guardian')),
    ],

    /**
     * 🗄️ Migrations.
     *
     * Runs the migrations of the features your fortresses use.
     * Set to `false` after publishing them (`--tag=guardian-migrations`), so they don't run twice.
     */
    'migrations' => (bool) env('GUARDIAN_MIGRATIONS', true),

    /**
     * 🔌 Database Connection.
     *
     * Connection of the trusted devices and OAuth identities tables (`null` uses the default).
     * The two-factor columns stay on the connection of each users table.
     */
    'database_connection' => env('GUARDIAN_DB_CONNECTION'),

    /**
     * 📋 Tables.
     *
     * - `users` – Tables that get the two-factor columns. `null` uses the table of the model
     *   each fortress signs in; or list table names and model classes.
     * - `two_factor_trusted_devices`, `oauth_identities` – Guardian's own tables.
     *
     * Users with UUID or ULID keys need `Schema::morphUsingUuids()` or `morphUsingUlids()`
     * before migrating. Don't rename the tables after migrating: the rollback reads these names.
     */
    'tables' => [
        'users' => null,
        'two_factor_trusted_devices' => 'two_factor_trusted_devices',
        'oauth_identities' => 'oauth_identities',
    ],

    /**
     * 🔐 Two-Factor Columns.
     *
     * Store the encrypted secret, the hashed recovery codes and when two-factor was confirmed.
     * Add the secret and recovery codes to the `$hidden` of your user models.
     *
     * Laravel Fortify uses the same names with another format: if you use it, choose others.
     * Don't rename the columns after migrating: the rollback reads these names.
     */
    'columns' => [
        'secret' => 'two_factor_secret',
        'recovery_codes' => 'two_factor_recovery_codes',
        'confirmed_at' => 'two_factor_confirmed_at',
    ],

    /**
     * 🚦 Cache Store.
     *
     * Keeps the security counters: rate limits, code attempts and used authenticator codes.
     * Must be shared by every server and count atomically (redis, database, memcached...),
     * or the limits can be bypassed. `null` uses the application's stores.
     */
    'cache_store' => env('GUARDIAN_CACHE_STORE'),

    /**
     * ✉️ Two-Factor Code Delivery.
     *
     * Queue of the codes sent by e-mail and SMS (`null` uses the defaults).
     * Codes expire quickly: use a queue slower jobs don't hold up, or the `sync` connection.
     */
    'two_factor_codes' => [
        'connection' => env('GUARDIAN_TWO_FACTOR_CODES_CONNECTION'),
        'queue' => env('GUARDIAN_TWO_FACTOR_CODES_QUEUE'),
    ],

    /**
     * 📱 Authenticator Issuer.
     *
     * The name authenticator apps show next to the account (`null` uses `app.name`).
     * Only affects the codes set up after it changes.
     */
    'totp_issuer' => env('GUARDIAN_TOTP_ISSUER'),

    /**
     * 🧹 Trusted Device Pruning.
     *
     * Deletes expired devices, and revoked ones older than `days`, every day.
     * Set `enabled` to `false` to schedule `guardian:prune-trusted-devices` yourself.
     */
    'prune_trusted_devices' => [
        'enabled' => (bool) env('GUARDIAN_PRUNE_TRUSTED_DEVICES', true),
        'days' => (int) env('GUARDIAN_PRUNE_TRUSTED_DEVICES_DAYS', 30),
    ],
];
