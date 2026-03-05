<div>
    <h2>Two-factor authentication</h2>

    <p>Enter the 6-digit authentication code from your authenticator app or a recovery code.</p>

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

        @if (guardian()->shouldRememberTwoFactorOnDevice())
            <label>
                <input type="checkbox" wire:model="remember_device">
                Remember this device for {{ guardian()->getRememberTwoFactorForDays() }} days
            </label>
        @endif

        <button type="submit">Verify code</button>
    </form>
</div>
