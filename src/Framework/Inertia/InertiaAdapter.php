<?php

namespace Datalogix\Guardian\Framework\Inertia;

use Datalogix\Guardian\Enums\Framework;
use Datalogix\Guardian\Features\Feature;
use Datalogix\Guardian\Fortress;
use Datalogix\Guardian\Framework\AbstractFrameworkAdapter;
use Datalogix\Guardian\Framework\Inertia\Controllers\ConfirmPasswordController;
use Datalogix\Guardian\Framework\Inertia\Controllers\EmailVerificationPromptController;
use Datalogix\Guardian\Framework\Inertia\Controllers\ForgotPasswordController;
use Datalogix\Guardian\Framework\Inertia\Controllers\LoginController;
use Datalogix\Guardian\Framework\Inertia\Controllers\OAuthCompleteRegistrationController;
use Datalogix\Guardian\Framework\Inertia\Controllers\PageController;
use Datalogix\Guardian\Framework\Inertia\Controllers\ResetPasswordController;
use Datalogix\Guardian\Framework\Inertia\Controllers\SignUpController;
use Datalogix\Guardian\Framework\Inertia\Controllers\TwoFactorChallengeController;
use Datalogix\Guardian\Framework\Inertia\Controllers\TwoFactorSetupController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

class InertiaAdapter extends AbstractFrameworkAdapter
{
    public const DEFAULT_PREFIX = 'Guardian';

    public function framework(): Framework
    {
        return Framework::Inertia;
    }

    public function package(): string
    {
        return 'inertiajs/inertia-laravel';
    }

    protected function requiredClass(): string
    {
        return Inertia::class;
    }

    protected function pages(): array
    {
        return [
            'login' => LoginController::class,
            'sign-up' => SignUpController::class,
            'forgot-password' => ForgotPasswordController::class,
            'reset-password' => ResetPasswordController::class,
            'confirm-password' => ConfirmPasswordController::class,
            'email-verification-prompt' => EmailVerificationPromptController::class,
            'two-factor-setup' => TwoFactorSetupController::class,
            'two-factor-challenge' => TwoFactorChallengeController::class,
            'oauth-complete-registration' => OAuthCompleteRegistrationController::class,
        ];
    }

    /**
     * The Inertia page component rendered for a bundled page, e.g. "Guardian/Login":
     * the one the fortress names in ->inertia(pages: [...]) or else "{prefix}/{Page}".
     */
    public static function component(string $page, ?Fortress $fortress = null): string
    {
        $pages = $fortress?->getFrameworkOption('pages', []) ?? [];
        $prefix = $fortress?->getFrameworkOption('prefix') ?? self::DEFAULT_PREFIX;

        return $pages[$page] ?? rtrim($prefix, '/').'/'.str_replace('Oauth', 'OAuth', Str::studly($page));
    }

    public function registerPageRoutes(Feature $feature, array|string $middleware): void
    {
        $controller = $feature->getRouteAction();

        // A custom route action owns its endpoints; only the bundled controllers are wired here.
        if (! is_string($controller) || ! is_subclass_of($controller, PageController::class)) {
            return;
        }

        // "signed" protects the link that opens the page (e.g. from an e-mail), not the endpoints it posts to.
        $middleware = array_values(array_diff(array_filter(Arr::wrap($middleware)), ['signed']));

        foreach ($controller::endpoints() as $name => $endpoint) {
            [$method, $uri, $action] = $endpoint;

            Route::{$method}($feature->getRouteSlug().$uri, [$controller, $action])
                ->middleware($middleware)
                ->where($endpoint[3] ?? [])
                ->name($feature->getEndpointName($name));
        }
    }
}
