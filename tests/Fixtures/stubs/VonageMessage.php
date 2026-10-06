<?php

namespace Illuminate\Notifications\Messages;

// Stand-in for the optional VonageMessage. Only require it from a #[RunInSeparateProcess]
// test: once declared, it stays for the whole process.
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
