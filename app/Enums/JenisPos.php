<?php

namespace App\Enums;

enum JenisPos: string
{
    /** Automatic Rainfall Recorder (curah hujan) */
    case Arr = 'arr';
    /** Automatic Water Level Recorder (tinggi muka air sungai) */
    case Awlr = 'awlr';
    case Klimatologi = 'klimatologi';
    case KualitasAir = 'kualitas_air';
    /** Muka air tanah */
    case Mat = 'mat';
    /** Automatic Groundwater Level Recorder */
    case Agwlr = 'agwlr';

    public function label(): string
    {
        return match ($this) {
            self::Arr => 'ARR',
            self::Awlr => 'AWLR',
            self::Klimatologi => 'Klimatologi',
            self::KualitasAir => 'Kualitas Air',
            self::Mat => 'MAT',
            self::Agwlr => 'AGWLR',
        };
    }

    public function tipe(): TipePos
    {
        return match ($this) {
            self::Arr, self::Klimatologi => TipePos::Hidrometeorologi,
            self::Awlr, self::KualitasAir => TipePos::Hidrologi,
            self::Mat, self::Agwlr => TipePos::Hidrogeologi,
        };
    }
}
