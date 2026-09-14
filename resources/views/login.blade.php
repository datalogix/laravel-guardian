<tk:page.auth.login
    identifier:name="login"
    :identifier="$identifierKey->value"
    :forgot-password-url="guardian()->forgotPasswordUrl()"
    :sign-up-url="guardian()->signUpUrl()"
    :oauth="guardian()->getOAuthProviders()"
/>
