<?= $this->extend('template/main') ?>

<?php
// $templates dikirim Crm::chat() lewat view('admin/crm/chat', $data). CI4
// meng-inject-nya dengan extract() di dalam View::render(), jadi tidak ada
// assignment yang terlihat oleh static analysis. Fallback di sini sekaligus
// menjaga view tetap aman bila dirender dari jalur lain tanpa data itu
// ($activeId di bagian js sudah lebih dulu diberi pola sama: $activeId ?? 0).
$templates ??= [];

// CS boleh membuka, membalas, mendaftarkan lead, serta mengubah/menghapus chat
// (sama seperti superadmin). Yang tetap khusus admin/superadmin: webhook,
// gateway, dan halaman kelola pelanggan (Profil 360).
$level_sesi    = (string) (session()->get('level') ?? '');
$boleh_kelola = in_array($level_sesi, ['admin', 'superadmin'], true);
?>

<?= $this->section('css') ?>
<style>
/* Tinggi ruang chat: dihitung dari jarak ruang atas (bar judul + navbar) plus
   tinggi tombol melayang "Kirim Masukan" (41,6px + margin 16px) + 8px napas,
   supaya dasar komposer tidak tertutup ikon dan halaman tidak ikut bergulir.
   Angka 140px untuk >=992px (navbar mobile tersembunyi, ruang atas 74px). */
.chat-container {
    height: calc(100vh - 140px);
    min-height: 0;
    background: #f0f2f5;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}
.chat-sidebar {
    width: 360px;
    border-right: 1px solid #e2e8f0;
    background: #ffffff;
    display: flex;
    flex-direction: column;
}
.chat-conversation-list {
    flex: 1;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
/* style.css memaksa padding & radius .form-control-sm dengan !important, jadi
   kelas Bootstrap (rounded-pill, ps-4) tidak mempan di sini */
.chat-sidebar .form-control.chat-cari {
    border-radius: 999px !important;
    padding: 0.4rem 1rem 0.4rem 2.2rem !important;
}
.chat-conv-item {
    cursor: pointer;
    transition: background 0.15s ease;
    border-bottom: 1px solid #f1f5f9;
}
.chat-conv-item:hover {
    background: #f8fafc;
}
.chat-conv-item.active {
    background: #e7f5ea;
    border-left: 4px solid #25d366;
}
.chat-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 0;
    height: 100%;
    position: relative;
    background: #efeae2;
    background-image: radial-gradient(#d1d7db 1px, transparent 1px);
    background-size: 20px 20px;
}
.chat-header {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 20px;
    flex-shrink: 0;
}
.chat-body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.chat-bubble {
    max-width: 65%;
    padding: 6px 14px;
    border-radius: 10px;
    font-size: 0.92rem;
    line-height: 1.45;
    position: relative;
    word-wrap: break-word;
    word-break: break-word;
    white-space: pre-wrap;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}
.chat-bubble.keluar {
    align-self: flex-end;
    background: #d9fdd3;
    color: #111b21;
    border-top-right-radius: 2px;
}
.chat-bubble.masuk {
    align-self: flex-start;
    background: #ffffff;
    color: #111b21;
    border-top-left-radius: 2px;
}
.chat-meta {
    font-size: 0.72rem;
    color: #667781;
    text-align: right;
    margin-top: 4px;
}
/* Tombol trigger menu dropdown ala WhatsApp (muncul saat baris percakapan di-hover) */
.chat-conv-trigger {
    width: 26px;
    height: 26px;
    border: none;
    background: transparent;
    color: #8696a0;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    padding: 0;
    margin-left: 4px;
    transition: background 0.15s ease, color 0.15s ease;
    cursor: pointer;
    outline: none;
    box-shadow: none;
}
.chat-conv-trigger::after {
    display: none !important;
}
.chat-conv-trigger:hover,
.chat-conv-trigger:focus,
.chat-conv-trigger[aria-expanded="true"] {
    background: #e9edef;
    color: #111b21;
}
.chat-conv-item:hover .chat-conv-trigger,
.chat-conv-item .chat-item-dropdown.show .chat-conv-trigger,
.chat-conv-item .chat-conv-trigger[aria-expanded="true"] {
    display: inline-flex;
}

/* Dropdown menu ala WhatsApp Web */
.wa-dropdown-menu {
    border-radius: 12px !important;
    border: 1px solid rgba(11, 20, 26, 0.08) !important;
    box-shadow: 0 4px 20px rgba(11, 20, 26, 0.15), 0 2px 6px rgba(11, 20, 26, 0.06) !important;
    padding: 6px 0 !important;
    min-width: 180px !important;
    background: #ffffff !important;
    z-index: 1050 !important;
}
.wa-dropdown-menu .dropdown-item {
    padding: 8px 16px !important;
    font-size: 0.875rem !important;
    color: #111b21 !important;
    font-weight: 400 !important;
    display: flex !important;
    align-items: center !important;
    transition: background-color 0.12s ease !important;
    border: none !important;
    background: transparent;
    width: 100%;
    text-align: left;
}
.wa-dropdown-menu .dropdown-item:hover,
.wa-dropdown-menu .dropdown-item:focus {
    background-color: #f5f6f6 !important;
    color: #111b21 !important;
}
.wa-dropdown-menu .dropdown-item:active {
    background-color: #e9edef !important;
}
.wa-dropdown-menu .wa-dropdown-icon {
    width: 20px;
    margin-right: 12px;
    font-size: 14px;
    color: #54656f;
    text-align: center;
    display: inline-block;
    flex-shrink: 0;
}
.wa-dropdown-menu .dropdown-item.text-danger {
    color: #ea3943 !important;
}
.wa-dropdown-menu .dropdown-item.text-danger .wa-dropdown-icon {
    color: #ea3943 !important;
}
.wa-dropdown-menu .dropdown-item.text-danger:hover,
.wa-dropdown-menu .dropdown-item.text-danger:focus {
    background-color: #fff1f2 !important;
    color: #dc2626 !important;
}
.wa-dropdown-menu .dropdown-item.text-danger:hover .wa-dropdown-icon {
    color: #dc2626 !important;
}
.wa-dropdown-divider {
    border: 0;
    border-top: 1px solid #f0f2f5;
    margin: 4px 0;
    opacity: 1;
}
.chat-footer {
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 12px 18px;
    flex-shrink: 0;
    z-index: 5;
}
.avatar-circle {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #25d366;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.1rem;
}
/* Notif pesan baru */
#newMsgNotif {
    position: absolute;
    bottom: 80px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    cursor: pointer;
    animation: slideUp 0.3s ease;
    white-space: nowrap;
}
@keyframes slideUp {
    from { opacity: 0; transform: translateX(-50%) translateY(10px); }
    to   { opacity: 1; transform: translateX(-50%) translateY(0); }
}
/* Kedip badge baru */
@keyframes blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}
.blink-badge {
    animation: blink 1.2s ease-in-out infinite;
}

/* ==========================================================================
   ATURAN RESPONSIF (HP / LAYAR SEMPIT: < 768px)
   ========================================================================== */
@media (max-width: 767.98px) {
    .chat-container {
        height: calc(100vh - 176px);
        height: calc(100dvh - 176px);
        min-height: 0;
        border-radius: 8px !important;
        margin-bottom: 0.5rem;
    }

    /* Mode awal di HP: Tampilkan sidebar daftar percakapan 100%, sembunyikan area chat */
    .chat-sidebar {
        width: 100% !important;
        border-right: none !important;
        display: flex !important;
    }
    .chat-main {
        display: none !important;
        width: 100% !important;
    }

    /* Mode percakapan dibuka: Sembunyikan sidebar, tampilkan area chat 100% */
    .chat-container.show-chat .chat-sidebar {
        display: none !important;
    }
    .chat-container.show-chat .chat-main {
        display: flex !important;
        width: 100% !important;
    }

    /* Header obrolan di HP */
    .chat-header {
        padding: 8px 12px !important;
    }
    .avatar-circle {
        width: 38px;
        height: 38px;
        font-size: 0.95rem;
    }

    /* Gelembung pesan di HP: lebih lebar dan font pas */
    .chat-body {
        padding: 12px 10px !important;
        gap: 8px !important;
    }
    .chat-bubble {
        max-width: 86% !important;
        font-size: 0.88rem !important;
        padding: 6px 12px !important;
    }
    .chat-meta {
        font-size: 0.68rem !important;
        margin-top: 2px !important;
    }

    /* Input pesan di HP: hemat ruang */
    .chat-footer {
        padding: 8px 10px !important;
    }
    #chatInput {
        font-size: 0.92rem !important;
        padding: 0.45rem 0.75rem !important;
    }

    /* Di layar sentuh: tombol trigger dropdown selalu tampak dengan opacity halus */
    .chat-conv-item .chat-conv-trigger {
        display: inline-flex !important;
        opacity: 0.65;
    }
    .chat-conv-item .chat-conv-trigger:active,
    .chat-conv-item .chat-conv-trigger[aria-expanded="true"] {
        opacity: 1;
        background: #e9edef;
    }
}

/* Tablet (768px - 991px): Sidebar sedikit lebih ramping agar area obrolan lebih lega */
@media (min-width: 768px) and (max-width: 991.98px) {
    /* Bar navbar mobile masih tampil di lebar ini, jadi ruang atasnya 118px,
       bukan 74px seperti di >=992px. */
    .chat-container {
        height: calc(100vh - 184px);
    }
    .chat-sidebar {
        width: 310px !important;
    }
    .chat-bubble {
        max-width: 75% !important;
    }
}

/* Kedua tombol composer diberi tinggi tetap yang sama dengan kolom teksnya:
   tanpa itu tingginya beda ~5px hanya karena Template berisi teks (line-height
   1.4) sedangkan Kirim hanya ikon (line-height normal dari FontAwesome). */
#chatFooter .btn-komposer {
    height: 2.125rem;
}

/* Chip di kanan kepala obrolan (status, order, Belum Terdaftar, Buat Customer,
   Profil 360) dibuat kotak yang sama persis. Badge dan .btn bawaan punya padding
   & line-height sendiri (dan .btn-sm memakai py-1/px-2 !important), jadi padding
   dan line-height dipaksa lewat !important agar keduanya benar-benar sejajar dan
   sama tinggi, bukan cuma sama height. */
#customerBadge .badge,
#customerBadge .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 2rem;
    padding: 0 0.65rem !important;
    border-radius: 10px !important;
    font-size: 0.78rem !important;
    line-height: 1 !important;
    white-space: nowrap;
    vertical-align: middle;
}

/* Layar sempit: kedua tombol ikon di composer (Template & Kirim) dibuat kotak
   seukuran kolom teksnya — sama lebar, sama tinggi, sama radius — supaya
   sejajar dan tidak ada yang lebih kempes. .btn-sm dan .px-* sama-sama
   !important, jadi ukurannya juga harus !important. */
@media (max-width: 767.98px) {
    #chatFooter .btn-komposer {
        width: 2.375rem;
        height: 2.375rem;
        padding: 0 !important;
        border-radius: 10px !important;
    }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include($boleh_kelola ? 'admin/navbar' : 'user/navbar') ?>

<div class="container-xxl py-2 px-2 py-md-4 px-md-3">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2 mb-md-3">
        <h1 class="h4 h3-md mb-0 fw-bold">Live WhatsApp Chat</h1>
        <?php if ($boleh_kelola): ?>
            <button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalInfoWebhook">
                <i class="fal fa-webhook me-1"></i> Webhook
            </button>
        <?php endif; ?>
    </div>

    <div class="chat-container d-flex">
        <!-- Sidebar Daftar Percakapan -->
        <div class="chat-sidebar">
            <div class="p-3 border-bottom bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 fw-bold"><i class="fab fa-whatsapp text-success me-1"></i> Percakapan</h6>
                    <button class="btn btn-sm btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalChatBaru">
                        <i class="fal fa-plus me-1"></i> Chat Baru
                    </button>
                </div>
                <div class="position-relative">
                    <i class="fal fa-search text-muted position-absolute top-50 start-0 translate-middle-y ms-2"></i>
                    <input type="text" id="searchConv" class="form-control form-control-sm chat-cari" placeholder="Cari kontak / nomor...">
                </div>
            </div>

            <div class="chat-conversation-list" id="convList">
                <div class="p-4 text-center text-muted">
                    <div class="spinner-border spinner-border-sm text-secondary mb-2" role="status"></div>
                    <div>Memuat percakapan...</div>
                </div>
            </div>
        </div>

        <!-- Area Obrolan Chat -->
        <div class="chat-main" id="chatArea">
            <!-- Header Chat Aktif -->
            <div class="chat-header d-flex justify-content-between align-items-center" id="chatHeader" style="display: none !important;">
                <div class="d-flex align-items-center min-w-0 me-2">
                    <button type="button" class="btn btn-sm border-0 bg-transparent text-dark me-2 d-md-none flex-shrink-0" id="btnBackToConvList" title="Kembali ke daftar percakapan">
                        <i class="fal fa-arrow-left"></i>
                    </button>
                    <div class="avatar-circle me-2 me-md-3 flex-shrink-0" id="activeAvatar">U</div>
                    <div class="min-w-0">
                        <h6 class="mb-0 fw-bold text-truncate" id="activeName">-</h6>
                        <small class="text-muted font-monospace text-truncate d-block" id="activePhone">-</small>
                    </div>
                </div>
                <div id="customerBadge" class="flex-shrink-0 ms-2"></div>
            </div>

            <!-- Pesan Percakapan -->
            <div class="chat-body" id="chatMessages" style="position:relative;">
                <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                    <div class="p-4 rounded-circle bg-white shadow-sm mb-3 text-success">
                        <i class="fab fa-whatsapp fa-3x"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">WhatsApp Live Chat CRM</h5>
                    <p class="small text-muted mb-3 text-center" style="max-width: 320px;">
                        Pilih salah satu percakapan di sebelah kiri atau mulai obrolan baru untuk mengirim pesan WhatsApp secara langsung.
                    </p>
                    <button class="btn btn-success btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalChatBaru">
                        <i class="fal fa-paper-plane me-1"></i> Mulai Obrolan Baru
                    </button>
                </div>
                <!-- Notifikasi pesan baru (muncul saat ada pesan masuk baru) -->
                <button id="newMsgNotif" class="btn btn-success btn-sm shadow rounded-pill px-4 py-2" style="display:none;">
                    <i class="fal fa-arrow-down me-1"></i> <span>Pesan baru</span>
                </button>
            </div>

            <!-- Footer Input Kirim Pesan -->
            <div class="chat-footer" id="chatFooter" style="display: none;">
                <div class="d-flex align-items-end gap-2">
                    <div class="dropdown flex-shrink-0">
                        <button class="btn btn-outline-secondary btn-komposer dropdown-toggle px-2 px-md-3" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Pilih Template Cepat">
                            <i class="fal fa-bolt"></i> <span class="d-none d-md-inline">Template</span>
                        </button>
                        <ul class="dropdown-menu shadow">
                            <?php foreach ($templates as $t) : ?>
                                <li>
                                    <a class="dropdown-item small btn-insert-tpl" href="#" data-text="<?= esc($t['pesan']) ?>">
                                        <strong><?= esc($t['judul']) ?></strong>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <textarea id="chatInput" class="form-control" rows="1" placeholder="Ketik pesan WhatsApp..." style="resize: none;"></textarea>
                    <button class="btn btn-success px-3 flex-shrink-0 btn-komposer" id="btnKirimPesan" title="Kirim Pesan">
                        <i class="fal fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Chat Baru -->
<div class="modal fade" id="modalChatBaru" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fab fa-whatsapp me-2"></i>Mulai Obrolan WhatsApp Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="alertNewChat" class="alert d-none"></div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Nomor WhatsApp Tujuan</label>
                    <input type="text" id="newChatNomor" class="form-control font-monospace" placeholder="628123456789 atau 08123456789">
                    <div class="form-text small">Gunakan format internasional (contoh: 628xxx).</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">Pesan Pertama</label>
                    <textarea id="newChatPesan" class="form-control font-monospace" rows="4" placeholder="Halo kak, ada yang bisa kami bantu?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success fw-bold" id="btnSubmitNewChat">
                    <i class="fab fa-whatsapp me-1"></i> Mulai &amp; Kirim Chat
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Info Webhook -->
<div class="modal fade" id="modalInfoWebhook" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fal fa-webhook me-2"></i>Webhook WhatsApp Inbound</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    Untuk menerima balasan chat dari pelanggan ke website ini secara real-time, daftarkan URL berikut di dashboard <strong>Fonnte (Menu Device &gt; Edit Device &gt; Webhook URL)</strong> atau <strong>Kapso &gt; Integrations &gt; Webhooks</strong>:
                </p>
                <div class="input-group mb-3">
                    <input type="text" class="form-control font-monospace bg-light" value="<?= site_url('webhook/whatsapp') ?>" readonly id="copyWebhookInput">
                    <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText($('#copyWebhookInput').val()); alert('URL Webhook disalin!');">
                        <i class="fal fa-copy"></i> Salin
                    </button>
                </div>
                <div class="alert alert-info small py-2 mb-3">
                    <i class="fal fa-check-circle me-1"></i> Endpoint ini sudah dibuka tanpa blokir CSRF sehingga siap menerima pesan masuk dari Fonnte maupun Kapso.
                </div>

                <h6 class="small fw-bold text-uppercase text-muted mb-2">
                    <i class="fal fa-clock me-1"></i> Status jalur pesan masuk
                </h6>
                <div id="panelStatusWebhook" class="border rounded bg-light px-3 py-2 small mb-2">
                    <div class="text-muted">Memuat status…</div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnMuatStatusWebhook">
                        <i class="fal fa-clock me-1"></i> Muat ulang
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="btnUjiWebhook">
                        <i class="fal fa-bolt me-1"></i> Uji pencatatan pesan
                    </button>
                </div>
                <div id="hasilUjiWebhook" class="alert small py-2 mb-0 d-none"></div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Buat Customer Baru dari Chat WA -->
<div class="modal fade" id="modalBuatCustomerWa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formBuatCustomerWa">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fal fa-user-plus me-2"></i>Jadikan Customer Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="alertBuatCustomer" class="alert d-none"></div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nomor WhatsApp</label>
                        <input type="text" name="nomor" id="inputCustomerNomor" class="form-control font-monospace bg-light" readonly required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nama Lengkap Customer</label>
                        <input type="text" name="nama" id="inputCustomerNama" class="form-control" placeholder="Masukkan nama pelanggan..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Alamat / Catatan Kota (Opsional)</label>
                        <textarea name="alamat" id="inputCustomerAlamat" class="form-control" rows="2" placeholder="Alamat atau kota..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold" id="btnSubmitCustomerBaru">
                        <i class="fal fa-save me-1"></i> Simpan ke CRM
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- Modal Ganti Label Percakapan: hanya mengubah nama di daftar chat, bukan
     data pelanggan di profil CRM -->
<div class="modal fade" id="modalGantiNama" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fal fa-tag me-2"></i>Ganti Nama Chat</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="alertGantiNama" class="alert d-none"></div>
                <div class="mb-3">
                    <label class="form-label small fw-bold" for="inputGantiNama">Nama Percakapan</label>
                    <input type="text" id="inputGantiNama" class="form-control" maxlength="100" placeholder="cth: Bu Rina">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-success fw-bold" id="btnSubmitGantiNama">
                    <i class="fal fa-save me-1"></i> Simpan
                </button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<script>
$(function() {
    'use strict';

    var currentConvId   = null;
    var requestedConvId = <?= (int) ($activeId ?? 0) ?>;
    var bolehKelola     = <?= $boleh_kelola ? 'true' : 'false' ?>; // admin-only: webhook & Profil 360
    var lastMsgId       = 0;     // ID pesan terakhir yang sudah dimuat
    var pollInterval    = null;
    var modalChatBaru   = new bootstrap.Modal(document.getElementById('modalChatBaru'));
    var modalGantiNama  = new bootstrap.Modal(document.getElementById('modalGantiNama'));
    var idGantiNama     = 0;
    // Isian awal panel chat, dipakai lagi setelah chat aktif dihapus
    var placeholderChat = $('#chatMessages').html();
    var isAtBottom      = true;  // Lacak apakah user scroll ke bawah
    var muatPenuhSeq    = 0;     // Penanda permintaan terakhir; respons telat dibuang
    var muatPenuhJalan  = false; // Polling dilarang selagi muat penuh berjalan

    // ── Muat daftar percakapan ──────────────────────────────────────────────
    function loadConversations(keepActive) {
        keepActive = (keepActive === undefined) ? true : keepActive;
        $.getJSON('<?= site_url('admin/crm/chat/percakapan') ?>', function(res) {
            if (res.status === 'success') {
                renderConversations(res.data, keepActive);
            }
        });
    }

    function renderConversations(list, keepActive) {
        var container = $('#convList');
        if (list.length === 0) {
            container.html('<div class="p-4 text-center text-muted small"><i class="fal fa-comments fa-2x mb-2 d-block"></i>Belum ada percakapan chat.</div>');
            if (!currentConvId) resetChatArea();
            return;
        }

        // Jika dropdown sedang terbuka, tunda render agar menu tidak mendadak tertutup saat dibuka pengguna
        if (container.find('.chat-item-dropdown.show, .dropdown-menu.show').length > 0) {
            return;
        }

        // Jika belum ada percakapan aktif yang terbuka:
        // Di DESKTOP (lebar >= 768px): otomatis buka obrolan yang diminta atau pertama.
        // Di MOBILE (< 768px): hanya buka otomatis jika ada ID spesifik yang diminta (requestedConvId > 0).
        if (!currentConvId && list.length > 0) {
            var targetItem = null;
            if (requestedConvId > 0) {
                targetItem = list.find(function(it) { return it.id_percakapan === requestedConvId; });
            }
            if (!targetItem && window.innerWidth >= 768) {
                targetItem = list[0];
            }
            if (targetItem) {
                currentConvId = targetItem.id_percakapan;
                openConversation(targetItem.id_percakapan, targetItem.nama_kontak, targetItem.nomor_wa);
            }
        }

        var html = '';
        list.forEach(function(item) {
            var activeClass  = (keepActive && currentConvId === item.id_percakapan) ? ' active' : '';
            var initial      = (item.nama_kontak || 'U').charAt(0).toUpperCase();
            var unreadBadge  = item.unread_admin > 0
                ? '<span class="badge bg-success rounded-pill blink-badge">Baru</span>'
                : '';
            var prefixArah   = item.arah_terakhir === 'keluar'
                ? '<i class="fal fa-check text-muted me-1"></i>'
                : '<i class="fal fa-arrow-down-left text-success me-1"></i>';

            // Menu dropdown (ganti nama + hapus) kini sama untuk CS dan admin;
            // markup & CSS-nya identik sehingga perilakunya ikut responsif sama.
            var dropdownMenu = '<div class="dropdown chat-item-dropdown">'
                             + '  <button type="button" class="chat-conv-trigger btn-conv-aksi" data-bs-toggle="dropdown" aria-expanded="false" title="Menu chat">'
                             + '    <i class="fal fa-chevron-down"></i>'
                             + '  </button>'
                             + '  <ul class="dropdown-menu dropdown-menu-end wa-dropdown-menu shadow">'
                             + '    <li>'
                             + '      <button type="button" class="dropdown-item btn-ganti-nama d-flex align-items-center" data-id="' + item.id_percakapan + '" data-nama="' + escapeAttr(item.nama_kontak) + '">'
                             + '        <i class="fal fa-pen wa-dropdown-icon"></i>'
                             + '        <span>Ganti nama chat</span>'
                             + '      </button>'
                             + '    </li>'
                             + '    <li><hr class="wa-dropdown-divider my-1"></li>'
                             + '    <li>'
                             + '      <button type="button" class="dropdown-item btn-hapus-chat text-danger d-flex align-items-center" data-id="' + item.id_percakapan + '" data-nama="' + escapeAttr(item.nama_kontak) + '">'
                             + '        <i class="fal fa-trash-alt wa-dropdown-icon text-danger"></i>'
                             + '        <span>Hapus chat</span>'
                             + '      </button>'
                             + '    </li>'
                             + '  </ul>'
                             + '</div>';

            html += '<div class="chat-conv-item p-3 d-flex align-items-center' + activeClass + '"'
                  + ' data-id="' + item.id_percakapan + '"'
                  + ' data-nomor="' + escapeAttr(item.nomor_wa) + '"'
                  + ' data-nama="' + escapeAttr(item.nama_kontak) + '">';
            html += '  <div class="avatar-circle me-3 flex-shrink-0">' + initial + '</div>';
            html += '  <div class="flex-grow-1 min-w-0">';
            html += '    <div class="d-flex justify-content-between align-items-baseline mb-1">';
            html += '      <strong class="text-truncate text-dark">' + escapeHtml(item.nama_kontak) + '</strong>';
            html += '      <small class="text-muted font-monospace ms-2 flex-shrink-0" style="font-size:0.75rem;">' + item.waktu + '</small>';
            html += '    </div>';
            html += '    <div class="d-flex justify-content-between align-items-center">';
            html += '      <div class="small text-muted text-truncate d-flex align-items-center pe-1">';
            html += '        ' + prefixArah + escapeHtml(item.pesan_terakhir || 'Mulai percakapan...');
            html += '      </div>';
            html += '      <div class="d-flex align-items-center flex-shrink-0 ms-2">' + unreadBadge + dropdownMenu + '</div>';
            html += '    </div>';
            html += '  </div>';
            html += '</div>';
        });

        container.html(html);
    }

    // ── Klik percakapan ─────────────────────────────────────────────────────
    $(document).on('click', '.chat-conv-item', function(e) {
        if ($(e.target).closest('.btn-conv-aksi, .chat-item-dropdown, .dropdown-menu').length) return;

        var id   = $(this).data('id');
        var nama = $(this).data('nama');
        var nomor = $(this).data('nomor');

        $('.chat-conv-item').removeClass('active');
        $(this).addClass('active');

        // Reset state percakapan
        if (currentConvId !== id) {
            currentConvId = id;
            lastMsgId     = 0;
        }

        openConversation(id, nama, nomor);
    });

    // Klik kanan (contextmenu) di baris percakapan membuka menu dropdown ala WhatsApp Web
    $(document).on('contextmenu', '.chat-conv-item', function(e) {
        if ($(e.target).is('input, textarea, a, button')) return;
        e.preventDefault();
        var $trigger = $(this).find('.chat-conv-trigger');
        if ($trigger.length) {
            var dd = bootstrap.Dropdown.getOrCreateInstance($trigger[0]);
            dd.toggle();
        }
    });

    // Tombol kembali di HP (dari area obrolan kembali ke daftar percakapan)
    $(document).on('click', '#btnBackToConvList', function(e) {
        e.preventDefault();
        $('.chat-container').removeClass('show-chat');
        $('#chatInput').blur();
    });

    // Sesuaikan jika layar di-resize (misal rotasi perangkat)
    $(window).on('resize', function() {
        if (window.innerWidth >= 768) {
            $('.chat-container').removeClass('show-chat');
        }
    });

    function openConversation(id, nama, nomor) {
        // Kontak yang belum disimpan punya nama berupa nomor telepon. jQuery .data()
        // mengubah angka jadi Number, dan charAt()/trim() di atas Number melempar
        // TypeError yang menghentikan fungsi ini sebelum pesan dimuat — akibatnya
        // bubble percakapan lama tetap tertempel di percakapan yang baru dibuka.
        nama  = nama === null || nama === undefined ? '' : String(nama);
        nomor = nomor === null || nomor === undefined ? '' : String(nomor);

        $('#activeName').text(nama);
        $('#activePhone').text(nomor);
        $('#activeAvatar').text((nama || 'U').charAt(0).toUpperCase());
        $('#chatHeader').attr('style', 'display: flex !important;');
        $('#chatFooter').css('display', 'block').show();
        $('#newMsgNotif').hide();

        // Di layar HP, aktifkan tampilan chat (sembunyikan sidebar)
        $('.chat-container').addClass('show-chat');

        // Hanya auto-focus input di desktop (di HP auto-focus memunculkan keyboard virtual yang menutup layar)
        if (window.innerWidth >= 768) {
            setTimeout(function() { $('#chatInput').focus(); }, 100);
        }

        // Full load pertama kali
        loadMessagesFull(id);
    }

    function resetChatArea() {
        currentConvId = null;
        lastMsgId     = 0;
        $('#chatHeader').attr('style', 'display: none !important;');
        $('#chatFooter').css('display', 'none');
        $('#customerBadge').empty();
        $('#chatMessages').html(placeholderChat);
        $('.chat-container').removeClass('show-chat');
    }

    // ── Load full (pertama buka percakapan) ─────────────────────────────────
    function loadMessagesFull(id) {
        if (!id) return;
        var seq = ++muatPenuhSeq;
        muatPenuhJalan = true;

        // Bersihkan kanvas lebih dulu supaya bubble percakapan lama tidak
        // tertinggal di bawah pesan yang baru dimuat.
        $('#chatMessages').empty();

        $.getJSON('<?= site_url('admin/crm/chat/pesan/') ?>' + id, function(res) {
            // Respons dari percakapan yang sudah ditinggalkan jangan dipakai.
            if (seq !== muatPenuhSeq || id !== currentConvId) return;
            muatPenuhJalan = false;

            if (res.status === 'success') {
                var num = res.percakapan ? res.percakapan.nomor_wa : $('#activePhone').text();
                renderCustomerBadge(res.pelanggan, num);
                renderMessages(res.messages, true);
                if (res.messages.length > 0) {
                    lastMsgId = res.messages[res.messages.length - 1].id_pesan;
                }
                // Refresh sidebar
                loadConversations(true);
            }
        }).fail(function() {
            if (seq === muatPenuhSeq) muatPenuhJalan = false;
        });
    }

    // ── Incremental polling: append pesan baru saja ──────────────────────────
    function pollNewMessages() {
        var id = currentConvId;

        // Dengan last_id=0 server membalas SELURUH pesan, jadi polling saat muat
        // penuh belum selesai akan menempelkan isi percakapan ini di atas bubble
        // percakapan sebelumnya — itulah sebabnya dua chat tampak menyatu.
        if (!id || muatPenuhJalan) return;

        var url = '<?= site_url('admin/crm/chat/pesan/') ?>' + id + '?last_id=' + lastMsgId;
        $.getJSON(url, function(res) {
            if (id !== currentConvId) return;
            if (res.status === 'success' && res.messages && res.messages.length > 0) {
                appendMessages(res.messages);
                lastMsgId = res.messages[res.messages.length - 1].id_pesan;
                // Refresh sidebar untuk update pesan terakhir + badge
                loadConversations(true);
            }
        });

        // Update ikon centang pesan keluar yang mungkin statusnya berubah (terkirim → diterima → dibaca)
        updateOutgoingStatuses();
    }

    // ── Update status centang pesan keluar secara real-time ─────────────────
    function buildCheckIcon(status) {
        if (status === 'dibaca') {
            return ' <i class="fal fa-check-double ms-1" style="color:#53bdeb;" title="Dibaca"></i>';
        } else if (status === 'diterima') {
            return ' <i class="fal fa-check-double ms-1 text-secondary" title="Diterima"></i>';
        } else if (status === 'gagal') {
            return ' <i class="fal fa-exclamation-circle ms-1 text-danger" title="Gagal terkirim"></i>';
        }
        return ' <i class="fal fa-check ms-1 text-secondary" title="Terkirim"></i>';
    }

    function updateOutgoingStatuses() {
        // Cari semua bubble pesan keluar yang belum status 'dibaca'
        var $bubbles = $('.chat-bubble.keluar').not('[data-status="dibaca"]');
        if ($bubbles.length === 0) return;

        // Ambil ID pesan yang perlu dicek
        var ids = [];
        $bubbles.each(function() {
            var id = $(this).data('id');
            if (id) ids.push(id);
        });
        if (ids.length === 0) return;

        $.ajax({
            url: '<?= site_url('admin/crm/chat/status_pesan') ?>',
            method: 'GET',
            data: { ids: ids.join(',') },
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success' && res.data) {
                    $.each(res.data, function(id, status) {
                        var $bubble = $('.chat-bubble.keluar[data-id="' + id + '"]');
                        if (!$bubble.length) return;
                        var currentStatus = $bubble.data('status');
                        if (currentStatus !== status) {
                            $bubble.attr('data-status', status).data('status', status);
                            // Update ikon centang di .chat-meta
                            var $meta = $bubble.find('.chat-meta');
                            var waktu = $meta.text().trim().replace(/\s*[\u2713\u2713\u2713\u2713\u2713!]*$/, '').trim();
                            // Ambil hanya waktu (sebelum ikon)
                            var waktuTeks = $meta.clone().children().remove().end().text().trim();
                            $meta.html(waktuTeks + buildCheckIcon(status));
                        }
                    });
                }
            }
        });
    }

    // ── Render semua pesan (full load) ──────────────────────────────────────
    function renderMessages(messages, scrollToBottom) {
        var container = $('#chatMessages');
        if (messages.length === 0) {
            container.html('<div class="text-center text-muted my-auto no-msg-placeholder"><i class="fal fa-comment-dots fa-2x mb-2 d-block"></i>Belum ada pesan. Kirim pesan pertama Anda!</div>');
            return;
        }

        var html    = '';
        var lastDate = '';

        messages.forEach(function(m) {
            html += buildMessageHtml(m, lastDate);
            lastDate = m.tanggal;
        });

        container.html(html);

        if (scrollToBottom) {
            scrollBottom();
        }
    }

    // ── Append pesan baru (incremental) ─────────────────────────────────────
    function appendMessages(messages) {
        var container = $('#chatMessages');
        // Hapus placeholder jika ada
        container.find('.no-msg-placeholder').remove();

        var lastDate = container.find('[data-date]').last().data('date') || '';
        var html     = '';
        var jumlah   = 0;

        messages.forEach(function(m) {
            // Gelembung yang sudah tampil tidak ditempel ulang
            if (m.id_pesan && container.find('.chat-bubble[data-id="' + m.id_pesan + '"]').length) {
                lastDate = m.tanggal;
                return;
            }
            html += buildMessageHtml(m, lastDate);
            lastDate = m.tanggal;
            jumlah++;
        });

        if (jumlah === 0) return;

        container.append(html);
        container.find('.sending-bubble').remove();

        // Cek apakah ada pesan masuk (dari pelanggan) — kalau ya, scroll ke bawah + notif
        var hasInbound = messages.some(function(m) { return m.arah === 'masuk'; });

        // Jika user sudah di posisi bawah ATAU ada pesan masuk baru, scroll
        checkIsAtBottom();
        if (isAtBottom || hasInbound) {
            scrollBottom();
        } else if (hasInbound) {
            // Tampilkan notifikasi ada pesan baru
            showNewMsgNotif(jumlah);
        }
    }

    // ── Build HTML bubble pesan tunggal ─────────────────────────────────────
    function buildMessageHtml(m, prevDate) {
        var html = '';
        if (m.tanggal !== prevDate) {
            html += '<div class="text-center my-2" data-date="' + escapeAttr(m.tanggal) + '">'
                  + '<span class="badge bg-white text-muted shadow-sm px-3 py-1 font-monospace" style="font-size:0.75rem;">'
                  + m.tanggal + '</span></div>';
        }

        // Ikon status centang — hanya untuk pesan keluar, persis gaya WhatsApp
        var checkIcon = '';
        if (m.arah === 'keluar') {
            var st = m.status || 'terkirim';
            if (st === 'dibaca') {
                // ✓✓ biru = sudah dibaca
                checkIcon = ' <i class="fal fa-check-double ms-1" style="color:#53bdeb;" title="Dibaca"></i>';
            } else if (st === 'diterima') {
                // ✓✓ abu = sudah diterima di HP penerima
                checkIcon = ' <i class="fal fa-check-double ms-1 text-secondary" title="Diterima"></i>';
            } else if (st === 'gagal') {
                // Tanda seru merah = gagal kirim
                checkIcon = ' <i class="fal fa-exclamation-circle ms-1 text-danger" title="Gagal terkirim"></i>';
            } else {
                // ✓ abu = terkirim ke server (belum sampai HP)
                checkIcon = ' <i class="fal fa-check ms-1 text-secondary" title="Terkirim"></i>';
            }
        }

        html += '<div class="chat-bubble ' + m.arah + '" data-id="' + m.id_pesan + '" data-status="' + (m.status || '') + '">';
        html += '  <div class="chat-bubble-isi">' + escapeHtml(m.isi_pesan) + '</div>';
        html += '  <div class="chat-meta">' + m.waktu + checkIcon + '</div>';
        html += '</div>';
        return html;
    }

    // ── Notifikasi pesan baru (saat user tidak di bawah) ────────────────────
    function showNewMsgNotif(count) {
        var $notif = $('#newMsgNotif');
        if (!$notif.length) return;
        $notif.text(count + ' pesan baru ↓').show();
    }

    // ── Lacak posisi scroll ──────────────────────────────────────────────────
    function checkIsAtBottom() {
        var el = document.getElementById('chatMessages');
        if (!el) return;
        isAtBottom = (el.scrollHeight - el.scrollTop - el.clientHeight) < 60;
    }

    $('#chatMessages').on('scroll', function() {
        checkIsAtBottom();
        if (isAtBottom) {
            $('#newMsgNotif').hide();
        }
    });

    // Klik notif → scroll ke bawah
    $(document).on('click', '#newMsgNotif', function() {
        scrollBottom();
        $(this).hide();
    });

    function scrollBottom() {
        var el = document.getElementById('chatMessages');
        if (el) {
            el.scrollTop = el.scrollHeight;
            isAtBottom = true;
            $('#newMsgNotif').hide();
        }
    }

    // ── Render badge pelanggan & auto-match ─────────────────────────────────
    function renderCustomerBadge(pelanggan, currentNumber) {
        var $badge = $('#customerBadge');
        if (pelanggan) {
            var st = pelanggan.status_crm || 'prospek';
            var stBadge = st === 'loyal' ? 'badge bg-warning text-dark'
                        : (st === 'aktif' ? 'badge bg-success'
                        : (st === 'baru' ? 'badge bg-info text-dark'
                        : (st === 'tidak_aktif' ? 'badge bg-secondary' : 'badge bg-light text-muted border')));
            var stText = st === 'loyal' ? 'Loyal' : (st === 'aktif' ? 'Aktif' : (st === 'baru' ? 'Baru' : (st === 'tidak_aktif' ? 'Pasif' : 'Prospek')));
            
            var totalOrder = pelanggan.jumlah_order || 0;
            var totalBelanja = Number(pelanggan.nilai || 0).toLocaleString('id-ID');
            var sisaPiutang = pelanggan.sisa ? Number(pelanggan.sisa).toLocaleString('id-ID') : '0';

            var html = '<div class="d-flex align-items-center gap-1 gap-md-2 flex-wrap justify-content-end">';
            /* Layar sempit: badge status dan tombol profil jadi ikon saja; katanya
               dipindah ke title. Badge "Order | Rp" memang hanya tampil di >=992px. */
            html += '<span class="' + stBadge + '" style="font-size:0.75rem;" title="' + stText + '"><i class="fal fa-check-circle"></i><span class="d-none d-lg-inline"> ' + stText + '</span></span>';
            var adaSisa = Number(pelanggan.sisa || 0) > 0;
            var ketOrder = totalOrder + ' Order | Rp ' + totalBelanja + (adaSisa ? ' | Sisa: Rp ' + sisaPiutang : '');
            html += '<span class="badge bg-light text-dark border" style="font-size:0.75rem;" title="' + escapeAttr(ketOrder) + '">'
                  + '<i class="fal fa-shopping-bag text-primary"></i>'
                  + '<span class="d-none d-lg-inline"> ' + totalOrder + ' Order | Rp ' + totalBelanja
                  + (adaSisa ? ' | <span class="text-danger">Sisa: Rp ' + sisaPiutang + '</span>' : '')
                  + '</span></span>';
            if (bolehKelola) {
            html += '<a href="<?= site_url('admin/crm/pelanggan/') ?>' + pelanggan.id_pelanggan + '" target="_blank" title="Profil 360°" class="btn btn-sm btn-primary py-1 px-2 fw-semibold" style="font-size:0.78rem;">'
                  + '<i class="fal fa-id-card"></i><span class="d-none d-lg-inline"> Profil 360°</span>'
                  + '</a>';
            }
            html += '</div>';
            $badge.html(html);
        } else {
            var num = currentNumber || $('#activePhone').text() || '';
            var html = '<div class="d-flex align-items-center gap-1 gap-md-2">';
            html += '<span class="badge bg-light text-muted border d-none d-sm-inline"><i class="fal fa-user-times me-1 text-warning"></i> Belum Terdaftar</span>';
            // "Jadikan Customer" tetap dibuka untuk CS: inilah yang menautkan
            // pelanggan_id ke percakapan, syarat KPI "Konversi lead ke order"
            // mengenali lead. Yang tetap khusus admin hanya Profil 360 (halaman
            // kelola pelanggan), bukan pendaftaran lead dari chat.
            html += '<button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 fw-semibold" id="btnJadikanCustomer" data-nomor="' + escapeAttr(num) + '" style="font-size:0.78rem;">'
                  + '<i class="fal fa-user-plus me-1"></i><span class="d-none d-sm-inline">+ Buat </span><span class="d-sm-none">+ </span>Customer'
                  + '</button>';
            html += '</div>';
            $badge.html(html);
        }
    }

    // ── Kirim pesan dari Live Chat ───────────────────────────────────────────
    function sendMessage() {
        var text = $('#chatInput').val().trim();
        if (!text || !currentConvId) return;

        var btn = $('#btnKirimPesan');
        btn.prop('disabled', true);

        // Optimistic UI: tambah bubble sementara
        var now    = new Date();
        var waktu  = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
        var tmpHtml = '<div class="chat-bubble keluar sending-bubble" style="opacity:0.6;">'
            + '<div>' + escapeHtml(text) + '</div>'
            + '<div class="chat-meta">' + waktu + ' <i class="fal fa-clock text-muted ms-1"></i></div>'
            + '</div>';
        $('#chatMessages').append(tmpHtml);
        scrollBottom();
        $('#chatInput').val('');

        $.ajax({
            url: '<?= site_url('admin/crm/chat/kirim') ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                id_percakapan: currentConvId,
                pesan: text
            },
            success: function(res) {
                btn.prop('disabled', false);
                if (res.status === 'success') {
                    // Bubble sementara dibuang oleh appendMessages/renderMessages
                    // begitu pesan aslinya benar-benar muncul dari server.
                    pollNewMessages();
                    loadConversations(true);
                } else {
                    $('.sending-bubble').remove();
                    alert('Gagal mengirim pesan: ' + res.message);
                    // Kembalikan teks ke input jika gagal
                    $('#chatInput').val(text);
                }
            },
            error: function() {
                btn.prop('disabled', false);
                $('.sending-bubble').remove();
                alert('Gagal menghubungi server.');
                $('#chatInput').val(text);
            }
        });
    }

    $('#btnKirimPesan').on('click', sendMessage);

    $('#chatInput').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    // Auto-resize textarea
    $('#chatInput').on('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // ── Masukkan template cepat ──────────────────────────────────────────────
    // Token {nama} {invoice} {total} ... diisi lebih dulu dengan data percakapan
    // yang sedang dibuka, supaya CS melihat isi pesan yang sebenarnya dan bukan
    // kurung kurawal yang terkirim mentah ke pelanggan.
    function isiTokenPesan(teks, token) {
        return teks.replace(/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/g, function (utuh, kunci) {
            var nilai = token[kunci.toLowerCase()];
            return (nilai === undefined || nilai === null || nilai === '') ? utuh : nilai;
        });
    }

    $(document).on('click', '.btn-insert-tpl', function(e) {
        e.preventDefault();
        var mentah = String($(this).attr('data-text') || '');
        var idSaatKlik = currentConvId;

        if (!idSaatKlik) {
            $('#chatInput').val(mentah).focus();
            return;
        }

        $.getJSON('<?= site_url('admin/crm/chat/token') ?>', { id_percakapan: idSaatKlik }, function(res) {
            // Percakapan sudah dipindah sebelum datanya balik — jangan menimpa input orang.
            if (idSaatKlik !== currentConvId) return;
            var token = (res && res.status === 'success' && res.data) ? res.data : {};
            $('#chatInput').val(isiTokenPesan(mentah, token)).focus().trigger('input');
        }).fail(function() {
            if (idSaatKlik !== currentConvId) return;
            $('#chatInput').val(mentah).focus().trigger('input');
        });
    });

    // ── Chat Baru ────────────────────────────────────────────────────────────
    // Nomor tujuan selalu dibersihkan ulang setiap kali isian berubah (ketik,
    // paste, atau autofill): buang simbol, ganti awalan 0 jadi 62, maksimal 13 angka
    $('#newChatNomor').on('input', function() {
        var angka = this.value.replace(/[^\d]/g, '');
        angka = angka.replace(/^00(?=62)/, '');
        if (angka.charAt(0) === '0') {
            angka = '62' + angka.replace(/^0+/, '');
        }
        angka = angka.slice(0, 13);
        if (angka !== this.value) {
            this.value = angka;
        }
    });

    // Setiap kali modal dibuka: form selalu kosong, tidak membawa sisa sebelumnya
    $('#modalChatBaru').on('show.bs.modal', function() {
        $('#newChatNomor').val('');
        $('#newChatPesan').val('');
        $('#alertNewChat').addClass('d-none');
        $('#btnSubmitNewChat').prop('disabled', false)
            .html('<i class="fab fa-whatsapp me-1"></i> Mulai &amp; Kirim Chat');
    });

    $('#btnSubmitNewChat').on('click', function() {
        var nomor = $('#newChatNomor').val().trim();
        var pesan = $('#newChatPesan').val().trim();

        if (!nomor || !pesan) {
            $('#alertNewChat').removeClass('d-none alert-success').addClass('alert-danger').text('Nomor dan pesan wajib diisi.');
            return;
        }

        var btn = $(this);
        btn.prop('disabled', true).text('Mengirim...');

        $.ajax({
            url: '<?= site_url('admin/crm/chat/kirim') ?>',
            type: 'POST',
            dataType: 'json',
            data: { nomor: nomor, pesan: pesan },
            success: function(res) {
                btn.prop('disabled', false).html('<i class="fab fa-whatsapp me-1"></i> Mulai &amp; Kirim Chat');
                if (res.status === 'success') {
                    modalChatBaru.hide();
                    $('#newChatPesan').val('');
                    if (res.id_percakapan) {
                        requestedConvId = res.id_percakapan;
                        currentConvId   = null;
                    }
                    loadConversations(false);
                } else {
                    $('#alertNewChat').removeClass('d-none alert-success').addClass('alert-danger').text(res.message);
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="fab fa-whatsapp me-1"></i> Mulai &amp; Kirim Chat');
                $('#alertNewChat').removeClass('d-none alert-success').addClass('alert-danger').text('Gagal menghubungi server.');
            }
        });
    });

    // ── Hapus satu chat beserta semua pesannya ───────────────────────────────
    $(document).on('click', '.btn-hapus-chat', function(e) {
        e.stopPropagation();
        var $btn  = $(this);
        var $item = $btn.closest('.chat-conv-item');
        var id    = $btn.data('id') || $item.data('id');
        var nama  = ($btn.data('nama') !== undefined ? $btn.data('nama') : ($item.data('nama') || '')).toString();

        var pertanyaan = 'Hapus chat "' + nama + '" beserta semua pesannya?'
            + '\nChat yang sudah dihapus tidak bisa dikembalikan.';
        if (!window.confirm(pertanyaan)) return;

        $.post('<?= site_url('admin/crm/chat/hapus') ?>', { id_percakapan: id }, function(res) {
            if (res.status !== 'success') {
                alert(res.message || 'Gagal menghapus chat.');
                return;
            }
            if (currentConvId === id) resetChatArea();
            $item.remove();
            loadConversations(true);
        }, 'json').fail(function() {
            alert('Gagal menghubungi server.');
        });
    });

    // ── Ganti nama chat (label percakapan saja) ─────────────────────────────
    $(document).on('click', '.btn-ganti-nama', function(e) {
        e.stopPropagation();
        var $btn  = $(this);
        var $item = $btn.closest('.chat-conv-item');

        idGantiNama = $btn.data('id') || $item.data('id');
        var nama    = ($btn.data('nama') !== undefined ? $btn.data('nama') : ($item.data('nama') || '')).toString();
        $('#alertGantiNama').addClass('d-none');
        $('#inputGantiNama').val(nama);

        modalGantiNama.show();
    });

    $('#btnSubmitGantiNama').on('click', function() {
        var nama = $('#inputGantiNama').val().trim();
        if (nama === '') {
            $('#alertGantiNama').removeClass('d-none alert-success').addClass('alert-danger').text('Nama tidak boleh kosong.');
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Menyimpan...');
        $.post('<?= site_url('admin/crm/chat/nama') ?>', { id_percakapan: idGantiNama, nama_kontak: nama }, function(res) {
            $btn.prop('disabled', false).html('<i class="fal fa-save me-1"></i> Simpan');
            if (res.status !== 'success') {
                $('#alertGantiNama').removeClass('d-none alert-success').addClass('alert-danger')
                    .text(res.message || 'Gagal menyimpan nama.');
                return;
            }
            modalGantiNama.hide();
            if (currentConvId === idGantiNama) {
                $('#activeName').text(nama);
                $('#activeAvatar').text(nama.charAt(0).toUpperCase());
            }
            loadConversations(true);
        }, 'json').fail(function() {
            $btn.prop('disabled', false).html('<i class="fal fa-save me-1"></i> Simpan');
            $('#alertGantiNama').removeClass('d-none alert-success').addClass('alert-danger').text('Gagal menghubungi server.');
        });
    });

    // ── Filter search kontak ─────────────────────────────────────────────────
    $('#searchConv').on('keyup', function() {
        var q = $(this).val().toLowerCase();
        $('.chat-conv-item').each(function() {
            var nama  = ($(this).data('nama') || '').toString().toLowerCase();
            var nomor = ($(this).data('nomor') || '').toString().toLowerCase();
            $(this).toggle(nama.indexOf(q) !== -1 || nomor.indexOf(q) !== -1);
        });
    });

    // ── Utility ─────────────────────────────────────────────────────────────
    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/[&<>"']/g, function(m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
        });
    }

    function escapeAttr(text) {
        if (!text) return '';
        return String(text).replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // ── Buat Customer Baru dari Percakapan WA ──────────────────────────────
    var modalCustomerBaru = new bootstrap.Modal(document.getElementById('modalBuatCustomerWa'));
    $(document).on('click', '#btnJadikanCustomer', function() {
        var num = $(this).data('nomor') || $('#activePhone').text() || '';
        $('#inputCustomerNomor').val(num);
        $('#inputCustomerNama').val('');
        $('#inputCustomerAlamat').val('');
        $('#alertBuatCustomer').addClass('d-none');
        modalCustomerBaru.show();
    });

    $('#formBuatCustomerWa').on('submit', function(e) {
        e.preventDefault();
        var $btn = $('#btnSubmitCustomerBaru');
        var $alert = $('#alertBuatCustomer');
        $btn.prop('disabled', true).html('<i class="fal fa-spinner fa-spin me-1"></i> Menyimpan...');

        $.ajax({
            url: '<?= site_url('admin/crm/chat/buat_customer') ?>',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(res) {
                $btn.prop('disabled', false).html('<i class="fal fa-save me-1"></i> Simpan ke CRM');
                if (res.status === 'success') {
                    $alert.removeClass('d-none alert-danger').addClass('alert-success').html('<i class="fal fa-check-circle me-1"></i> ' + res.message);
                    renderCustomerBadge(res.pelanggan);
                    setTimeout(function() {
                        modalCustomerBaru.hide();
                        loadConversations(true);
                    }, 1200);
                } else {
                    $alert.removeClass('d-none alert-success').addClass('alert-danger').text(res.message);
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fal fa-save me-1"></i> Simpan ke CRM');
                $alert.removeClass('d-none alert-success').addClass('alert-danger').text('Terjadi kesalahan jaringan atau server.');
            }
        });
    });

    // ── Start ────────────────────────────────────────────────────────────────
    // Muat daftar percakapan awal
    loadConversations(false);

    // Polling daftar percakapan (sidebar) tiap 5 detik
    setInterval(function() {
        loadConversations(true);
    }, 5000);

    // Polling pesan aktif tiap 3 detik (incremental, hanya pesan baru)
    setInterval(function() {
        if (currentConvId) {
            pollNewMessages();
        }
    }, 3000);

    // ── Panel status jalur pesan masuk (di dalam modal Webhook URL) ──────────
    var urlWebhookStatus = '<?= site_url('admin/crm/chat/webhook_status') ?>';
    var urlWebhookUji    = '<?= site_url('admin/crm/chat/webhook_uji') ?>';
    var urlStatusFonnte  = '<?= site_url('admin/crm/cek_status_fonnte') ?>';

    function waktuLalu(ts) {
        var detik = Math.floor(Date.now() / 1000) - Number(ts);
        if (detik < 60) { return detik + ' detik lalu'; }
        if (detik < 3600) { return Math.floor(detik / 60) + ' menit lalu'; }
        if (detik < 86400) { return Math.floor(detik / 3600) + ' jam lalu'; }
        return Math.floor(detik / 86400) + ' hari lalu';
    }

    function barisStatus(label, nilai, kelas) {
        var $baris = $('<div class="d-flex justify-content-between align-items-start gap-2 py-1 border-bottom"></div>');
        $baris.append($('<span class="text-muted"></span>').text(label));
        $baris.append($('<span class="fw-semibold text-end"></span>').addClass(kelas || '').text(nilai));
        return $baris;
    }

    function muatStatusWebhook() {
        var $panel = $('#panelStatusWebhook').empty().append($('<div class="text-muted">Memuat status…</div>'));

        $.getJSON(urlWebhookStatus).done(function(res) {
            var d = (res && res.data) || {};
            $panel.empty();
            $panel.append(barisStatus('Provider aktif', d.provider || '-'));

            if (d.event) {
                $panel.append(barisStatus(
                    'Event gateway terakhir',
                    waktuLalu(d.event.waktu) + ' · ' + d.event.sumber + (d.event.nomor ? ' · ' + d.event.nomor : ''),
                    d.event.diproses ? 'text-success' : 'text-warning'
                ));
            } else {
                $panel.append(barisStatus('Event gateway terakhir', 'belum pernah ada', 'text-danger'));
            }

            if (d.masuk_terakhir) {
                $panel.append(barisStatus('Pesan masuk tercatat', waktuLalu(d.masuk_terakhir.waktu) + ' · ' + d.masuk_terakhir.nomor));
            } else {
                $panel.append(barisStatus('Pesan masuk tercatat', 'belum ada', 'text-danger'));
            }

            $panel.append(barisStatus('Pesan masuk hari ini', String(d.masuk_hari_ini || 0)));

            if (d.provider === 'fonnte') {
                var $perangkat = barisStatus('Perangkat Fonnte', 'memeriksa…', 'text-muted');
                $panel.append($perangkat);
                $.getJSON(urlStatusFonnte).done(function(dev) {
                    $perangkat.find('span').last()
                        .removeClass('text-muted')
                        .addClass(dev && dev.device_status === 'connect' ? 'text-success' : 'text-danger')
                        .text((dev && dev.device ? dev.device + ' · ' : '') + (dev && dev.device_status ? dev.device_status : 'tidak diketahui'));
                }).fail(function() {
                    $perangkat.find('span').last().removeClass('text-muted').addClass('text-danger').text('gagal diperiksa');
                });
            }

            $panel.children().last().removeClass('border-bottom');

            if (!d.event) {
                $panel.append($('<div class="text-muted mt-2"></div>').text('Belum ada event yang sampai ke server. Daftarkan URL webhook di atas pada dashboard gateway, lalu pastikan tunnel publiknya hidup.'));
            }
        }).fail(function() {
            $panel.empty().append($('<div class="text-danger">Gagal memuat status.</div>'));
        });
    }

    $('#modalInfoWebhook').on('shown.bs.modal', muatStatusWebhook);
    $('#btnMuatStatusWebhook').on('click', muatStatusWebhook);

    $('#btnUjiWebhook').on('click', function() {
        var $btn   = $(this).prop('disabled', true);
        var $hasil = $('#hasilUjiWebhook')
            .removeClass('d-none alert-success alert-danger')
            .addClass('alert-secondary')
            .text('Menjalankan payload uji…');

        $.ajax({ url: urlWebhookUji, method: 'POST', dataType: 'json' })
            .done(function(res) {
                $hasil.removeClass('alert-secondary')
                    .addClass(res.status === 'success' ? 'alert-success' : 'alert-danger')
                    .text(res.message || (res.status === 'success' ? 'Jalur webhook sehat.' : 'Uji gagal.'));
                muatStatusWebhook();
            })
            .fail(function(xhr) {
                var pesan = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Uji gagal dijalankan (HTTP ' + xhr.status + ').';
                $hasil.removeClass('alert-secondary').addClass('alert-danger').text(pesan);
            })
            .always(function() {
                $btn.prop('disabled', false);
            });
    });
});
</script>
<?= $this->endSection() ?>
