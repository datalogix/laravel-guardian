@php
use Datalogix\Guardian\Enums\IdentifierKey;
@endphp

<tk:auth.pages.login
    email:name="login"
    email:type="{{ match($identifierKey) {
        IdentifierKey::Email => 'email',
        IdentifierKey::Username => 'text',
        IdentifierKey::Both => 'text',
    } }}"
    email:label="{{ match($identifierKey) {
        IdentifierKey::Email => 'E-mail',
        IdentifierKey::Username => 'Username',
        IdentifierKey::Both => 'E-mail or Username',
    } }}"
    email:autocomplete="{{ match($identifierKey) {
        IdentifierKey::Email => 'email',
        IdentifierKey::Username => 'username',
        IdentifierKey::Both => 'username',
    } }}"
    :forgot-password="guardian()->getForgotPasswordUrl()"
    :sign-up="guardian()->getSignUpUrl()"
/>
