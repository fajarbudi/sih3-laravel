<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Menghitung rekap per jam dari data telemetri mentah.
 *
 * Selalu dihitung ulang (SUM/AVG atas semua bacaan pada jam tsb) lalu di-upsert,
 * sehingga aman dijalankan berulang kali. Ini menggantikan pola lama
 * "ON DUPLICATE KEY UPDATE arr_hujan = arr_hujan + VALUES(arr_hujan)"
 * yang menggandakan nilai setiap kali cron membaca ulang data yang sama.
 */
class RekapTelemetri
{
    public function hitungUlangArr(?CarbonInterface $dari = null, ?CarbonInterface $sampai = null, ?int $posId = null): int
    {
        [$where, $bindings] = $this->filter($dari, $sampai, $posId);

        return DB::affectingStatement("
            INSERT INTO rekap_arr_per_jam (pos_id, jam, hujan, jumlah_data, diperbarui_at)
            SELECT pos_id, DATE_FORMAT(waktu, '%Y-%m-%d %H:00:00'), SUM(hujan), COUNT(*), NOW()
            FROM telemetri_arr
            {$where}
            GROUP BY pos_id, DATE_FORMAT(waktu, '%Y-%m-%d %H:00:00')
            ON DUPLICATE KEY UPDATE
                hujan = VALUES(hujan),
                jumlah_data = VALUES(jumlah_data),
                diperbarui_at = VALUES(diperbarui_at)
        ", $bindings);
    }

    public function hitungUlangAwlr(?CarbonInterface $dari = null, ?CarbonInterface $sampai = null, ?int $posId = null): int
    {
        [$where, $bindings] = $this->filter($dari, $sampai, $posId);

        return DB::affectingStatement("
            INSERT INTO rekap_awlr_per_jam (pos_id, jam, tma_rata, tma_min, tma_max, jumlah_data, diperbarui_at)
            SELECT pos_id, DATE_FORMAT(waktu, '%Y-%m-%d %H:00:00'), AVG(tma), MIN(tma), MAX(tma), COUNT(*), NOW()
            FROM telemetri_awlr
            {$where}
            GROUP BY pos_id, DATE_FORMAT(waktu, '%Y-%m-%d %H:00:00')
            ON DUPLICATE KEY UPDATE
                tma_rata = VALUES(tma_rata),
                tma_min = VALUES(tma_min),
                tma_max = VALUES(tma_max),
                jumlah_data = VALUES(jumlah_data),
                diperbarui_at = VALUES(diperbarui_at)
        ", $bindings);
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private function filter(?CarbonInterface $dari, ?CarbonInterface $sampai, ?int $posId): array
    {
        $kondisi = [];
        $bindings = [];

        if ($dari) {
            $kondisi[] = 'waktu >= ?';
            $bindings[] = $dari->copy()->startOfHour()->toDateTimeString();
        }
        if ($sampai) {
            $kondisi[] = 'waktu < ?';
            $bindings[] = $sampai->copy()->startOfHour()->addHour()->toDateTimeString();
        }
        if ($posId) {
            $kondisi[] = 'pos_id = ?';
            $bindings[] = $posId;
        }

        return [$kondisi ? 'WHERE '.implode(' AND ', $kondisi) : '', $bindings];
    }
}
