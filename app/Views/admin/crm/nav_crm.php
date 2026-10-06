<?php
$uri = trim(uri_string(), '/');
$tabs = [
    ['admin/crm', 'Ringkasan', 'fa-chart-pie', $uri === 'admin/crm'],
    ['admin/crm/pelanggan', 'Data Pelanggan', 'fa-users', str_starts_with($uri, 'admin/crm/pelanggan')],
    ['admin/crm/followup', 'Follow-up & Reminder', 'fa-tasks', str_starts_with($uri, 'admin/crm/followup')],
    ['admin/crm/chat', 'Live Chat WA', 'fa-comments', str_starts_with($uri, 'admin/crm/chat')],
    ['admin/crm/broadcast', 'Broadcast Promo', 'fa-bullhorn', str_starts_with($uri, 'admin/crm/broadcast')],
    ['admin/crm/duplikat', 'Akun Duplikat', 'fa-clone', str_starts_with($uri, 'admin/crm/duplikat')],
    ['admin/crm/pengiriman', 'Notifikasi Pengiriman', 'fa-shipping-fast', str_starts_with($uri, 'admin/crm/pengiriman')],
    ['admin/crm/log', 'Riwayat Pesan', 'fa-history', str_starts_with($uri, 'admin/crm/log')],
    ['admin/crm/template', 'Template Pesan', 'fa-file-alt', str_starts_with($uri, 'admin/crm/template')],
    ['admin/crm/pengaturan', 'Pengaturan Gateway', 'fa-sliders-h', str_starts_with($uri, 'admin/crm/pengaturan')],
];
?>
<div class="mb-4 border-bottom">
    <ul class="nav nav-pills flex-nowrap overflow-auto pb-2 gap-2">
        <?php foreach ($tabs as [$url, $label, $icon, $active]) : ?>
            <li class="nav-item">
                <a class="nav-link <?= $active ? 'active fw-semibold shadow-sm' : 'text-dark border bg-white' ?>" href="<?= site_url($url) ?>">
                    <i class="fal <?= $icon ?> me-1"></i> <?= $label ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
