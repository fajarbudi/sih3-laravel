<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPermintaan;
use App\Http\Controllers\Controller;
use App\Models\PermintaanDataPos;
use App\Models\Pos;
use App\Services\KondisiTerkini;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard kondisi lapangan: hujan, muka air, dan cuaca untuk pos yang boleh dilihat pengguna.
 * Perhitungannya di App\Services\KondisiTerkini (dipakai bersama beranda portal publik).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, KondisiTerkini $kondisi): View
    {
        $posId = Pos::terlihatOleh($request->user())->select('id');
        $mukaAir = $kondisi->mukaAir($posId);

        return view('admin.dashboard', [
            'sekarang' => now(config('services.telemetri.zona_waktu')),
            'ambangPerubahan' => KondisiTerkini::AMBANG_PERUBAHAN_M,
            'jumlahMenunggu' => PermintaanDataPos::whereIn('pos_id', $posId)->where('status', StatusPermintaan::Pengajuan)->count(),
            'hujanPerJam' => $kondisi->hujanPerJam($posId),
            'hujanPerPos' => $kondisi->hujanPerPos($posId),
            'mukaAir' => $mukaAir,
            'posNaik' => $mukaAir->filter(fn ($m) => $m['perubahan'] !== null && $m['perubahan'] >= KondisiTerkini::AMBANG_PERUBAHAN_M)->count(),
            'cuaca' => $kondisi->cuaca($posId),
        ]);
    }
}
