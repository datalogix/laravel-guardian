<tk:page.auth.sign-up
    identifier:name="login"
    :identifier="$identifierKey->value"
    :login-url="guardian()->loginUrl()"
    :oauth="guardian()->getOAuthProviders()"
/>
