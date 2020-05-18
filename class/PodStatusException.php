<?php

namespace Anaplam;

class PodStatusException extends \RuntimeException
{
    public function __construct($status)
    {
        parent::__construct("create pod initialize failed. status is '" . $status . "'.");
    }
}

