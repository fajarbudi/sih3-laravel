<?php

test('halaman utama mengarah ke panel admin', function () {
    $this->get('/')->assertRedirect('/admin');
});
