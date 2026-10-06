<?php

namespace Datalogix\Guardian\Framework\Livewire\Pages;

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
        $action = app(ForgotPasswordAction::class);
        $data = $this->validate($action::rules());

        $status = $action($data);

        return app(Guardian::getForgotPasswordFeature()->getResponse(), ['status' => $status]);
    }
}
