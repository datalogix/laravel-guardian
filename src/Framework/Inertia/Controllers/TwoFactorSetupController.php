<?php

namespace Datalogix\Guardian\Framework\Inertia\Controllers;

use Datalogix\Guardian\Actions\DisableTwoFactor;
use Datalogix\Guardian\Actions\EnableTwoFactor;
use Datalogix\Guardian\Actions\PrepareTwoFactorSetup;
use Datalogix\Guardian\Actions\RegenerateTwoFactorRecoveryCodes;
use Datalogix\Guardian\Exceptions\PasswordConfirmationException;
use Datalogix\Guardian\Guardian;
use Datalogix\Guardian\Http\Responses\Concerns\RedirectsToTwoFactorSetup;
use Datalogix\Guardian\Response\Redirector;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorSetupContext;
use Datalogix\Guardian\Support\TwoFactor\TwoFactorUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TwoFactorSetupController extends PageController
{
    use RedirectsToTwoFactorSetup;

    public const RECOVERY_CODES_FLASH = 'guardian.two_factor_setup.recovery_codes';

    public const AWAITING_CONTINUE_FLASH = 'guardian.two_factor_setup.awaiting_continue';

    protected static function page(): string
    {
        return 'two-factor-setup';
    }

    public static function endpoints(): array
    {
        return [
            'prepare' => ['post', '/prepare', 'prepare'],
            'enable' => ['post', '/enable', 'enable'],
            'continue' => ['post', '/continue', 'continueAfterSetup'],
            'disable' => ['delete', '', 'disable'],
            'recovery-codes' => ['post', '/recovery-codes', 'regenerateRecoveryCodes'],
            'trusted-devices.destroy-all' => ['delete', '/trusted-devices', 'revokeAllTrustedDevices'],
            'trusted-devices.destroy' => ['delete', '/trusted-devices/{device}', 'revokeTrustedDevice', ['device' => '[0-9]+']],
        ];
    }

    public function __invoke(Request $request)
    {
        if (! Guardian::isAuthenticated() && ! Guardian::hasPendingTwoFactorSetup()) {
            return Redirector::redirectToLogin(intended: true);
        }

        $this->setup()->abortIfCannotAccess($this->setup()->user());

        return $this->render($request);
    }

    protected function props(Request $request): array
    {
        $user = $this->setup()->user();
        $state = $this->setup()->summary($user);
        $pending = $state['enabled'] ? null : $this->setup()->pendingSetup($user);

        return [
            'enabled' => $state['enabled'],
            'secretUnreadable' => $state['secretUnreadable'],
            'canDisable' => $state['canDisable'],
            'method' => ($pending['method'] ?? $this->setup()->method())->value,
            'secret' => $pending['secret'] ?? null,
            'uri' => $pending['uri'] ?? null,
            'qrSvg' => $pending['qrSvg'] ?? null,
            'canManageRecoveryCodes' => $state['canManageRecoveryCodes'],
            'recoveryCodesCount' => $state['recoveryCodesCount'],
            'recoveryCodes' => session(self::RECOVERY_CODES_FLASH) ?? [],
            'trustedDevices' => $this->trustedDevices($state['trustedDevices']),
            'awaitingContinueAfterSetup' => (bool) session(self::AWAITING_CONTINUE_FLASH, false),
        ];
    }

    public function prepare()
    {
        $user = $this->setup()->authorizedUser();

        if (! $user || ! app(TwoFactorUser::class)->canStoreTwoFactorSecret($user)) {
            return back();
        }

        try {
            app(PrepareTwoFactorSetup::class)($user, $this->setup()->method());
        } catch (PasswordConfirmationException $exception) {
            return $this->setup()->redirectToPasswordConfirmation($exception);
        }

        return back();
    }

    public function enable(Request $request)
    {
        $user = $this->setup()->authorizedUser();

        if (! $user) {
            return back();
        }

        $wasPendingSetup = Guardian::hasPendingTwoFactorSetup();

        $action = app(EnableTwoFactor::class);
        $data = $request->validate($action::rules());

        try {
            $recoveryCodes = $action($user, $data);
        } catch (PasswordConfirmationException $exception) {
            return $this->setup()->redirectToPasswordConfirmation($exception);
        }

        session()->flash(self::RECOVERY_CODES_FLASH, $recoveryCodes);
        session()->flash(self::AWAITING_CONTINUE_FLASH, $wasPendingSetup);

        return back();
    }

    public function continueAfterSetup(Request $request)
    {
        if (! Guardian::isAuthenticated()) {
            return Redirector::redirectToLogin();
        }

        return $this->respond(
            $this->redirectForRequiredEmailVerification() ?? app(Guardian::getLoginFeature()->getResponse()),
            $request,
        );
    }

    public function disable()
    {
        $user = $this->setup()->authorizedUser();

        if (! $user) {
            return back();
        }

        try {
            app(DisableTwoFactor::class)($user);
        } catch (PasswordConfirmationException $exception) {
            return $this->setup()->redirectToPasswordConfirmation($exception);
        }

        return back();
    }

    public function regenerateRecoveryCodes()
    {
        $user = $this->setup()->authorizedUser();

        if (! $user) {
            return back();
        }

        try {
            $recoveryCodes = app(RegenerateTwoFactorRecoveryCodes::class)($user);
        } catch (PasswordConfirmationException $exception) {
            return $this->setup()->redirectToPasswordConfirmation($exception);
        }

        session()->flash(self::RECOVERY_CODES_FLASH, $recoveryCodes);

        return back();
    }

    public function revokeTrustedDevice(int $device)
    {
        $user = $this->setup()->authorizedUser(requireAuthenticated: true);

        if ($user instanceof Model) {
            Guardian::revokeTrustedTwoFactorDevice($user, $device);
        }

        return back();
    }

    public function revokeAllTrustedDevices()
    {
        $user = $this->setup()->authorizedUser(requireAuthenticated: true);

        if ($user instanceof Model) {
            Guardian::revokeAllTrustedTwoFactorDevices($user);
            Guardian::forgetRememberedTwoFactorDevice();
        }

        return back();
    }

    protected function setup(): TwoFactorSetupContext
    {
        return app(TwoFactorSetupContext::class);
    }

    protected function trustedDevices(array $devices): array
    {
        $feature = $this->feature();

        return array_map(fn (array $device) => [
            ...$device,
            'revokeUrl' => $feature->getEndpointUrl('trusted-devices.destroy', ['device' => $device['id']]),
        ], $devices);
    }
}
