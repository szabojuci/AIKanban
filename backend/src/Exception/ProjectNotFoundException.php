<?php

namespace App\Exception;

use Exception;

class ProjectNotFoundException extends Exception
{
    public function __construct($message = "Project not found")
    {
        parent::__construct($message);
    }
}
