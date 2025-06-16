<?php

namespace Oilstone\ApiContentfulIntegration\Exceptions;

class MethodNotAllowedException extends Exception
{
    public function __construct()
    {
        parent::__construct('This function is not permitted', 400);
    }
}
