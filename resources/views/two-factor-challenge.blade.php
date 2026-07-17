<div>
    <h2>Two-factor authentication</h2>

    @if ($method === \Datalogix\Guardian\Enums\TwoFactorMethod::Totp)
        <p>Enter the 6-digit authentication code from your authenticator app or a recovery code.</p>
    @elseif ($method === \Datalogix\Guardian\Enums\TwoFactorMethod::Email)
        <p>Enter the 6-digit authentication code sent to your email or a recovery code.</p>
    @elseif ($method === \Datalogix\Guardian\Enums\TwoFactorMethod::Sms)
        <p>Enter the 6-digit authentication code sent by SMS or a recovery code.</p>
    @else
        <p>Enter your authentication code or a recovery code.</p>
    @endif

    <form wire:submit="submit">
        <div>
            <label for="code">Authentication or recovery code</label>

            <input
                id="code"
                type="text"
                autocomplete="one-time-code"
                maxlength="64"
                wire:model="code"
            >

            @error('code')
                <div>{{ $message }}</div>
            @enderror
        </div>

        @if (guardian()->shouldTwoFactorRememberOnDevice())
            <label>
                <input type="checkbox" wire:model="remember_device">
                Remember this device for {{ guardian()->getTwoFactorRememberForDays() }} days
            </label>
        @endif

        <button type="submit">Verify code</button>
    </form>

    @if (in_array($method, [\Datalogix\Guardian\Enums\TwoFactorMethod::Email, \Datalogix\Guardian\Enums\TwoFactorMethod::Sms], true))
        <button type="button" wire:click="resend">Resend code</button>
    @endif
</div>
