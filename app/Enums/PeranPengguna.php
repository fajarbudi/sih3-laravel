<?php

namespace App\Enums;

enum PeranPengguna: string
{
    /** level 9 di sistem lama */
    case Superadmin = 'superadmin';
    /** level 5: admin satu walidata */
    case AdminWalidata = 'admin_walidata';
    /** level 3: petugas satu pos */
    case PetugasPos = 'petugas_pos';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::AdminWalidata => 'Admin Walidata',
            self::PetugasPos => 'Petugas Pos',
        };
    }

    public static function dariLevelLama(int $level): self
    {
        return match ($level) {
            9 => self::Superadmin,
            5 => self::AdminWalidata,
            default => self::PetugasPos,
        };
    }
}
