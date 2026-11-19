<?php

use App\Models\Pos;
use App\Models\RumusDebit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK']]);
    Pos::forceCreate(['id' => 10, 'walidata_id' => 1, 'nama' => 'Bendung Glapan', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
    Pos::forceCreate(['id' => 12, 'walidata_id' => 1, 'nama' => 'Bendung Ancol', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
});

it('grid R_1-R_6 menampilkan koefisien tersimpan, khusus superadmin', function () {
    RumusDebit::create(['pos_id' => 10, 'koefisien' => [1, 2.5, 3, 4, 5, 6]]);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/rumus-debit')->assertForbidden();
    $this->actingAs(akun('superadmin', ['username' => 'su']))->get('/admin/rumus-debit')->assertOk()
        ->assertSeeInOrder(['Rumus Debit', 'R_1', 'R_6', 'Bendung Ancol', 'Bendung Glapan'])
        ->assertSee('name="batas[10][2]"', false)
        ->assertSee('value="2.5"', false);
    $this->get('/admin')->assertSee(route('admin.rumus-debit'));
});

it('menyimpan koefisien sebagai array dan menghapus rumus bila semua kosong', function () {
    RumusDebit::create(['pos_id' => 12, 'koefisien' => [9, 9, 9, 9, 9, 9]]);
    $this->actingAs(akun('superadmin'));

    $this->from('/admin/rumus-debit')->put('/admin/rumus-debit', ['batas' => [
        10 => [1 => '0.5', 2 => '', 3 => '1.75', 4 => '', 5 => '', 6 => ''],
        12 => [1 => '', 2 => '', 3 => '', 4 => '', 5 => '', 6 => ''],
    ]])->assertRedirect('/admin/rumus-debit');

    expect(RumusDebit::where('pos_id', 10)->sole()->koefisien)->toBe([0.5, null, 1.75, null, null, null])
        ->and(RumusDebit::where('pos_id', 12)->exists())->toBeFalse();
});

it('simpan mengubah rumus yang ditampilkan (berlaku_mulai terbaru) bila pos punya beberapa rumus', function () {
    $lama = RumusDebit::create(['pos_id' => 10, 'koefisien' => [1, 1, 1, 1, 1, 1], 'berlaku_mulai' => '2020-01-01']);
    $baru = RumusDebit::create(['pos_id' => 10, 'koefisien' => [2, 2, 2, 2, 2, 2], 'berlaku_mulai' => '2025-01-01']);
    $this->actingAs(akun('superadmin'));

    $this->get('/admin/rumus-debit')->assertSee('value="2"', false);
    $this->put('/admin/rumus-debit', ['batas' => [10 => [1 => '7', 2 => '', 3 => '', 4 => '', 5 => '', 6 => '']]])->assertRedirect();

    expect($baru->fresh()->koefisien[0])->toEqual(7)->and($lama->fresh()->koefisien[0])->toEqual(1); // JSON: 7.0 kembali sebagai 7
});
