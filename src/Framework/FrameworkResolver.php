<?php

namespace Datalogix\Guardian\Framework;

use Datalogix\Guardian\Enums\Framework;
use InvalidArgumentException;

class FrameworkResolver
{
    /**
     * @var array<string, ComponentFactory>
     */
    protected array $factories = [];

    public function register(Framework $framework, ComponentFactory $factory): void
    {
        $this->factories[$framework->value] = $factory;
    }

    public function resolveComponent(string $componentName, ?Framework $framework = null): string
    {
        $framework ??= Framework::tryFrom(config('guardian.framework'));

        if (! $framework) {
            throw new InvalidArgumentException('Unknown framework configured');
        }

        $key = $framework->value;

        if (! isset($this->factories[$key])) {
            throw new InvalidArgumentException("No component factory registered for framework [{$key}].");
        }

        return $this->factories[$key]->resolve($componentName);
    }
}
