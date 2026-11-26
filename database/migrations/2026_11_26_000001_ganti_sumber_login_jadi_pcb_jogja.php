<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Sumber 'login' (nama lama dari Apilogin.php) diganti 'pcb-jogja', sama dengan nama perintah telemetri:fetch. */
return new class extends Migration
{
    private const TABEL = ['perangkat_telemetri', 'telemetri_arr', 'telemetri_awlr', 'telemetri_klimatologi', 'log_telemetri', 'parameter_instrumen'];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            DB::table($tabel)->where('sumber', 'login')->update(['sumber' => 'pcb-jogja']);
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            DB::table($tabel)->where('sumber', 'pcb-jogja')->update(['sumber' => 'login']);
        }
    }
};
