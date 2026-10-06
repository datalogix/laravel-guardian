<?php

namespace Datalogix\Guardian\Tests;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\GuardianServiceProvider;
use Datalogix\Guardian\Support\SessionState;
use Datalogix\Guardian\Support\TwoFactor\DeliveredCodes;
use Datalogix\Guardian\Tests\Attributes\WithFortresses;
use Datalogix\Guardian\Tests\Fixtures\RecordingDeliveredCodes;
use Datalogix\Guardian\Tests\Fixtures\User;
use GrahamCampbell\TestBench\AbstractPackageTestCase;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Laravel\Socialite\SocialiteServiceProvider;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use ReflectionMethod;

abstract class TestCase extends AbstractPackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(DeliveredCodes::class, fn ($app) => new RecordingDeliveredCodes($app->make(SessionState::class)));

        if (class_exists(Inertia::class)) {
            Inertia::setRootView('guardian-tests::inertia-root');
        }
    }

    protected function tearDown(): void
    {
        $this->ensureCacheDirectoryIsClean();

        // The links of these notifications are static, so they outlive the application.
        ResetPassword::createUrlUsing(null);
        VerifyEmail::createUrlUsing(null);

        parent::tearDown();
    }

    protected static function getServiceProviderClass(): string
    {
        return GuardianServiceProvider::class;
    }

    /**
     * Livewire, Socialite and Inertia are optional, so only the installed ones are registered.
     */
    protected static function getRequiredServiceProviders(): array
    {
        return array_values(array_filter([
            LivewireServiceProvider::class,
            SocialiteServiceProvider::class,
            InertiaServiceProvider::class,
        ], 'class_exists'));
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('app.name', 'Guardian Tests');
        $app['config']->set('auth.timebox_duration', 0);
        $app['view']->addNamespace('guardian-tests', __DIR__.'/Fixtures/views');

        // What Application::configure()->withEvents() registers in every Laravel 11+ app.
        $app['events']->listen(Registered::class, SendEmailVerificationNotification::class);
        $app['config']->set('guardian.framework', class_exists(Livewire::class) ? Framework::Livewire : Framework::Inertia);
        $app['config']->set('guardian.livewire.cache_path', $this->cachePath());

        $app['config']->set('services.github', [
            'client_id' => 'test-github-client-id',
            'client_secret' => 'test-github-client-secret',
            'redirect' => 'http://localhost/oauth/github/callback',
        ]);

        $this->runMigration(__DIR__.'/database/migrations/0000_00_00_000000_create_users_table.php');

        foreach ($this->packageMigrationPaths() as $path) {
            $this->runMigration($path);
        }

        foreach ($this->fortressesForCurrentTest() as $fortress) {
            Guardian::registerFortress($fortress);
        }
    }

    protected function fortresses(): array
    {
        return [Fortress::make()->basic()];
    }

    protected function fortressesForCurrentTest(): array
    {
        $attribute = (new ReflectionMethod($this, $this->name()))->getAttributes(WithFortresses::class)[0] ?? null;

        return $attribute ? $this->{$attribute->newInstance()->method}() : $this->fortresses();
    }

    protected function packageMigrationPaths(): array
    {
        $base = dirname(__DIR__).'/database/migrations';

        return [
            "{$base}/2026_01_01_000000_add_two_factor_columns_to_users_table.php",
            "{$base}/2026_01_01_000001_create_two_factor_trusted_devices_table.php",
            "{$base}/2026_01_01_000002_create_oauth_identities_table.php",
        ];
    }

    protected function runMigration(string $path): void
    {
        (require $path)->up();
    }

    protected function cachePath(): string
    {
        return sys_get_temp_dir().'/guardian-tests-cache-'.Str::random(8);
    }

    protected function ensureCacheDirectoryIsClean(): void
    {
        $path = $this->app['config']->get('guardian.livewire.cache_path');

        if ($path && is_dir($path)) {
            (new Filesystem)->deleteDirectory($path);
        }
    }

    /**
     * The rate limit error of the given exception, whatever the seconds left.
     */
    protected function expectRateLimitedBy(string $exception): void
    {
        $this->expectException($exception);
        $this->expectExceptionMessageMatches(
            '/^'.preg_replace('/\\d+/', '\\d+', preg_quote($exception::rateLimited(60)->getMessage(), '/')).'$/'
        );
    }

    /**
     * The last two-factor code sent by e-mail or SMS.
     */
    protected function lastDeliveredCode(): ?string
    {
        return app(DeliveredCodes::class)->lastIssued;
    }

    protected function createUser(array $attributes = []): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => Str::random(12).'@example.com',
            'password' => Hash::make('password'),
            ...$attributes,
        ]);
    }
}
