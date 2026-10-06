<?php

namespace Illuminate\Notifications\Messages;

// A minimal stand-in for laravel-notification-channels/vonage's VonageMessage,
// which this package never depends on. Loading this lets tests exercise the
// `class_exists(...)` branches that only activate when that optional package
// is installed, without adding it as a real dependency. Must only be required
// from a #[RunInSeparateProcess] test: once declared, it exists for the rest
// of the PHP process and would break the "channel unavailable" tests.
if (! class_exists(VonageMessage::class)) {
    class VonageMessage
    {
        public string $content = '';

        public function content(string $content): static
        {
            $this->content = $content;

            return $this;
        }
    }
}
