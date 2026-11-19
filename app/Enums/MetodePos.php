<?php

namespace App\Enums;

enum MetodePos: string
{
    case Manual = 'manual';
    case Telemetri = 'telemetri';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::Telemetri => 'Telemetri',
        };
    }
}
