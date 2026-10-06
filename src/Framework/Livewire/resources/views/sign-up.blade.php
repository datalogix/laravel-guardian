<tk:page.auth.sign-up
    identifier:name="login"
    :identifier="$identifierKey->value"
    :login-url="guardian()->loginUrl()"
    :terms-url="guardian()->getSignUpTermsUrl()"
    :oauth="guardian()->getOAuthProviders()"
/>
