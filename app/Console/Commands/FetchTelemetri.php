<?php

namespace App\Console\Commands;

use App\Models\LogTelemetri;
use App\Services\Telemetri;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;


#[Signature('telemetri:fetch
    {sumber : sermo, pjt, higertech, tatonas, stesy, pcb-jogja, beacon}
    {--dari= : Awal rentang (WIB). Kosong = lanjut dari data terakhir}
    {--sampai= : Akhir rentang (WIB). Kosong = sekarang}')]
#[Description('Ambil data telemetri dari API vendor')]
class FetchTelemetri extends Command
{
    public const FETCH_DATA = [
        'sermo' => Telemetri\FetchDataSermo::class,
        'pjt' => Telemetri\FetchDataPjt::class,
        'higertech' => Telemetri\FetchDataHigertech::class,
        'tatonas' => Telemetri\FetchDataTatonas::class,
        'stesy' => Telemetri\FetchDataStesy::class,
        'pcb-jogja' => Telemetri\FetchDataPcbJogja::class,
        'beacon' => Telemetri\FetchDataBeacon::class,
    ];

    public function handle(): int
    {
        $sumber = $this->argument('sumber');
        if (! isset(self::FETCH_DATA[$sumber])) {
            $this->error('Sumber tidak dikenal. Pilihan: '.implode(', ', array_keys(self::FETCH_DATA)));

            return self::INVALID;
        }

        $zona = config('services.telemetri.zona_waktu');
        $dari = $this->option('dari') ? Carbon::parse($this->option('dari'), $zona) : null;
        $sampai = $this->option('sampai') ? Carbon::parse($this->option('sampai'), $zona) : null;

        $fetchData = app(self::FETCH_DATA[$sumber]);
        try {
            $jumlah = $fetchData->fetch($dari, $sampai);
        } catch (Throwable $e) {
            LogTelemetri::create(['sumber' => $sumber, 'pesan' => Str::limit($e->getMessage(), 250)]);
            $this->error("{$sumber}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("{$sumber}: {$jumlah} baris disimpan, {$fetchData->gagal} gagal");
        if ($fetchData->gagal) {
            $this->warn("Rincian kegagalan ada di tabel log_telemetri (sumber = {$sumber}).");
        }

        return self::SUCCESS;
    }
}
