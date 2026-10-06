<?php

namespace Datalogix\Guardian\Tests\Feature\Framework\Inertia;

use Datalogix\Guardian\Framework\FrameworkResolver;
use Datalogix\Guardian\Framework\Inertia\InertiaAdapter;
use Datalogix\Guardian\Framework\Inertia\InertiaServiceProvider;
use Datalogix\Guardian\Tests\TestCase;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionMethod;

class InertiaServiceProviderTest extends TestCase
{
    public function test_the_adapter_is_registered_in_the_framework_resolver(): void
    {
        $adapter = app(FrameworkResolver::class)->adapters()['inertia'];

        $this->assertInstanceOf(InertiaAdapter::class, $adapter);
        $this->assertSame($adapter, app(InertiaAdapter::class));
    }

    public function test_the_vue_pages_are_published(): void
    {
        $paths = ServiceProvider::pathsToPublish(InertiaServiceProvider::class, 'guardian-inertia-vue');

        $this->assertNotEmpty($paths);
        $this->assertStringEndsWith('Framework/Inertia/resources/js/vue', array_key_first($paths));
        $this->assertStringEndsWith('js/pages/Guardian', array_values($paths)[0]);
    }

    public function test_the_react_pages_are_published(): void
    {
        $paths = ServiceProvider::pathsToPublish(InertiaServiceProvider::class, 'guardian-inertia-react');

        $this->assertNotEmpty($paths);
        $this->assertStringEndsWith('Framework/Inertia/resources/js/react', array_key_first($paths));
        $this->assertStringEndsWith('js/pages/Guardian', array_values($paths)[0]);
    }

    public function test_the_vue_and_react_pages_publish_to_the_same_directory(): void
    {
        $vue = ServiceProvider::pathsToPublish(InertiaServiceProvider::class, 'guardian-inertia-vue');
        $react = ServiceProvider::pathsToPublish(InertiaServiceProvider::class, 'guardian-inertia-react');

        $this->assertSame(array_values($vue)[0], array_values($react)[0]);
    }

    public function test_every_bundled_page_has_a_vue_and_a_react_component(): void
    {
        $method = new ReflectionMethod(InertiaAdapter::class, 'pages');
        $pages = $method->invoke(app(InertiaAdapter::class));

        $this->assertNotEmpty($pages);

        $base = dirname((new ReflectionMethod(InertiaAdapter::class, 'pages'))->getFileName()).'/resources/js';

        foreach (array_keys($pages) as $page) {
            $name = Str::after(InertiaAdapter::component($page), 'Guardian/');

            $this->assertFileExists("{$base}/vue/{$name}.vue", "Missing Vue page for [{$page}].");
            $this->assertFileExists("{$base}/react/{$name}.tsx", "Missing React page for [{$page}].");
        }
    }

    public function test_every_shared_component_exists_for_both_stacks(): void
    {
        $base = dirname((new ReflectionMethod(InertiaAdapter::class, 'pages'))->getFileName()).'/resources/js';

        $components = ['Alert', 'AuthLayout', 'Button', 'Checkbox', 'Field', 'OAuthProviders'];

        foreach ($components as $component) {
            $this->assertFileExists("{$base}/vue/components/{$component}.vue");
            $this->assertFileExists("{$base}/react/components/{$component}.tsx");
        }

        foreach (['vue', 'react'] as $stack) {
            $this->assertFileExists("{$base}/{$stack}/components/identifier.ts");
            $this->assertFileExists("{$base}/{$stack}/components/types.ts");
        }
    }
}
