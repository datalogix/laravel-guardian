<?php

namespace Datalogix\Guardian\Framework\Inertia\Controllers;

use Datalogix\Guardian\Features\Feature;
use Datalogix\Guardian\Framework\Inertia\InertiaAdapter;
use Datalogix\Guardian\Framework\Inertia\PageTranslations;
use Datalogix\Guardian\Guardian;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\ResponseFactory;

abstract class PageController
{
    abstract protected static function page(): string;

    /**
     * name => [method, URI suffix, controller method, route constraints]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3?: array<string, string>}>
     */
    public static function endpoints(): array
    {
        return [
            'submit' => ['post', '', 'submit'],
        ];
    }

    protected function props(Request $request): array
    {
        return [];
    }

    public function __invoke(Request $request)
    {
        return $this->render($request);
    }

    protected function render(Request $request)
    {
        return Inertia::render(InertiaAdapter::component(static::page(), Guardian::getCurrentOrDefaultFortress()), [
            'status' => session('status'),
            'endpoints' => $this->endpointUrls(),
            'translations' => $this->translations(),
            ...$this->props($request),
        ]);
    }

    protected function translations(): mixed
    {
        $translations = fn () => app(PageTranslations::class)->all();

        if (! $this->supportsOnceProps()) {
            return $translations();
        }

        return Inertia::once($translations)->as('guardian.translations.'.app()->getLocale());
    }

    protected function supportsOnceProps(): bool
    {
        return method_exists(ResponseFactory::class, 'once');
    }

    protected function feature(): Feature
    {
        return Arr::first(
            Guardian::getFeatures(),
            fn (Feature $feature) => $feature->getPageName() === static::page(),
        );
    }

    protected function endpointUrls(): array
    {
        $feature = $this->feature();
        $urls = [];

        foreach (static::endpoints() as $name => [, $uri]) {
            if (! str_contains($uri, '{')) {
                $urls[$name] = $feature->getEndpointUrl($name);
            }
        }

        return $urls;
    }

    protected function oauthProviders(): array
    {
        $feature = Guardian::getOAuthFeature();

        return array_map(fn (string $provider) => [
            'name' => $provider,
            'url' => $feature->getRedirectUrl($provider),
        ], Guardian::getOAuthProviders());
    }

    /**
     * A response that returns nothing means "stay on the page".
     */
    protected function respond(mixed $response, Request $request)
    {
        if ($response instanceof Responsable) {
            $response = $response->toResponse($request);
        }

        return $response ?? back();
    }
}
