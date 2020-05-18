<?php

namespace Anaplam;

class LengthExceedException extends \RuntimeException
{
    public function __construct($resource, $name, $value, $length)
    {
        parent::__construct($resource . " '" . $name . "' must not exceed maximum length " . $length . ". (`" . $value . "`)");
    }
}

