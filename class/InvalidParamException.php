<?php

namespace Anaplam;

class InvalidParamException extends \RuntimeException
{
    public function __construct($resource, $name, $value)
    {
        parent::__construct($resource . " '" . $name . "' is invalid. (`" . $value . "`)");
    }
}

