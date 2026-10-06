<?php

namespace Datalogix\Guardian\Framework;

use Datalogix\Guardian\Enums\Framework;
use InvalidArgumentException;

class FrameworkResolver
{
    /**
     * @var array<string, FrameworkAdapter>
     */
    protected array $adapters = [];

    public function register(FrameworkAdapter $adapter): void
    {
        $this->adapters[$adapter->framework()->value] = $adapter;
    }

    /**
     * @return array<string, FrameworkAdapter>
     */
    public function adapters(): array
    {
        return $this->adapters;
    }

    public function adapter(?Framework $framework = null): FrameworkAdapter
    {
        $framework ??= $this->configuredFramework();

        return $this->adapters[$framework->value]
            ?? throw new InvalidArgumentException("No framework adapter registered for framework [{$framework->value}].");
    }

    public function resolveComponent(string $componentName, ?Framework $framework = null): string
    {
        return $this->adapter($framework)->pageAction($componentName);
    }

    protected function configuredFramework(): Framework
    {
        $config = config('guardian.framework');

        $framework = $config instanceof Framework ? $config : Framework::tryFrom($config);

        return $framework ?? throw new InvalidArgumentException('Unknown framework configured');
    }
}
