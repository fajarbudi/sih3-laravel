<?php

namespace App\Enums;

enum StatusPermintaan: string
{
    case Pengajuan = 'pengajuan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';
    case Ditutup = 'ditutup';
}
