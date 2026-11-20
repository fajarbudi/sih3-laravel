<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('halaman utama adalah beranda portal publik', function () {
    $this->get('/')->assertOk()->assertSee('Peta pos pemantauan');
});
