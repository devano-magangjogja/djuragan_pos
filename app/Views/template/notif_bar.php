<?php
// Panelnya melayang di kanan atas, jadi tautan "lihat semua" ikut role yang
// sedang login: admin dan CS punya halaman daftarnya masing-masing.
helper('fungsi');
$link_notif_lengkap = site_url(base_user() . '/notifikasi');
?>
<!-- Lonceng khusus desktop: melayang di pojok kanan atas, bukan bar penuh.
     Di layar kecil loncengnya sudah ada di topbar. -->
<a class="notif-melayang" data-notif-toggle="1" href="#notifikasi" role="button" aria-label="Notifikasi" title="Notifikasi">
    <i class="fal fa-bell"></i>
    <span class="notif-melayang__angka counter"></span>
</a>

<div class="notif-panel" id="notifikasi" role="dialog" aria-label="Notifikasi" aria-hidden="true">
    <div class="notif-panel__kepala">
        <span class="fw-semibold">Notifikasi</span>
        <button type="button" class="btn btn-sm btn-link p-0 small fw-semibold btnSettingNotif tandaiSemuaTerbaca">
            Tandai semua dibaca
        </button>
    </div>

    <div class="list-group list-group-flush notif-panel__isi" id="notifDisini"></div>

    <a class="notif-panel__buntut" href="<?= $link_notif_lengkap ?>">
        Lihat semua notifikasi <i class="fal fa-chevron-right"></i>
    </a>
</div>
