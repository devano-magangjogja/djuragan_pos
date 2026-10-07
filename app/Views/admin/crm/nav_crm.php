<?php
$uri = trim(uri_string(), '/');
$tabs = [
    ['admin/crm', 'Ringkasan', 'fa-chart-pie', $uri === 'admin/crm'],
    ['admin/crm/pelanggan', 'Data Pelanggan', 'fa-users', str_starts_with($uri, 'admin/crm/pelanggan')],
    ['admin/crm/followup', 'Follow-up', 'fa-tasks', str_starts_with($uri, 'admin/crm/followup')],
    ['admin/crm/chat', 'Live Chat WA', 'fa-comments', str_starts_with($uri, 'admin/crm/chat')],
    ['admin/crm/broadcast', 'Broadcast', 'fa-bullhorn', str_starts_with($uri, 'admin/crm/broadcast')],
    ['admin/crm/duplikat', 'Akun Duplikat', 'fa-clone', str_starts_with($uri, 'admin/crm/duplikat')],
    ['admin/crm/pengiriman', 'Pengiriman', 'fa-shipping-fast', str_starts_with($uri, 'admin/crm/pengiriman')],
    ['admin/crm/log', 'Riwayat Pesan', 'fa-history', str_starts_with($uri, 'admin/crm/log')],
    ['admin/crm/template', 'Template Pesan', 'fa-file-alt', str_starts_with($uri, 'admin/crm/template')],
    ['admin/crm/pengaturan', 'Pengaturan', 'fa-sliders-h', str_starts_with($uri, 'admin/crm/pengaturan')],
];
?>
<style>
    .crm-nav-pills {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
        -webkit-overflow-scrolling: touch;
    }
    .crm-nav-pills::-webkit-scrollbar {
        height: 4px;
    }
    .crm-nav-pills::-webkit-scrollbar-track {
        background: transparent;
    }
    .crm-nav-pills::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .crm-nav-pills::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .crm-nav-pills .nav-link {
        transition: all 0.15s ease-in-out;
    }
    .crm-nav-pills .nav-link:not(.active):hover {
        background-color: #f8fafc !important;
        border-color: #cbd5e1 !important;
        color: #0d6efd !important;
    }
</style>

<div class="mb-4 border-bottom">
    <ul class="nav nav-pills crm-nav-pills flex-nowrap overflow-auto pb-2 gap-2">
        <?php foreach ($tabs as [$url, $label, $icon, $active]) : ?>
            <li class="nav-item flex-shrink-0">
                <a class="nav-link text-nowrap d-inline-flex align-items-center py-2 px-3 small rounded-3 <?= $active ? 'active fw-semibold shadow-sm' : 'text-dark border bg-white' ?>" href="<?= site_url($url) ?>">
                    <i class="fal <?= $icon ?> me-2"></i><span><?= $label ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
