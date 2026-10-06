<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
.chat-container {
    height: calc(100vh - 220px);
    min-height: 580px;
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
    background: #efeae2;
    background-image: radial-gradient(#d1d7db 1px, transparent 1px);
    background-size: 20px 20px;
}
.chat-header {
    background: #ffffff;
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 20px;
}
.chat-body {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.chat-bubble {
    max-width: 65%;
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 0.92rem;
    line-height: 1.45;
    position: relative;
    word-wrap: break-word;
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
.chat-footer {
    background: #ffffff;
    border-top: 1px solid #e2e8f0;
    padding: 12px 18px;
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
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Live WhatsApp Chat</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb p-0 m-0">
                    <li class="breadcrumb-item"><?= anchor('', 'Dasbor') ?></li>
                    <li class="breadcrumb-item"><?= anchor('admin/crm', 'CRM') ?></li>
                    <li class="breadcrumb-item active" aria-current="page">Live Chat</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#modalInfoWebhook">
                <i class="fal fa-webhook me-1"></i> Webhook URL
            </button>
            <?php if (!empty($isSandbox)) : ?>
                <span class="badge bg-warning text-dark py-2 px-3">
                    <i class="fal fa-flask me-1"></i> Sandbox Mode
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?= $this->include('admin/crm/nav_crm') ?>

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
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="fal fa-search text-muted"></i></span>
                    <input type="text" id="searchConv" class="form-control" placeholder="Cari kontak / nomor...">
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
                <div class="d-flex align-items-center">
                    <div class="avatar-circle me-3" id="activeAvatar">U</div>
                    <div>
                        <h6 class="mb-0 fw-bold" id="activeName">-</h6>
                        <small class="text-muted font-monospace" id="activePhone">-</small>
                    </div>
                </div>
                <div id="customerBadge"></div>
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
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Pilih Template Cepat">
                            <i class="fal fa-bolt"></i> Template
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
                    <textarea id="chatInput" class="form-control" rows="1" placeholder="Ketik pesan WhatsApp... (Tekan Enter untuk kirim, Shift+Enter untuk baris baru)" style="resize: none;"></textarea>
                    <button class="btn btn-success px-4" id="btnKirimPesan">
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
                    <input type="text" id="newChatNomor" class="form-control font-monospace" placeholder="628123456789 atau 08123456789" value="<?= (!empty($isSandbox) && !empty($sandboxNumber)) ? esc($sandboxNumber) : '' ?>">
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
                <h5 class="modal-title"><i class="fal fa-webhook me-2"></i>URL Webhook WhatsApp Inbound</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    Untuk menerima balasan chat dari pelanggan ke website ini secara real-time, daftarkan URL berikut di dashboard <strong>Kapso &gt; Integrations &gt; Webhooks</strong>:
                </p>
                <div class="input-group mb-3">
                    <input type="text" class="form-control font-monospace bg-light" value="<?= site_url('webhook/whatsapp') ?>" readonly id="copyWebhookInput">
                    <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText($('#copyWebhookInput').val()); alert('URL Webhook disalin!');">
                        <i class="fal fa-copy"></i> Salin
                    </button>
                </div>
                <div class="alert alert-info small py-2 mb-0">
                    <i class="fal fa-check-circle me-1"></i> Endpoint ini sudah dibuka tanpa blokir CSRF sehingga siap menerima event <code>whatsapp.message.received</code>.
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
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<script>
$(function() {
    'use strict';

    var currentConvId   = null;
    var lastMsgId       = 0;     // ID pesan terakhir yang sudah dimuat
    var pollInterval    = null;
    var modalChatBaru   = new bootstrap.Modal(document.getElementById('modalChatBaru'));
    var isAtBottom      = true;  // Lacak apakah user scroll ke bawah

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
            return;
        }

        var html = '';
        list.forEach(function(item) {
            var activeClass  = (keepActive && currentConvId === item.id_percakapan) ? ' active' : '';
            var initial      = (item.nama_kontak || 'U').charAt(0).toUpperCase();
            var unreadBadge  = item.unread_admin > 0
                ? '<span class="badge bg-success rounded-pill ms-1 blink-badge">Baru</span>'
                : '';
            var prefixArah   = item.arah_terakhir === 'keluar'
                ? '<i class="fal fa-check text-muted me-1"></i>'
                : '<i class="fal fa-arrow-down-left text-success me-1"></i>';

            html += '<div class="chat-conv-item p-3 d-flex align-items-center' + activeClass + '"'
                  + ' data-id="' + item.id_percakapan + '"'
                  + ' data-nomor="' + escapeAttr(item.nomor_wa) + '"'
                  + ' data-nama="' + escapeAttr(item.nama_kontak) + '">';
            html += '  <div class="avatar-circle me-3 flex-shrink-0">' + initial + '</div>';
            html += '  <div class="flex-grow-1 min-w-0 me-2">';
            html += '    <div class="d-flex justify-content-between align-items-baseline mb-1">';
            html += '      <strong class="text-truncate text-dark">' + escapeHtml(item.nama_kontak) + '</strong>';
            html += '      <small class="text-muted font-monospace">' + item.waktu + '</small>';
            html += '    </div>';
            html += '    <div class="small text-muted text-truncate d-flex align-items-center">';
            html += '      ' + prefixArah + escapeHtml(item.pesan_terakhir || 'Mulai percakapan...');
            html += '    </div>';
            html += '  </div>';
            html += '  <div>' + unreadBadge + '</div>';
            html += '</div>';
        });

        container.html(html);
    }

    // ── Klik percakapan ─────────────────────────────────────────────────────
    $(document).on('click', '.chat-conv-item', function() {
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

    function openConversation(id, nama, nomor) {
        $('#activeName').text(nama);
        $('#activePhone').text(nomor);
        $('#activeAvatar').text((nama || 'U').charAt(0).toUpperCase());
        $('#chatHeader').attr('style', 'display: flex !important;');
        $('#chatFooter').show();
        $('#newMsgNotif').hide();

        // Full load pertama kali
        loadMessagesFull(id);
    }

    // ── Load full (pertama buka percakapan) ─────────────────────────────────
    function loadMessagesFull(id) {
        if (!id) return;
        $.getJSON('<?= site_url('admin/crm/chat/pesan/') ?>' + id, function(res) {
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
        });
    }

    // ── Incremental polling: append pesan baru saja ──────────────────────────
    function pollNewMessages() {
        if (!currentConvId) return;

        var url = '<?= site_url('admin/crm/chat/pesan/') ?>' + currentConvId + '?last_id=' + lastMsgId;
        $.getJSON(url, function(res) {
            if (res.status === 'success' && res.messages && res.messages.length > 0) {
                appendMessages(res.messages);
                lastMsgId = res.messages[res.messages.length - 1].id_pesan;
                // Refresh sidebar untuk update pesan terakhir + badge
                loadConversations(true);
            }
        });
    }

    // ── Render semua pesan (full load) ──────────────────────────────────────
    function renderMessages(messages, scrollToBottom) {
        var container = $('#chatMessages');
        if (messages.length === 0) {
            container.html('<div class="text-center text-muted my-auto"><i class="fal fa-comment-dots fa-2x mb-2 d-block"></i>Belum ada pesan. Kirim pesan pertama Anda!</div>');
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
        var html = '';

        messages.forEach(function(m) {
            html += buildMessageHtml(m, lastDate);
            lastDate = m.tanggal;
        });

        container.append(html);

        // Cek apakah ada pesan masuk (dari pelanggan) — kalau ya, scroll ke bawah + notif
        var hasInbound = messages.some(function(m) { return m.arah === 'masuk'; });

        // Jika user sudah di posisi bawah ATAU ada pesan masuk baru, scroll
        checkIsAtBottom();
        if (isAtBottom || hasInbound) {
            scrollBottom();
        } else if (hasInbound) {
            // Tampilkan notifikasi ada pesan baru
            showNewMsgNotif(messages.length);
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

        var checkIcon = m.arah === 'keluar'
            ? ' <i class="fal fa-check-double text-primary ms-1"></i>'
            : '';

        html += '<div class="chat-bubble ' + m.arah + '" data-id="' + m.id_pesan + '">';
        html += '  <div>' + escapeHtml(m.isi_pesan) + '</div>';
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

            var html = '<div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">';
            html += '<span class="' + stBadge + '" style="font-size:0.75rem;"><i class="fal fa-check-circle me-1"></i>' + stText + '</span>';
            html += '<span class="badge bg-light text-dark border d-none d-md-inline" style="font-size:0.75rem;">'
                  + '<i class="fal fa-shopping-bag me-1 text-primary"></i>' + totalOrder + ' Order | Rp ' + totalBelanja
                  + (Number(pelanggan.sisa || 0) > 0 ? ' | <span class="text-danger">Sisa: Rp ' + sisaPiutang + '</span>' : '')
                  + '</span>';
            html += '<a href="<?= site_url('admin/crm/pelanggan/') ?>' + pelanggan.id_pelanggan + '" target="_blank" class="btn btn-sm btn-primary py-1 px-2 fw-semibold" style="font-size:0.8rem;">'
                  + '<i class="fal fa-id-card me-1"></i> Profil 360°'
                  + '</a>';
            html += '</div>';
            $badge.html(html);
        } else {
            var num = currentNumber || $('#activePhone').text() || '';
            var html = '<div class="d-flex align-items-center gap-2">';
            html += '<span class="badge bg-light text-muted border"><i class="fal fa-user-times me-1 text-warning"></i> Belum Terdaftar</span>';
            html += '<button type="button" class="btn btn-sm btn-outline-primary py-1 px-2 fw-semibold" id="btnJadikanCustomer" data-nomor="' + escapeAttr(num) + '" style="font-size:0.8rem;">'
                  + '<i class="fal fa-user-plus me-1"></i> + Buat Customer'
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
                // Hapus bubble sementara, reload pesan dari server
                $('.sending-bubble').remove();
                if (res.status === 'success') {
                    pollNewMessages();
                    loadConversations(true);
                } else {
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
    $(document).on('click', '.btn-insert-tpl', function(e) {
        e.preventDefault();
        $('#chatInput').val($(this).data('text')).focus();
    });

    // ── Chat Baru ────────────────────────────────────────────────────────────
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
});
</script>
<?= $this->endSection() ?>
