<?php

namespace Datalogix\Guardian\Actions;

use Datalogix\Guardian\Actions\Contracts\HasValidationRules;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorSetupManager;

class EnableTwoFactor implements HasValidationRules
{
    public function __construct(
        protected TwoFactorSetupManager $setupManager,
    ) {}

    /**
     * @return array<int, string>
     */
    public function __invoke(object $user, array $data = []): array
    {
        return $this->setupManager->enableFromPendingSetup(
            user: $user,
            code: (string) ($data['code'] ?? ''),
        );
    }

    public static function rules(): array
    {
        return [
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }
}
