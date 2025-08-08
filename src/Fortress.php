<?php

namespace Datalogix\Fortress;

class Fortress
{
    use Concerns\HasAuth;
    use Concerns\HasComponents;
    use Concerns\HasDefault;
    use Concerns\HasId;
    use Concerns\HasLifecycleHooks;
    use Concerns\HasMiddleware;
    use Concerns\HasModes;
    use Concerns\HasRoutes;

    public static function make(): static
    {
        return app(static::class);
    }
}
