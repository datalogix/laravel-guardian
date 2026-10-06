<?php

namespace Datalogix\Guardian\Exceptions;

class FrameworkConfigurationException extends GuardianException
{
    public static function dependencyMissing(string $framework, string $package): static
    {
        return new static(
            "The [{$framework}] framework integration requires the [{$package}] package, which is not installed. ".
            "Run [composer require {$package}], or pass your own route action to every feature you enable."
        );
    }

    public static function tallkitMissing(string $fortressId): static
    {
        return new static(
            "The [{$fortressId}] fortress renders the bundled Livewire views, which are built with datalogix/tallkit. ".
            'Run [composer require datalogix/tallkit], publish the views (--tag=guardian-views) '.
            "or give the fortress views and a layout of its own (->livewire(views: '...'), ->layout('...'))."
        );
    }
}
