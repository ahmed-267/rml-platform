<?php

namespace App\Support;

class ZoneDisplay
{
    public static function code(?string $code): string
    {
        if ($code === null || trim($code) === '') {
            return __('rml.common.zone_not_set');
        }

        return $code;
    }
}
