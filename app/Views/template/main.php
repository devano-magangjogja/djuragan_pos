<!doctype html>
<html lang="id">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="<?= vite('resources/scss/style.scss') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/tailwind.css') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/css/tailwind.css') ?>">

    <?= $this->renderSection('css') ?>

    <title><?= esc($title) ?></title>
</head>

<body>
    <?= $this->renderSection('content') ?>

    <?= $this->renderSection('modal') ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"></script>

    <?= $this->renderSection('js') ?>
</body>

</html>
