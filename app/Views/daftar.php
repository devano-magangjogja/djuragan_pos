<?= $this->extend('template/auth') ?>

<?= $this->section('authTitle') ?>
Daftar baru.
<?= $this->endSection() ?>

<?= $this->section('authHeading') ?>
Daftar
<?= $this->endSection() ?>

<?= $this->section('authSub') ?>
Lengkapi kolom isian lalu klik Daftar.
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
</div>
<div class="auth-field">
    <?php
    $class_nama = 'form-control';
    if ($validation->hasError('nama')) {
        $class_nama .= ' is-invalid';
    }
    ?>

    <?= form_label('Nama', 'nama', ['class' => 'form-label']) ?>
    <?= form_input([
        'class'       => $class_nama,
        'id'          => 'nama',
        'name'        => 'nama',
        'placeholder' => 'nama pengguna',
        'required'    => true,
        'tabindex'    => '3',
        'value'       => set_value('nama'),
    ]) ?>

    <?php if ($validation->hasError('nama')) { ?>
        <div class="invalid-feedback">
            <?= $validation->getError('nama') ?>
        </div>
    <?php } ?>
</div>
<div class="auth-field">
    <?php
    $class_email = 'form-control';
    if ($validation->hasError('email')) {
        $class_email .= ' is-invalid';
    }
    ?>

    <?= form_label('Email', 'email', ['class' => 'form-label']) ?>
    <?= form_input([
        'class'       => $class_email,
        'id'          => 'email',
        'name'        => 'email',
        'placeholder' => 'email',
        'required'    => true,
        'tabindex'    => '4',
        'type'        => 'email',
        'value'       => set_value('email'),
    ]) ?>

    <?php if ($validation->hasError('email')) { ?>
        <div class="invalid-feedback">
            <?= $validation->getError('email') ?>
        </div>
    <?php } ?>
</div>
<?= form_button([
    'class'    => 'btn auth-submit',
    'content'  => 'Daftar',
    'tabindex' => '5',
    'type'     => 'submit',
]) ?>
<?= form_close() ?>
<?= $this->endSection() ?>

<?= $this->section('authHint') ?>
Sudah punya akun? <?= anchor('auth', 'masuk disini', ['title' => 'Masuk sekarang']) ?>
<?= $this->endSection() ?>
