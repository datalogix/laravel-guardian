<?php

use Datalogix\Guardian\GuardianManager;

if (! function_exists('guardian')) {
    function guardian(): GuardianManager
    {
        return app('guardian');
    }
}
