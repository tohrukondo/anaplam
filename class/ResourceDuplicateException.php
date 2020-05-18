<?php

namespace Anaplam;

class ResourceDuplicateException extends \RuntimeException
{
    public function __construct($resource, $name, $value)
    {
        parent::__construct($resource . " '" . $name . "' is duplicated. (`" . $value . "`)");
    }
}

