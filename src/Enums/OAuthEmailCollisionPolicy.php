<?php

namespace Datalogix\Guardian\Enums;

enum OAuthEmailCollisionPolicy: string
{
    case LinkExisting = 'link_existing';

    case DenyWithError = 'deny_with_error';

    case RequireManualLink = 'require_manual_link';
}
