<?php

namespace TALLKit;

// Stand-in for the optional tallkit package. Only require it from a #[RunInSeparateProcess]
// test: once declared, it stays for the whole process.
if (! class_exists(TALLKitServiceProvider::class)) {
    class TALLKitServiceProvider {}
}
