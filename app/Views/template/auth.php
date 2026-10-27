<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>

<div class="auth-page">
    <div class="auth-card">
        <aside class="auth-hero">
            <h1 class="auth-hero-title"><?= $this->renderSection('authTitle') ?></h1>
            <div class="auth-hero-brand">
                <?= $this->include('template/logo') ?>
            </div>
        </aside>

        <section class="auth-panel">
            <h2 class="auth-panel-title"><?= $this->renderSection('authHeading') ?></h2>
            <p class="auth-panel-sub"><?= $this->renderSection('authSub') ?></p>

            <?php $status = ($_SESSION['status'] ?? ''); ?>
            <?php if ($status !== '') : ?>
                <div class="auth-status"><?= $status ?></div>
            <?php endif; ?>

            <?= $this->renderSection('authBody') ?>

            <p class="auth-hint"><?= $this->renderSection('authHint') ?></p>
        </section>
    </div>
</div>

<?= $this->endSection() ?>
