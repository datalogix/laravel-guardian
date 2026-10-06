@php

use Datalogix\Guardian\Enums\TwoFactorMethod;

@endphp
@if ($enabled)
    <tk:section
        title="Two-factor setup"
        subtitle="Two-factor authentication is enabled for your account."
        :separator="false"
    >
        @if ($secretUnreadable)
            <tk:text label="We could not verify your two-factor secret. Please disable and set up two-factor authentication again." />
        @endif

        @unless ($awaitingContinueAfterSetup)
            <tk:button action="disable" label="Disable two-factor" />
        @endunless

        @if (count($recoveryCodes) > 0)
            <tk:separator />
            <tk:text label="Recovery codes (store them safely):" />
            <tk:list :items="$recoveryCodes" />
        @elseif ($canManageRecoveryCodes)
            <tk:separator />
            <tk:text :label="__('Recovery codes are configured for your account (:count available).', ['count' => $recoveryCodesCount])" />
        @endif

        @if ($awaitingContinueAfterSetup)
            <tk:button action="continueAfterSetup" label="Continue" />
        @elseif (count($recoveryCodes) > 0 || $canManageRecoveryCodes)
            <tk:button action="regenerateRecoveryCodes" label="Regenerate recovery codes" />
        @endif

        @if (count($trustedDevices) > 0)
            <tk:separator />
            <tk:text label="Trusted devices:" />
            <tk:table border :cols="['Device name', 'IP address', 'Last used', '']">
                <tk:table.rows>
                    @foreach ($trustedDevices as $trustedDevice)
                        <tk:table.row>
                            <tk:table.cell>{{ $trustedDevice['name'] ?? __('Trusted device') }}</tk:table.cell>
                            <tk:table.cell>{{ $trustedDevice['ip_address'] ?? __('Unknown IP') }}</tk:table.cell>
                            <tk:table.cell>{{ $trustedDevice['last_used_at'] ?? __('Never used') }}</tk:table.cell>
                            <tk:table.cell align="right"><tk:button action="revokeTrustedDevice({{ $trustedDevice['id'] }})" label="Revoke" /></tk:table.cell>
                        </tk:table.row>
                    @endforeach
                </tk:table.rows>
            </tk:table>
            <tk:button action="revokeAllTrustedDevices" label="Revoke all trusted devices" />
        @endif
    </tk:section>
@else

    <tk:section
        title="Two-factor setup"
        subtitle="Two-factor authentication is disabled."
        :separator="false"
    >
        <tk:button action="prepare" label="Generate setup secret" />

        @if ($secret)
            <div class="space-y-6">
                <tk:separator />

                @if ($method === TwoFactorMethod::Totp && $qrSvg)
                    <div class="mx-auto" role="img" aria-label="{{ __('QR code for authenticator app setup') }}">{!! $qrSvg !!}</div>
                @endif

                @if ($method === TwoFactorMethod::Totp)
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <tk:text label="Use this secret in your authenticator app:" />
                            <pre class="whitespace-pre-wrap break-all border border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5 p-4 rounded-lg">{{ $secret }}</pre>
                            <tk:copyable :content="$secret" label="Copy secret" />
                        </div>
                        <div class="space-y-2">
                            <tk:text label="OTPAuth URI (for QR generation):" />
                            <pre class="whitespace-pre-wrap break-all border border-zinc-200 dark:border-white/10 bg-zinc-50 dark:bg-white/5 p-4 rounded-lg">{{ $uri }}</pre>
                            <tk:copyable :content="$uri" label="Copy URI" />
                        </div>
                    </div>
                @else
                    <tk:text
                        :label="match ($method) {
                            TwoFactorMethod::Email => 'We sent a 6-digit verification code to your email. Enter it below to enable two-factor authentication.',
                            TwoFactorMethod::Sms => 'We sent a 6-digit verification code by SMS. Enter it below to enable two-factor authentication.',
                            default => false,
                        }"
                    />
                @endif

                <tk:form action="enable" submit:label="Enable two-factor">
                    <tk:otp label="Verification code" name="code" />
                </tk:form>
            </div>
        @endif
    </tk:section>
@endif
