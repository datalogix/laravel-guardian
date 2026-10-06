<?php

namespace Datalogix\Guardian\Framework\Inertia;

use Datalogix\Guardian\Framework\FrameworkResolver;
use Illuminate\Support\ServiceProvider;

class InertiaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InertiaAdapter::class);

        $this->callAfterResolving(
            FrameworkResolver::class,
            fn (FrameworkResolver $resolver) => $resolver->register($this->app->make(InertiaAdapter::class)),
        );
    }

    public function boot(): void
    {
        $pages = $this->app->resourcePath('js/pages/'.InertiaAdapter::DEFAULT_PREFIX);

        $this->publishes([
            __DIR__.'/resources/js/vue' => $pages,
        ], 'guardian-inertia-vue');

        $this->publishes([
            __DIR__.'/resources/js/react' => $pages,
        ], 'guardian-inertia-react');
    }
}
