<div>
    <h2>Two-factor setup</h2>

    @if ($enabled)
        <p>Two-factor authentication is enabled for your account.</p>

        <p>To disable two-factor, confirm your password first.</p>

        @if (guardian()->getPasswordConfirmationFeature()->getUrl())
            <a href="{{ guardian()->getPasswordConfirmationFeature()->getUrl() }}">Confirm password</a>
        @endif

        @error('password')
            <div>{{ $message }}</div>
        @enderror

        <button wire:click="disable" type="button">Disable two-factor</button>

        @if (count($recoveryCodes) > 0)
            <p>Recovery codes (store them safely):</p>

            <ul>
                @foreach ($recoveryCodes as $recoveryCode)
                    <li>{{ $recoveryCode }}</li>
                @endforeach
            </ul>

            <button wire:click="regenerateRecoveryCodes" type="button">Regenerate recovery codes</button>
        @elseif ($canManageRecoveryCodes)
            <p>Recovery codes are configured for your account ({{ $recoveryCodesCount }} available).</p>

            <button wire:click="regenerateRecoveryCodes" type="button">Regenerate recovery codes</button>
        @endif

        @if (count($trustedDevices) > 0)
            <p>Trusted devices:</p>

            <ul>
                @foreach ($trustedDevices as $trustedDevice)
                    <li>
                        <div>{{ $trustedDevice['name'] ?? 'Trusted device' }}</div>
                        <div>{{ $trustedDevice['ip_address'] ?? 'Unknown IP' }}</div>
                        <div>{{ $trustedDevice['last_used_at'] ?? 'Never used' }}</div>
                        <button wire:click="revokeTrustedDevice({{ $trustedDevice['id'] }})" type="button">Revoke</button>
                    </li>
                @endforeach
            </ul>

            <button wire:click="revokeAllTrustedDevices" type="button">Revoke all trusted devices</button>
        @endif
    @else
        <p>Two-factor authentication is disabled.</p>

        @if (! $secret)
            <button wire:click="prepare" type="button">Generate setup secret</button>
        @else
            @if ($qrSvg)
                <div>{!! $qrSvg !!}</div>
            @endif

            <p>Use this secret in your authenticator app:</p>

            <pre>{{ $secret }}</pre>

            <button
                type="button"
                @if ($secret)
                    onclick="navigator.clipboard?.writeText(@js($secret))"
                @endif
            >
                Copy secret
            </button>

            <p>OTPAuth URI (for QR generation):</p>

            <pre>{{ $uri }}</pre>

            <button
                type="button"
                @if ($uri)
                    onclick="navigator.clipboard?.writeText(@js($uri))"
                @endif
            >
                Copy URI
            </button>

            <form wire:submit="enable">
                <div>
                    <label for="code">Verification code</label>

                    <input
                        id="code"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        wire:model="code"
                    >

                    @error('code')
                        <div>{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit">Enable two-factor</button>
            </form>

            @if (count($recoveryCodes) > 0)
                <p>Recovery codes (showing once):</p>

                <ul>
                    @foreach ($recoveryCodes as $recoveryCode)
                        <li>{{ $recoveryCode }}</li>
                    @endforeach
                </ul>
            @endif
        @endif
    @endif
</div>
