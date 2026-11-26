<?php

namespace App\Enums;

enum SumberTelemetri: string
{
    case Higertech = 'higertech';
    case Tatonas = 'tatonas';
    case Stesy = 'stesy';
    /** log.pcbjogja.com */
    case PcbJogja = 'pcb-jogja';
    /** Perum Jasa Tirta I */
    case Pjt = 'pjt';
    /** Sensor via hidrologi.bbwsserayuopak.id (ref_pos.pos_tele + pos_sensor) */
    case Beacon = 'beacon';
    /** SCADASH Bendungan Sermo (202.180.30.82). Dulu ditarik aplikasi hidrologi, bukan SIH3. */
    case Sermo = 'sermo';

    public function label(): string
    {
        return match ($this) {
            self::Higertech => 'Higertech',
            self::Tatonas => 'Tatonas',
            self::Stesy => 'Stesy',
            self::PcbJogja => 'PCB Jogja',
            self::Pjt => 'Perum Jasa Tirta I',
            self::Beacon => 'Beacon',
            self::Sermo => 'SCADASH Sermo',
        };
    }
}
