<tk:page.auth.two-factor :method="$method->value">
    @if (guardian()->shouldTwoFactorRememberOnDevice())
        <tk:checkbox
            :label="__('Remember this device for :days days', ['days' => guardian()->getTwoFactorRememberForDays()])"
            name="remember_device"
        />
    @endif
</tk:page.auth.two-factor>
