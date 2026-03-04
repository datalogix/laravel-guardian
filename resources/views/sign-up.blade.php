@php
use Datalogix\Guardian\Enums\IdentifierKey;
@endphp

<tk:page.auth.sign-up :login="guardian()->getLoginFeature()->getUrl()">
    @if ($identifierKey !== IdentifierKey::Email)
        <tk:input
            name="username"
            required
            autocomplete="username"
            placeholder
        />
    @endif
</tk:page.auth.sign-up>
