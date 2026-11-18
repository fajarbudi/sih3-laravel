<?php

namespace App\Console\Commands;

use App\Services\Konten\FetchDataBuletinBmkg;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('konten:buletin-bmkg')]
#[Description('Ambil buletin otomatis dari Stasiun Klimatologi BMKG Yogyakarta & Jawa Tengah (disimpan sebagai draf)')]
class FetchBuletinBmkg extends Command
{
    public function handle(FetchDataBuletinBmkg $fetchData): int
    {
        $gagal = 0;
        foreach (['Staklim Yogya' => 'fetchYogya', 'Staklim Jateng' => 'fetchJateng'] as $nama => $metode) {
            try {
                $this->info("{$nama}: {$fetchData->$metode()} buletin baru");
            } catch (Throwable $e) {
                $gagal++;
                $this->error("{$nama}: {$e->getMessage()}");
            }
        }

        return $gagal === 2 ? self::FAILURE : self::SUCCESS;
    }
}
