<?php

namespace PHPNomad\Static\Events;

use PHPNomad\Events\Interfaces\Event;

class StaticCompileRequested implements Event
{
    public static function getId() : string
    {
        return 'staticCompileRequested';
    }
}