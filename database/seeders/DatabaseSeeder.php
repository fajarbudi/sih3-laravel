<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Data awal yang dibutuhkan aplikasi, meski tanpa impor dari db_sih3.
     */
    public function run(): void
    {
        $this->call(ParameterKualitasAirSeeder::class);
    }
}
