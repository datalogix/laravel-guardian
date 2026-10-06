<?php

namespace Datalogix\Guardian\Support\Auth;

use Datalogix\Guardian\Guardian;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Laravel sends guests to route('login'), which Guardian does not define: when it is
 * missing, guests go to the fortress login. A destination set by the app is kept.
 */
class GuestRedirect
{
    protected const CLASSES = [
        Authenticate::class,
        AuthenticateSession::class,
        AuthenticationException::class,
    ];

    public function __construct(
        protected mixed $previous,
    ) {}

    /**
     * Laravel resets its default when the HTTP kernel is made, so this runs again then.
     */
    public static function wrap(): void
    {
        foreach (self::CLASSES as $class) {
            // The destination is a protected static property of the class.
            $previous = (fn () => static::$redirectToCallback)->bindTo(null, $class)();

            if (! $previous instanceof self) {
                $class::redirectUsing(new self($previous));
            }
        }
    }

    public static function forget(): void
    {
        foreach (self::CLASSES as $class) {
            (fn () => static::$redirectToCallback = null)->bindTo(null, $class)();
        }
    }

    public function __invoke(Request $request): ?string
    {
        try {
            return is_callable($this->previous) ? call_user_func($this->previous, $request) : null;
        } catch (RouteNotFoundException $exception) {
            return Guardian::getCurrentOrDefaultFortress()->loginUrl() ?? throw $exception;
        }
    }
}
