<?php

namespace Datalogix\Guardian\Framework;

interface ComponentFactory
{
    public function resolve(string $componentName): string;
}
