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
    <script>
        if (localStorage.getItem('djuragan_sidebar_collapsed') === '1') {
            document.documentElement.classList.add('sidebar-collapsed');
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('sidebar-collapsed');
            });
        }
    </script>
</head>

<body class="<?= ! empty(session()->get('id')) ? 'has-app-sidebar' : '' ?>">
    <?= $this->renderSection('content') ?>

    <?= $this->renderSection('modal') ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <script>
        // Semua jalur tulis app ini lewat $.ajax/$.post, jadi token CSRF dipasang
        // sekali di header agar tiap permintaan ikut terlindungi.
        $.ajaxSetup({
            headers: { '<?= csrf_header() ?>': '<?= esc(csrf_hash()) ?>' }
        });

        // Cookie CSRF berumur 2 jam; kalau halaman masih terbuka setelah itu,
        // permintaan ditolak 403. Muat ulang satu kali untuk mengambil token baru.
        $(document).ajaxError(function (_event, jqXHR) {
            if (jqXHR.status !== 403) {
                return;
            }

            var terakhir = Number(sessionStorage.getItem('csrf_muat_ulang') || 0);
            if (Date.now() - terakhir < 10000) {
                return;
            }

            sessionStorage.setItem('csrf_muat_ulang', String(Date.now()));
            window.location.reload();
        });
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"></script>

    <?= $this->renderSection('js') ?>
</body>

</html>
