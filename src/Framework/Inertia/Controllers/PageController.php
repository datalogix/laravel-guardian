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
    /**
     * The bundled page this controller renders (matches the feature page name).
     */
    abstract protected static function page(): string;

    /**
     * The extra endpoints of the page, besides its GET route:
     * name => [HTTP method, URI appended to the page URI, controller method, route parameter constraints].
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

    /**
     * Where Inertia can (v3), the browser keeps the lines of a language across
     * visits instead of getting them with every page.
     */
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

    /**
     * The URL of every endpoint of the page that does not need route parameters.
     */
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
     * Some responses only flash a notification and return nothing, which
     * means "stay on the page".
     */
    protected function respond(mixed $response, Request $request)
    {
        if ($response instanceof Responsable) {
            $response = $response->toResponse($request);
        }

        return $response ?? back();
    }
}
