@php
use Datalogix\Guardian\Enums\IdentifierKey;
@endphp

<tk:auth.pages.sign-up :login="guardian()->getLoginUrl()">
    @if ($identifierKey !== IdentifierKey::Email)
        <tk:input
            name="username"
            required
            autocomplete="username"
            placeholder
        />
    @endif
</tk:auth.pages.sign-up>
