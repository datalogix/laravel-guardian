<?php

namespace Datalogix\Guardian\Framework\Livewire;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\AbstractFrameworkAdapter;
use Datalogix\Guardian\Framework\Livewire\Pages\ConfirmPassword;
use Datalogix\Guardian\Framework\Livewire\Pages\EmailVerificationPrompt;
use Datalogix\Guardian\Framework\Livewire\Pages\ForgotPassword;
use Datalogix\Guardian\Framework\Livewire\Pages\Login;
use Datalogix\Guardian\Framework\Livewire\Pages\OAuthCompleteRegistration;
use Datalogix\Guardian\Framework\Livewire\Pages\ResetPassword;
use Datalogix\Guardian\Framework\Livewire\Pages\SignUp;
use Datalogix\Guardian\Framework\Livewire\Pages\TwoFactorChallenge;
use Datalogix\Guardian\Framework\Livewire\Pages\TwoFactorSetup;
use Livewire\Component;
use Livewire\Livewire;

class LivewireAdapter extends AbstractFrameworkAdapter
{
    public function __construct(protected ComponentCache $cache = new ComponentCache)
    {
        //
    }

    public function framework(): Framework
    {
        return Framework::Livewire;
    }

    public function package(): string
    {
        return 'livewire/livewire';
    }

    protected function requiredClass(): string
    {
        return Livewire::class;
    }

    protected function pages(): array
    {
        return [
            'login' => Login::class,
            'sign-up' => SignUp::class,
            'forgot-password' => ForgotPassword::class,
            'reset-password' => ResetPassword::class,
            'confirm-password' => ConfirmPassword::class,
            'email-verification-prompt' => EmailVerificationPrompt::class,
            'two-factor-setup' => TwoFactorSetup::class,
            'two-factor-challenge' => TwoFactorChallenge::class,
            'oauth-complete-registration' => OAuthCompleteRegistration::class,
        ];
    }

    public function registerFortress(Fortress $fortress): void
    {
        if (! $this->isInstalled()) {
            return;
        }

        foreach ($this->components($fortress) as $componentName => $componentClass) {
            Livewire::component($componentName, $componentClass);
        }

        Livewire::addPersistentMiddleware($fortress->pullPersistentMiddleware());
    }

    /**
     * The Livewire components of a fortress (name => class): the cached ones
     * on a real request, otherwise the ones its enabled features route to.
     *
     * @return array<string, string>
     */
    public function components(Fortress $fortress): array
    {
        return $this->cache->exists($fortress)
            ? $this->cache->read($fortress)
            : $this->discoverComponents($fortress);
    }

    /**
     * The view of a bundled page: the one of the folder the fortress names in
     * ->livewire(views: '...') when it exists, otherwise the bundled one.
     */
    public function viewFor(string $page, Fortress $fortress): string
    {
        $views = $fortress->getFrameworkOption('views');

        if (is_string($views) && $views !== '') {
            $custom = rtrim($views, '.').'.'.$page;

            if (view()->exists($custom)) {
                return $custom;
            }
        }

        return 'guardian::'.$page;
    }

    public function cacheComponents(Fortress $fortress): void
    {
        if (! $this->isInstalled()) {
            return;
        }

        $this->cache->write($fortress, $this->discoverComponents($fortress));
    }

    public function clearCachedComponents(Fortress $fortress): void
    {
        $this->cache->clear($fortress);
    }

    public function componentCachePath(Fortress $fortress): string
    {
        return $this->cache->path($fortress);
    }

    protected function discoverComponents(Fortress $fortress): array
    {
        $components = [];

        foreach ($fortress->getFeatures() as $feature) {
            $action = $feature->getRouteAction();

            if (is_string($action) && is_subclass_of($action, Component::class)) {
                $components[$this->componentName($action)] = $action;
            }
        }

        return $components;
    }

    protected function componentName(string $component): string
    {
        return app('livewire.factory')->resolveComponentName($component);
    }

    public function redirect(string $path, bool $intended = false, bool $navigate = true): mixed
    {
        $livewire = app()->bound('livewire') ? app('livewire')->current() : null;

        if (! $livewire) {
            return parent::redirect($path, $intended, $navigate);
        }

        return $intended
            ? $livewire->redirectIntended($path, navigate: $navigate)
            : $livewire->redirect($path, navigate: $navigate);
    }

    /**
     * The tallkit alerts when that UI kit is installed, the status flash otherwise.
     */
    public function notify(string $message, ?string $type = null): void
    {
        if (app()->bound('tallkit')) {
            app('tallkit')->alert(__($message), $type);

            return;
        }

        parent::notify($message, $type);
    }
}
