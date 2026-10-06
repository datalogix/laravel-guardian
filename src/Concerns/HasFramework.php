<?php

namespace Datalogix\Guardian\Concerns;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Framework\FrameworkAdapter;
use Datalogix\Guardian\Framework\FrameworkResolver;

trait HasFramework
{
    protected Framework $framework;

    /**
     * The options of the chosen front-end, as given to inertia() or livewire().
     *
     * @var array<string, mixed>
     */
    protected array $frameworkOptions = [];

    /**
     * @param  string|null  $prefix  the folder of the page components: "{prefix}/Login" ("Guardian" by default)
     * @param  array<string, string>  $pages  page name => component, to render a single page from elsewhere
     */
    public function inertia(?string $prefix = null, array $pages = []): static
    {
        return $this->setFramework(Framework::Inertia, ['prefix' => $prefix, 'pages' => $pages]);
    }

    /**
     * @param  string|null  $views  where the views of this fortress live: "admin.auth" renders
     *                              "admin.auth.login", falling back to the bundled view of a page it lacks
     */
    public function livewire(?string $views = null): static
    {
        return $this->setFramework(Framework::Livewire, ['views' => $views]);
    }

    protected function setFramework(Framework $framework, array $options = []): static
    {
        $this->framework = $framework;
        $this->frameworkOptions = array_filter($options, fn (mixed $option) => $option !== null && $option !== []);

        return $this;
    }

    public function getFrameworkOptions(): array
    {
        return $this->frameworkOptions;
    }

    public function getFrameworkOption(string $key, mixed $default = null): mixed
    {
        return $this->frameworkOptions[$key] ?? $default;
    }

    public function getFramework(): Framework
    {
        if (isset($this->framework)) {
            return $this->framework;
        }

        $config = config('guardian.framework');

        $this->framework = match (true) {
            $config instanceof Framework => $config,
            default => Framework::tryFrom($config) ?? Framework::Livewire,
        };

        return $this->framework;
    }

    public function getFrameworkAdapter(): FrameworkAdapter
    {
        return app(FrameworkResolver::class)->adapter($this->getFramework());
    }
}
