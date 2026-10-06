<tk:page.auth.oauth-complete-registration
    identifier:name="login"
    :identifier="$identifierKey->value"
    :provider="Arr::get(guardian()->getPendingOAuthRegistrationSession(), 'provider')"
    :email="Arr::get(guardian()->getPendingOAuthRegistrationSession(), 'email')"
/>
