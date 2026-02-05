<?php

namespace App\Exception;

use Exception;

class ProjectAlreadyExistsException extends Exception
{
    public function __construct($message = "Project already exists")
    {
        parent::__construct($message);
    }
}
