<?php
// Tombol Live Chat ikut mengambang di pojok kanan bawah, ditumpuk di atas tombol
// Kirim Masukan, jadi dia tidak lagi ikut baris judul halaman CRM.
?>
<div class="fixed-bottom wa-melayang">
    <div class="container-fluid d-flex justify-content-end px-2">
        <a href="<?= site_url('admin/crm/chat') ?>" class="btn btn-success btn-sm" title="Live Chat WA" aria-label="Live Chat WA">
            <i class="fab fa-whatsapp fs-5"></i>
        </a>
    </div>
</div>
