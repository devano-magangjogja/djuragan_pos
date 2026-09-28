<?= $this->extend('template/auth') ?>

<?= $this->section('authTitle') ?>
Sandi baru.
<?= $this->endSection() ?>

<?= $this->section('authHeading') ?>
Atur ulang sandi
<?= $this->endSection() ?>

<?= $this->section('authSub') ?>
Tentukan kata sandi baru untuk akunmu.
<?= $this->endSection() ?>

<?= $this->section('authBody') ?>
<?= form_open('auth/simpan-sandi-baru', [], ['id' => $id]) ?>
<div class="auth-field">
    <?php
    $class_sandiBaru = 'form-control';
    if ($validation->hasError('sandi_baru')) {
        $class_sandiBaru .= ' is-invalid';
    }
    ?>

    <?= form_label('Kata sandi baru', 'sandi_baru', ['class' => 'form-label']) ?>
    <?= form_password([
        'class'       => $class_sandiBaru,
        'id'          => 'sandi_baru',
        'name'        => 'sandi_baru',
        'placeholder' => 'sandi baru',
        'required'    => true,
        'tabindex'    => '1',
    ]) ?>
    <?php if ($validation->hasError('sandi_baru')) { ?>
        <div class="invalid-feedback">
            <?= $validation->getError('sandi_baru') ?>
        </div>
    <?php } ?>
</div>
<div class="auth-field">
    <?php
    $class_ulangSandiBaru = 'form-control';
    if ($validation->hasError('ulangi_sandi_baru')) {
        $class_ulangSandiBaru .= ' is-invalid';
    }
    ?>

    <?= form_label('Ulangi kata sandi baru', 'ulangi_sandi_baru', ['class' => 'form-label']) ?>
    <?= form_password([
        'class'       => $class_ulangSandiBaru,
        'id'          => 'ulangi_sandi_baru',
        'name'        => 'ulangi_sandi_baru',
        'placeholder' => 'ulangi sandi baru',
        'required'    => true,
        'tabindex'    => '2',
    ]) ?>
    <?php if ($validation->hasError('ulangi_sandi_baru')) { ?>
        <div class="invalid-feedback">
            <?= $validation->getError('ulangi_sandi_baru') ?>
        </div>
    <?php } ?>
</div>
<?= form_button([
    'class'    => 'btn auth-submit',
    'content'  => 'Atur ulang',
    'tabindex' => '3',
    'type'     => 'submit',
]) ?>
<?= form_close() ?>
<?= $this->endSection() ?>

<?= $this->section('authHint') ?>
Sudah ingat sandi lama? <?= anchor('auth', 'login disini', ['title' => 'Masuk sekarang']) ?>
<?= $this->endSection() ?>
