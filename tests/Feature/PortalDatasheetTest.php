<?php

use App\Models\ArsipPos;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([
        ['id' => 1, 'nama' => 'BBWS SERAYU OPAK', 'urutan' => 1, 'website' => 'https://bbws.example.go.id', 'url_ppid' => 'https://ppid.example.go.id'],
        ['id' => 3, 'nama' => 'DINAS PUP ESDM DIY', 'urutan' => 2, 'website' => null, 'url_ppid' => null],
    ]);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'AWLR Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual', 'das' => 'Progo', 'desa' => 'Sidoarum']);
    Pos::forceCreate(['id' => 11, 'walidata_id' => 3, 'nama' => 'KA Kali Code', 'tipe' => 'hidrologi', 'jenis' => 'kualitas_air', 'metode' => 'manual', 'das' => 'Opak']);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 40, 'walidata_id' => 1, 'nama' => 'Pos Nonaktif', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual', 'aktif' => false]);
});

it('menampilkan pos aktif satu bidang, urut walidata, tanpa login', function () {
    $this->get('/datasheet/hidrologi')->assertOk()
        ->assertSee('Hidrologi adalah ilmu yang mempelajari tentang air')
        ->assertSeeInOrder(['BBWS SERAYU OPAK', 'AWLR Glapan', 'Sidoarum', 'DINAS PUP ESDM DIY', 'KA Kali Code'])
        ->assertDontSee('ARR Sermo')
        ->assertDontSee('Pos Nonaktif');
});

it('menyaring jenis, walidata, dan DAS', function () {
    $this->get('/datasheet/hidrologi?jenis=kualitas_air')->assertSee('KA Kali Code')->assertDontSee('AWLR Glapan');
    $this->get('/datasheet/hidrologi?walidata=1')->assertSee('AWLR Glapan')->assertDontSee('KA Kali Code');
    $this->get('/datasheet/hidrologi?das=Opak')->assertSee('KA Kali Code')->assertDontSee('AWLR Glapan');
});

it('AGWLR tampil sebagai jenis sendiri, terpisah dari MAT', function () {
    Pos::forceCreate(['id' => 30, 'walidata_id' => 1, 'nama' => 'Sumur Wates', 'tipe' => 'hidrogeologi', 'jenis' => 'mat', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 31, 'walidata_id' => 1, 'nama' => 'AGWLR Bantul', 'tipe' => 'hidrogeologi', 'jenis' => 'agwlr', 'metode' => 'telemetri']);

    $this->get('/datasheet/hidrogeologi')->assertSee('jenis=agwlr', false)->assertSee('jenis=mat', false);
    $this->get('/datasheet/hidrogeologi?jenis=agwlr')->assertSee('AGWLR Bantul')->assertDontSee('Sumur Wates');
});

it('bidang tidak dikenal 404', function () {
    $this->get('/datasheet/embung')->assertNotFound();
});

it('tab arsip menampilkan tahun arsip dan tautan permintaan data walidata', function () {
    ArsipPos::create(['pos_id' => 10, 'tahun' => 2023, 'path' => 'a.pdf']);
    ArsipPos::create(['pos_id' => 10, 'tahun' => 2024, 'path' => 'b.pdf']);

    $this->get('/pos/10/arsip')->assertOk()
        ->assertSeeInOrder(['2024', '2023'])
        ->assertSee('https://ppid.example.go.id');
    $this->get('/pos/11/arsip')->assertOk()->assertSee('Belum ada arsip data');
    $this->get('/pos/40/arsip')->assertNotFound();
});

it('menu Informasi H3 ada di header portal', function () {
    $this->get('/')->assertSee(route('portal.datasheet', 'hidrogeologi'));
});
