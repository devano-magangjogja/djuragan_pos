<?php

// Variabel berikut dikirim Auth::index() lewat view() lalu di-extract CI4 saat
// render, sehingga tidak terlihat oleh analis statis. Deklarasi di bawah memberi
// jenis sebenarnya ke Intelephense (P1008 pada $validation).

/**
 * @var \CodeIgniter\Validation\Validation $validation hasil \Config\Services::validation()
 */
?>
<?= $this->extend('template/auth') ?>

<?= $this->section('authTitle') ?>
Seven Inc.  
<?= $this->endSection() ?>

<?= $this->section('authHeading') ?>
Masuk
<?= $this->endSection() ?>

<?= $this->section('authSub') ?>
Hanya akun valid yang bisa mengakses halaman ini.
<?= $this->endSection() ?>

<?= $this->section('authBody') ?>
<?= form_open() ?>
<div class="auth-field">
    <?php
    $class_username = 'form-control';
    if ($validation->hasError('username')) {
        $class_username .= ' is-invalid';
    }
    ?>

    <?= form_label('Pengguna', 'username', ['class' => 'form-label']) ?>
    <?= form_input([
        'class'       => $class_username,
        'id'          => 'username',
        'name'        => 'username',
        'placeholder' => 'username',
        'required'    => true,
        'tabindex'    => '1',
        'value'       => set_value('username'),
    ]) ?>
    <?php if ($validation->hasError('username')) { ?>
        <div class="invalid-feedback">
            <?= $validation->getError('username') ?>
        </div>
    <?php } ?>
</div>
<div class="auth-field">
    <?php
    $class_password = 'form-control';
    if ($validation->hasError('password')) {
        $class_password .= ' is-invalid';
    }
    ?>

    <?= form_label('Kata sandi', 'password', ['class' => 'form-label']) ?>
    <?= form_password([
        'class'       => $class_password,
        'id'          => 'password',
        'name'        => 'password',
        'placeholder' => 'kata sandi',
        'required'    => true,
        'tabindex'    => '2',
    ]) ?>

    <?php if ($validation->hasError('password')) { ?>
        <div class="invalid-feedback">
            <?= $validation->getError('password') ?>
        </div>
    <?php } ?>

    <?php /* "Lupa sandi?" dulu duduk di baris paling bawah berdampingan dengan
              "daftar disini" — jauh dari kolom yang bersangkutan. */ ?>
    <div class="auth-lupa">
        <?= anchor('auth/lupa', 'Lupa sandi?', ['title' => 'Reset kata sandi']) ?>
    </div>
</div>
<?= form_button([
    'class'    => 'btn auth-submit',
    'content'  => 'Masuk',
    'tabindex' => '3',
    'type'     => 'submit',
]) ?>
<?= form_close() ?>
<?= $this->endSection() ?>

<?= $this->section('authHint') ?>
Belum punya akun? <?= anchor('auth/daftar', 'daftar disini', ['title' => 'Daftar sekarang']) ?>
<?= $this->endSection() ?>
