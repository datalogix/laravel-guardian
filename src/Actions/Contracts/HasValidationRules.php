<?php

namespace Datalogix\Guardian\Actions\Contracts;

interface HasValidationRules
{
    public static function rules(): array;
}
