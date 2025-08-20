<?php

namespace Datalogix\Guardian;

class Fortress
{
    use Concerns\HasAuth;
    use Concerns\HasComponents;
    use Concerns\HasDefault;
    use Concerns\HasEmailVerification;
    use Concerns\HasFramework;
    use Concerns\HasId;
    use Concerns\HasIdentifier;
    use Concerns\HasLayouts;
    use Concerns\HasLifecycleHooks;
    use Concerns\HasLogin;
    use Concerns\HasLogout;
    use Concerns\HasMiddleware;
    use Concerns\HasModes;
    use Concerns\HasPasswordConfirmation;
    use Concerns\HasPasswordReset;
    use Concerns\HasRoutes;
    use Concerns\HasSignUp;

    public static function make(): static
    {
        return app(static::class);
    }
}
