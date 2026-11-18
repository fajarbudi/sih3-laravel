<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'telemetri' => [
        // Kolom `waktu` di tabel telemetri_* menyimpan jam dinding WIB, sama seperti data hasil impor
        // dari aplikasi lama. Vendor yang mengirim UTC dikonversi ke zona ini sebelum disimpan.
        'zona_waktu' => env('TELEMETRI_ZONA_WAKTU', 'Asia/Jakarta'),
    ],

    'sermo' => [
        'url' => env('SERMO_API_URL', 'http://202.180.30.82/public/API/v1'),
        'token' => env('SERMO_API_TOKEN'),
        // Pengelola pos yang dibuat otomatis dari perangkat Sermo (1 = BBWS Serayu Opak).
        'walidata_id' => (int) env('SERMO_WALIDATA_ID', 1),
    ],

    'pjt' => [
        'url' => env('PJT_API_URL', 'https://datatirta.jasatirta1.co.id/api'),
        'email' => env('PJT_API_EMAIL'),
        'password' => env('PJT_API_PASSWORD'),
        // Pengelola pos baru yang muncul di API (11 = Perum Jasa Tirta I, sama dengan Apipjt.php lama).
        'walidata_id' => (int) env('PJT_WALIDATA_ID', 11),
    ],

    'higertech' => [
        'url' => env('HIGERTECH_API_URL', 'https://api.higertech.com/v2'),
    ],

    'tatonas' => [
        'url' => env('TATONAS_API_URL', 'https://tatonas.co.id/api/v2'),
        // Kode akses akun BBWS Serayu Opak (uc) dan kode proyek (pc), dikirim sebagai query string.
        'uc' => env('TATONAS_UC'),
        'pc' => env('TATONAS_PC'),
    ],

    'stesy' => [
        'url' => env('STESY_API_URL', 'https://mini-stesy.monitoring4system.com/api'),
        // Nilai header Authorization apa adanya (bukan "Bearer ..."), sama dengan helper posapistesy() lama.
        'authorization' => env('STESY_API_AUTHORIZATION'),
    ],

    'pcb_jogja' => [
        'url' => env('PCB_JOGJA_API_URL', 'https://log.pcbjogja.com/api/web/custom/bbws_serayuopak.php'),
    ],

    'beacon' => [
        'url' => env('BEACON_API_URL', 'https://bbwsso.monitoring4system.com/integrasi_api'),
        // HTTP Basic Auth, akun web berlevel admin (akun user/tamu ditolak 401).
        'username' => env('BEACON_API_USERNAME'),
        'password' => env('BEACON_API_PASSWORD'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
