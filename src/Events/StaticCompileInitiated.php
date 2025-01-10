<?php

namespace PHPNomad\Static\Events;

use PHPNomad\Events\Interfaces\Event;

class StaticCompileInitiated implements Event
{
    public static function getId() : string
    {
        return 'staticCompileInitiated';
    }
}