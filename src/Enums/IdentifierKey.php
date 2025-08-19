<?php

namespace Datalogix\Guardian\Enums;

enum IdentifierKey: string
{
    case Email = 'email';
    case Username = 'username';
    case Both = 'both';
}
