<?php

namespace Datalogix\Guardian\Enums;

enum AuthFlowResult: string
{
    case ChallengeRequired = 'challenge-required';
    case SetupRequired = 'setup-required';
    case Authenticated = 'authenticated';
}
