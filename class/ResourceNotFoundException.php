<?php

namespace Anaplam;

class ResourceNotFoundException extends \RuntimeException
{
    public function __construct($resource, $name)
    {
        parent::__construct($resource . " '" . $name . "' not found.");
    }
}

