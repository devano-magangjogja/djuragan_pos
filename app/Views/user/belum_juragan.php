<?php
/**
 * Halaman status 404 khusus akun CS yang belum ditautkan ke juragan mana pun.
 *
 * Ditampilkan User\Invoices::lihat() lewat response()->setBody(view(...)) supaya
 * tamu tetap menerima status 404, sementara isi halaman menawarkan jalan keluar:
 * satu tombol yang mengirim permintaan (notifikasi prioritas) ke Superadmin agar
 * akun ini segera ditautkan. Tidak memakai template/main karena halaman ini
 * sengaja berdiri sendiri seperti error_page bawaan.
 *
 * @var string $title Judul halaman.
 */
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>" />
    <title><?= esc($title ?? 'Akun Belum Ditautkan') ?></title>
    <style>
        body {
            background: #f4f6f9;
        }

        .taut-wrap {
            min-height: 100vh;
            padding: 1.5rem;
        }

        .taut-card {
            width: 100%;
            max-width: 540px;
            margin: 0 auto;
            border: 1px solid #eaecf0;
            border-radius: 16px;
            box-shadow: 0 8px 28px rgba(16, 24, 40, .08);
        }

        .taut-ikon {
            width: 60px;
            height: 60px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            background: #fef3f2;
            color: #d92d20;
            font-size: 1.5rem;
        }
    </style>
</head>

<body>
    <div class="taut-wrap d-flex align-items-center justify-content-center">
        <div class="taut-card bg-white p-4 p-md-5 text-center">
            <span class="taut-ikon mb-3"><i class="fal fa-user-plus"></i></span>
            <h1 class="h4 mb-2">Akun kamu belum punya juragan</h1>
            <p class="text-muted mb-4">
                Belum ada orderan yang bisa dibuka karena akun ini belum ditautkan ke
                sebuah juragan.
            </p>

            <?php $sukses = session()->getFlashdata('sukses'); ?>
            <?php $gagal = session()->getFlashdata('gagal'); ?>
            <?php if ($sukses) : ?>
                <div class="alert alert-success text-start taut-flash" role="alert"><?= $sukses ?></div>
            <?php endif; ?>
            <?php if ($gagal) : ?>
                <div class="alert alert-warning text-start taut-flash" role="alert"><?= $gagal ?></div>
            <?php endif; ?>

            <?= form_open(site_url('user/minta_juragan'), ['id' => 'mintaForm', 'class' => 'd-inline']) ?>
            <button type="submit" id="mintaBtn" class="btn btn-primary btn-lg">
                <i class="fal fa-user-plus me-1"></i> Minta ke Superadmin
            </button>
            <?= form_close() ?>

            <div class="mt-3">
                <?= anchor('auth/keluar', 'Keluar', ['class' => 'btn btn-link btn-sm text-muted']) ?>
            </div>
        </div>
    </div>

    <script>
        (function () {
            'use strict';

            var COOLDOWN = 50; // detik; jeda antar-permintaan supaya tidak spam
            var KEY = 'minta_juragan_cooldown';

            // pesan flash hilang sendiri setelah 5 detik
            document.querySelectorAll('.taut-flash').forEach(function (el) {
                setTimeout(function () {
                    el.style.transition = 'opacity .4s ease';
                    el.style.opacity = '0';
                    setTimeout(function () { el.remove(); }, 400);
                }, 5000);
            });

            var form = document.getElementById('mintaForm');
            var btn = document.getElementById('mintaBtn');
            if (!form || !btn) {
                return;
            }

            var labelAsli = btn.innerHTML;
            var timer = null;

            function sisaDetik() {
                var terakhir = parseInt(localStorage.getItem(KEY) || '0', 10);
                if (!terakhir) {
                    return 0;
                }
                return Math.max(0, Math.ceil(COOLDOWN - (Date.now() - terakhir) / 1000));
            }

            function render() {
                var sisa = sisaDetik();
                if (sisa > 0) {
                    btn.disabled = true;
                    btn.innerHTML = 'Tunggu ' + sisa + ' detik lagi...';
                    if (!timer) {
                        timer = setInterval(render, 1000);
                    }
                } else {
                    btn.disabled = false;
                    btn.innerHTML = labelAsli;
                    if (timer) {
                        clearInterval(timer);
                        timer = null;
                    }
                }
            }

            // catat waktu klik; form tetap submit (halaman reload) lalu render()
            // melanjutkan hitungan mundur dari timestamp yang tersimpan
            form.addEventListener('submit', function () {
                localStorage.setItem(KEY, String(Date.now()));
            });

            render();
        })();
    </script>
</body>

</html>
