<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\CompleteOAuthRegistration as CompleteOAuthRegistrationAction;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Guardian;
use Livewire\Attributes\Locked;

class OAuthCompleteRegistration extends Page
{
    #[Locked]
    public IdentifierKey $identifierKey;

    public string $login = '';

    public function mount()
    {
        // The route's EnsureOAuthCompleteRegistrationAccess middleware already
        // guarantees a pending OAuth registration exists before this page loads,
        // and CompleteOAuthRegistration re-checks it on submit().
        $this->identifierKey = Guardian::getIdentifierKey();
    }

    public function submit()
    {
        $data = $this->validate(CompleteOAuthRegistrationAction::rules());

        $result = app(CompleteOAuthRegistrationAction::class)($data);

        return Guardian::respondToAuthFlow($result, Guardian::getOAuthFeature()->getResponse());
    }
}
