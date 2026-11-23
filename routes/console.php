<?php

use App\Console\Commands\FetchBuletinBmkg;
use App\Console\Commands\FetchTelemetri;
use Illuminate\Support\Facades\Schedule;

/*
| Di server cukup satu baris cron:
|   * * * * * php /path/ke/sih3-laravel/artisan schedule:run >> /dev/null 2>&1
*/

// Telemetri: sumber => tiap berapa menit (mengikuti cron aplikasi lama).
$jadwal = [
    'stesy' => 2,
    'pcb-jogja' => 2,
    'sermo' => 5,
    'higertech' => 5,
    'tatonas' => 5,
    'pjt' => 10,
    'beacon' => 10,
];
// runInBackground: tiap sumber berjalan sendiri, jadi vendor yang lambat/timeout tidak menunda sumber lain
// (tanpa ini schedule:run menjalankannya satu per satu). Kunci withoutOverlapping kedaluwarsa setelah 30 menit,
// agar proses yang mati mendadak tidak menghentikan sumber itu selama 24 jam (bawaan Laravel).
foreach ($jadwal as $sumber => $menit) {
    Schedule::command(FetchTelemetri::class, [$sumber])->cron("*/{$menit} * * * *")->withoutOverlapping(30)->runInBackground();
}

// Buletin otomatis BMKG (draf), tiap tengah malam WIB.
Schedule::command(FetchBuletinBmkg::class)->dailyAt('00:15')->timezone(config('services.telemetri.zona_waktu'));
