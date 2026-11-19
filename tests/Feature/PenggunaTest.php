<?php

use App\Models\Pengguna;
use App\Models\Pos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    DB::table('walidata')->insert([['id' => 1, 'nama' => 'BBWS SERAYU OPAK'], ['id' => 11, 'nama' => 'PERUM JASA TIRTA I']]);
    Pos::forceCreate(['id' => 60, 'walidata_id' => 11, 'nama' => 'Pos PJT', 'tipe' => 'hidrologi', 'jenis' => 'awlr', 'metode' => 'manual']);
});

it('khusus superadmin; daftar berkolom seperti aplikasi lama', function () {
    akun('petugas_pos', ['pos_id' => 60, 'walidata_id' => 11, 'nama' => 'Budi Petugas', 'username' => 'budi', 'telepon' => '0812']);

    $this->actingAs(akun('admin_walidata', ['walidata_id' => 1]))->get('/admin/pengguna')->assertForbidden();
    $this->actingAs(akun('superadmin', ['username' => 'su']))->get('/admin/pengguna')->assertOk()
        ->assertSeeInOrder(['Nama', 'Nama Login', 'Walidata', 'POS', 'Email', 'Telpon', 'Level', 'Status'])
        ->assertSeeInOrder(['Budi Petugas', 'budi', 'PERUM JASA TIRTA I', 'Pos PJT', '0812', 'Petugas Pos', 'Aktif']);
    $this->get('/admin/pengguna?peran=superadmin')->assertDontSee('Budi Petugas');
    $this->get('/admin')->assertSee(route('admin.pengguna'));
});

it('menambah petugas: walidata mengikuti pos, sandi di-hash, bisa masuk', function () {
    $this->actingAs(akun('superadmin'));

    $this->post('/admin/pengguna', ['nama' => 'Siti', 'username' => 'siti', 'password' => 'rahasia123', 'peran' => 'petugas_pos', 'pos_id' => 60, 'walidata_id' => 1, 'aktif' => '1'])
        ->assertRedirect('/admin/pengguna');
    $siti = Pengguna::where('username', 'siti')->sole();
    expect($siti->walidata_id)->toBe(11)->and(Hash::check('rahasia123', $siti->password))->toBeTrue()->and($siti->aktif)->toBeTrue();

    auth()->logout();
    $this->post('/login', ['username' => 'siti', 'password' => 'rahasia123'])->assertRedirect(route('admin.dashboard'));
});

it('validasi: login unik, sandi minimal 8, walidata/pos wajib sesuai level', function () {
    $this->actingAs(akun('superadmin'));

    $this->post('/admin/pengguna', ['nama' => 'X', 'username' => 'superadmin', 'password' => 'pendek', 'peran' => 'admin_walidata'])
        ->assertSessionHasErrors(['username', 'password', 'walidata_id']);
    $this->post('/admin/pengguna', ['nama' => 'X', 'username' => 'x1', 'password' => 'rahasia123', 'peran' => 'petugas_pos'])->assertSessionHasErrors('pos_id');
    $this->post('/admin/pengguna', ['nama' => 'X', 'username' => 'nama spasi', 'password' => 'rahasia123', 'peran' => 'superadmin'])->assertSessionHasErrors('username');
});

it('mengubah tanpa sandi tidak mengganti sandi; menonaktifkan; tidak bisa menghapus/menonaktifkan diri sendiri', function () {
    $admin = akun('superadmin');
    $lain = akun('admin_walidata', ['walidata_id' => 1, 'username' => 'adm', 'password_md5' => md5('lama')]);
    $hashLama = $lain->password;
    $this->actingAs($admin);

    $this->put("/admin/pengguna/{$lain->id}", ['nama' => 'Admin BBWS', 'username' => 'adm', 'password' => '', 'peran' => 'admin_walidata', 'walidata_id' => 1])->assertRedirect();
    expect($lain->fresh())->nama->toBe('Admin BBWS')->password->toBe($hashLama)->aktif->toBeFalse()->password_md5->not->toBeNull();

    $this->put("/admin/pengguna/{$lain->id}", ['nama' => 'Admin BBWS', 'username' => 'adm', 'password' => 'baru12345', 'peran' => 'admin_walidata', 'walidata_id' => 1, 'aktif' => '1'])->assertRedirect();
    expect($lain->fresh()->password_md5)->toBeNull();

    $this->put("/admin/pengguna/{$admin->id}", ['nama' => 'Saya', 'username' => 'superadmin', 'peran' => 'superadmin'])->assertRedirect();
    expect($admin->fresh()->aktif)->toBeTrue();
    $this->delete("/admin/pengguna/{$admin->id}")->assertStatus(422);
    $this->delete("/admin/pengguna/{$lain->id}")->assertRedirect();
    expect(Pengguna::count())->toBe(1);
});

it('superadmin tidak bisa mengubah level akunnya sendiri', function () {
    $admin = akun('superadmin');
    $this->actingAs($admin);

    $this->get("/admin/pengguna/{$admin->id}/ubah")->assertOk()
        ->assertSee('Level akun Anda sendiri tidak bisa diubah')
        ->assertSee('<input type="hidden" name="peran" value="superadmin">', false);

    $this->put("/admin/pengguna/{$admin->id}", ['nama' => 'Saya', 'username' => 'superadmin', 'peran' => 'admin_walidata', 'walidata_id' => 1])
        ->assertSessionHasErrors('peran');
    expect($admin->fresh()->peran->value)->toBe('superadmin');

    // Mengubah data lain di akun sendiri tetap bisa.
    $this->put("/admin/pengguna/{$admin->id}", ['nama' => 'Nama Baru', 'username' => 'superadmin', 'peran' => 'superadmin'])->assertRedirect('/admin/pengguna');
    expect($admin->fresh()->nama)->toBe('Nama Baru');
});
