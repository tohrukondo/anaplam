<?php

namespace Anaplam;

class ResourceAlreadyExistException extends \RuntimeException
{
    public function __construct($resource, $name, $value)
    {
        parent::__construct($resource . " '" . $name . "' already exists. (`" . $value . "`)");
    }
}

