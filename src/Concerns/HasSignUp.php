<?php

namespace Datalogix\Guardian\Concerns;

use BackedEnum;
use Closure;
use Datalogix\Guardian\Features\SignUpFeature;

trait HasSignUp
{
    protected ?SignUpFeature $signUpFeature = null;

    protected string|Closure|null $signUpTermsUrl = null;

    public function getSignUpFeature(): SignUpFeature
    {
        return $this->signUpFeature ??= new SignUpFeature($this);
    }

    public function signUp(
        string|Closure|array|false|null $routeAction = null,
        ?string $routeSlug = null,
        ?string $routeName = null,
        string|Closure|null $response = null,
        int|false|null $maxAttempts = null,
        BackedEnum|string|null $layout = null,
        string|Closure|null $termsUrl = null,
    ): static {
        $this->getSignUpFeature()->configure(
            $routeAction,
            $routeSlug,
            $routeName,
            $response,
            $maxAttempts,
            $layout,
        );

        $this->signUpTermsUrl = $termsUrl;

        return $this;
    }

    public function getSignUpTermsUrl(): ?string
    {
        $url = value($this->signUpTermsUrl);

        return filled($url) ? $url : null;
    }

    public function signUpRoutes(): static
    {
        $this->getSignUpFeature()->registerRoutesIfEnabled();

        return $this;
    }

    public function signUpUrl(): ?string
    {
        return $this->getSignUpFeature()->getUrl();
    }
}
