<?php

use App\Models\BatasSiaga;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK']]);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 12, 'walidata_id' => 1, 'nama' => 'Bendung Ancol', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 20, 'walidata_id' => 1, 'nama' => 'ARR Sermo', 'tipe' => 'hidrometeorologi', 'jenis' => 'arr', 'metode' => 'manual']);
});

it('grid semua pos AWLR dengan Batas 1-6, khusus superadmin', function () {
    BatasSiaga::create(['pos_id' => 10, 'level' => 2, 'nilai' => 1.5]);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/ambang-batas')->assertForbidden();
    $this->actingAs(akun('superadmin', ['username' => 'su']))->get('/admin/ambang-batas')->assertOk()
        ->assertSeeInOrder(['POS', 'Batas 1', 'Batas 6', 'Bendung Ancol', 'Bendung Glapan'])
        ->assertSee('name="batas[10][2]"', false)
        ->assertSee('value="1.5"', false)
        ->assertDontSee('ARR Sermo');
    $this->get('/admin')->assertSee(route('admin.batas-siaga'));
});

it('menyimpan hanya pos yang dikirim; batas jenis lain tidak ikut terhapus', function () {
    BatasSiaga::create(['pos_id' => 10, 'level' => 1, 'nilai' => 1]);
    BatasSiaga::create(['pos_id' => 20, 'level' => 3, 'nilai' => 50]); // ARR, tidak ada di grid AWLR
    $this->actingAs(akun('superadmin'));

    $this->from('/admin/ambang-batas')->put('/admin/ambang-batas', ['batas' => [
        10 => [1 => '', 2 => '2.25', 3 => '3.5'],
        12 => [1 => '', 2 => '', 3 => '', 4 => '', 5 => '', 6 => ''],
    ]])->assertRedirect('/admin/ambang-batas');

    expect(BatasSiaga::where('pos_id', 10)->orderBy('level')->pluck('nilai', 'level')->all())->toEqual([2 => 2.25, 3 => 3.5])
        ->and(BatasSiaga::where('pos_id', 12)->count())->toBe(0)
        ->and(BatasSiaga::where('pos_id', 20)->value('nilai'))->toBe(50.0);

    $this->put('/admin/ambang-batas', ['batas' => [10 => [1 => 'tinggi']]])->assertSessionHasErrors('batas.10.1');
});
