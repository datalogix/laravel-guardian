<?php

namespace Datalogix\Guardian\Http\Livewire;

use Datalogix\Guardian\Actions\ForgotPassword as ForgotPasswordAction;
use Datalogix\Guardian\Enums\IdentifierKey;
use Datalogix\Guardian\Guardian;
use Livewire\Attributes\Locked;

class ForgotPassword extends Page
{
    #[Locked()]
    public IdentifierKey $identifierKey;

    public string $login = '';

    public function mount()
    {
        $this->identifierKey = Guardian::getIdentifierKey();
    }

    public function submit()
    {
        $data = $this->validate(ForgotPasswordAction::rules());

        $status = app(ForgotPasswordAction::class)($data);

        return app(Guardian::getForgotPasswordFeature()->getResponse(), ['status' => $status]);
    }
}
