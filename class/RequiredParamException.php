<?php

namespace Anaplam;

class RequiredParamException extends \RuntimeException
{
    public function __construct($resource, $name)
    {
        parent::__construct($resource . " requires '" . $name . "'.");
    }
}

