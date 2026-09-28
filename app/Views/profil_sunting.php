<?php
$session = \Config\Services::session();
?>
<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?php if ($session->get('level') === 'cs') : ?>
    <?= $this->include('user/navbar') ?>
<?php else : ?>
    <?= $this->include('admin/navbar') ?>
<?php endif; ?>

<div class="container-xxl">
    <h1 class="h3 mt-5">Ubah Profil</h1>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb p-0">
            <li class="breadcrumb-item"><?= anchor('', 'Dasbor') ?></li>
            <li class="breadcrumb-item active" aria-current="page">Profil</li>
        </ol>
    </nav>
</div>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 mb-3">
            <?= ($_SESSION['status'] ?? '') ?>

            <div class="card">
                <div class="card-body">
                    <?= form_open('user/sunting', [], ['id' => $pengguna->id]) ?>

                    <div class="mb-3 row gx-2">
                        <div class="col-6">
                            <?= form_label('Username', 'username', ['class' => 'form-label']) ?>
                            <?= form_input('username', $pengguna->username, ['class' => 'form-control', 'id' => 'username', 'disabled' => '']) ?>
                        </div>
                        <div class="col-6">
                            <?= form_label('Level', 'level', ['class' => 'form-label']) ?>
                            <?= form_input('level', $pengguna->level, ['class' => 'form-control', 'id' => 'level', 'disabled' => '']) ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?php $kelasNama = 'form-control' . ($validation->hasError('nama') ? ' is-invalid' : ''); ?>
                        <?= form_label('Nama Lengkap', 'nama', ['class' => 'form-label']) ?>
                        <?= form_input('nama', set_value('nama', $pengguna->name), ['class' => $kelasNama, 'id' => 'nama', 'required' => '', 'placeholder' => 'nama lengkap']) ?>
                        <?php if ($validation->hasError('nama')) : ?>
                            <div class="invalid-feedback"><?= $validation->getError('nama') ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <?php $kelasEmail = 'form-control' . ($validation->hasError('email') ? ' is-invalid' : ''); ?>
                        <?= form_label('Email', 'email', ['class' => 'form-label']) ?>
                        <?= form_input('email', set_value('email', $pengguna->email), ['class' => $kelasEmail, 'id' => 'email', 'required' => '', 'placeholder' => 'alamat@email'], 'email') ?>
                        <?php if ($validation->hasError('email')) : ?>
                            <div class="invalid-feedback"><?= $validation->getError('email') ?></div>
                        <?php endif; ?>
                    </div>
                    <hr>

                    <p class="text-muted small mb-3">Kosongkan ketiga kolom ini kalau tidak ingin mengganti kata sandi.</p>

                    <div class="mb-3">
                        <?= form_label('Kata Sandi Lama', 'sandi_lama', ['class' => 'form-label']) ?>
                        <?= form_password('sandi_lama', '', ['class' => 'form-control', 'id' => 'sandi_lama', 'placeholder' => 'sandi saat ini', 'autocomplete' => 'current-password']) ?>
                    </div>
                    <div class="mb-3">
                        <?= form_label('Kata Sandi Baru', 'sandi_baru', ['class' => 'form-label']) ?>
                        <?= form_password('sandi_baru', '', ['class' => 'form-control', 'id' => 'sandi_baru', 'placeholder' => 'minimal 6 karakter', 'autocomplete' => 'new-password']) ?>
                    </div>
                    <div class="mb-3">
                        <?= form_label('Ulangi Kata Sandi Baru', 'ulangi_sandi_baru', ['class' => 'form-label']) ?>
                        <?= form_password('ulangi_sandi_baru', '', ['class' => 'form-control', 'id' => 'ulangi_sandi_baru', 'placeholder' => 'ulangi sandi baru', 'autocomplete' => 'new-password']) ?>
                    </div>

                    <hr>
                    <?= form_button([
                        'class'   => 'btn btn-primary',
                        'content' => '<i class="fal fa-save"></i> Simpan',
                        'type'    => 'submit',
                    ]) ?>
                    <?= form_close() ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
