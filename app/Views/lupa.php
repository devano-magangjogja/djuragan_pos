<?= $this->extend('template/auth') ?>

<?= $this->section('authTitle') ?>
Atur ulang sandi.
<?= $this->endSection() ?>

<?= $this->section('authHeading') ?>
Lupa password
<?= $this->endSection() ?>

<?= $this->section('authSub') ?>
Aku bisa bantu mengubah kata sandimu lewat email.
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

    <?= form_label('Email atau Nama pengguna', 'username', ['class' => 'form-label']) ?>
    <?= form_input([
        'class'       => $class_username,
        'id'          => 'username',
        'name'        => 'username',
        'pattern'     => '^(?:\w+|\w+([+\.-]?\w+)*@\w+([\.-]?\w+)*(\.[a-zA-z]{2,4})+)',
        'placeholder' => 'email / username',
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
<?= form_button([
    'class'    => 'btn auth-submit',
    'content'  => 'Atur ulang',
    'tabindex' => '2',
    'type'     => 'submit',
]) ?>
<?= form_close() ?>
<?= $this->endSection() ?>

<?= $this->section('authHint') ?>
Sudah ingat sandinya? <?= anchor('auth', 'login disini', ['title' => 'Masuk sekarang']) ?>
<?= $this->endSection() ?>
