<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Pengaturan Gateway WhatsApp</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb p-0 m-0">
                    <li class="breadcrumb-item"><?= anchor('', 'Dasbor') ?></li>
                    <li class="breadcrumb-item"><?= anchor('admin/crm', 'CRM') ?></li>
                    <li class="breadcrumb-item active" aria-current="page">Pengaturan Gateway</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?= $this->include('admin/crm/tombol_wa') ?>
        </div>
    </div>


    <div class="row g-4">
        <!-- Form Pengaturan Provider -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fal fa-sliders-h me-2 text-primary"></i>Konfigurasi Provider &amp; API</h6>
                </div>
                <div class="card-body">
                    <div id="alertPengaturan" class="alert d-none"></div>

                    <form id="formPengaturan">
                        <div class="mb-4 p-3 bg-light rounded border">
                            <label class="form-label fw-bold">Provider WhatsApp Aktif</label>
                            <select name="wa_provider" id="wa_provider" class="form-select form-select-lg">
                                <option value="fonnte" <?= ($settings['wa_provider'] ?? '') === 'fonnte' ? 'selected' : '' ?>>Fonnte (Indonesian WhatsApp Gateway) [Aktif]</option>
                                <option value="kapso" <?= ($settings['wa_provider'] ?? '') === 'kapso' ? 'selected' : '' ?>>Kapso (Meta WhatsApp Cloud API)</option>
                            </select>
                            <div class="form-text small mt-2">
                                Anda dapat berganti provider kapan saja tanpa perlu mengubah alur pengiriman pesan aplikasi.
                            </div>
                        </div>

                        <!-- Tab/Box Fonnte -->
                        <div id="boxFonnte" class="mb-4">
                            <h6 class="fw-bold border-bottom pb-2 text-success">
                                <i class="fab fa-whatsapp me-1"></i> Pengaturan Fonnte Gateway
                            </h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Fonnte API Token</label>
                                <div class="input-group">
                                    <input type="text" name="fonnte_token" id="fonnte_token" class="form-control font-monospace" value="<?= esc($settings['fonnte_token'] ?? '') ?>" placeholder="Masukkan token perangkat Fonnte (contoh: c3a8e9f...)" required>
                                    <button class="btn btn-outline-success" type="button" id="btnCekStatusFonnte" title="Cek status koneksi perangkat ke server Fonnte">
                                        <i class="fal fa-sync-alt me-1"></i> Cek Koneksi
                                    </button>
                                </div>
                                <div class="form-text small">Dapatkan token perangkat dari dashboard Fonnte Anda di menu <em>Device</em>.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Base URL Fonnte</label>
                                <input type="text" name="fonnte_base_url" class="form-control font-monospace" value="<?= esc($settings['fonnte_base_url'] ?? 'https://api.fonnte.com') ?>">
                            </div>

                            <!-- Panel Status Perangkat Fonnte -->
                            <div class="p-3 bg-light rounded border mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small fw-bold text-dark"><i class="fal fa-mobile-android me-1"></i> Status Perangkat Fonnte</span>
                                    <span id="badgeFonnteStatus" class="badge bg-secondary">Belum Dicek</span>
                                </div>
                                <div id="infoFonnteDetail" class="small text-muted mb-2">
                                    Klik tombol <strong>Cek Koneksi</strong> untuk memverifikasi apakah nomor WhatsApp Anda terhubung ke Fonnte.
                                </div>
                                <div id="panelDetailFonnte" class="d-none small p-2 bg-white rounded border mb-2">
                                    <div class="row g-1">
                                        <div class="col-6"><strong>Nomor:</strong> <span id="fonnteDeviceNum">-</span></div>
                                        <div class="col-6"><strong>Sisa Kuota:</strong> <span id="fonnteDeviceQuota">-</span></div>
                                        <div class="col-6"><strong>Paket:</strong> <span id="fonnteDevicePackage">-</span></div>
                                        <div class="col-6"><strong>Kadaluarsa:</strong> <span id="fonnteDeviceExp">-</span></div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnBukaQrFonnte">
                                        <i class="fal fa-qrcode me-1"></i> Hubungkan / Scan QR
                                    </button>
                                    <a href="https://fonnte.com" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="fal fa-external-link me-1"></i> Buka Fonnte.com
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Tab/Box Kapso -->
                        <div id="boxKapso" class="mb-4">
                            <h6 class="fw-bold border-bottom pb-2 text-primary">
                                <i class="fab fa-whatsapp me-1"></i> Pengaturan Kapso (Meta Cloud API)
                            </h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Kapso API Key / Token</label>
                                <input type="text" name="kapso_api_key" class="form-control font-monospace" value="<?= esc($settings['kapso_api_key'] ?? '') ?>" placeholder="5959a150...">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Phone Number ID</label>
                                    <input type="text" name="kapso_phone_number_id" class="form-control font-monospace" value="<?= esc($settings['kapso_phone_number_id'] ?? '') ?>" placeholder="597907523413541">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Base URL Endpoint</label>
                                    <input type="text" name="kapso_base_url" class="form-control font-monospace" value="<?= esc($settings['kapso_base_url'] ?? 'https://api.kapso.ai') ?>" placeholder="https://api.kapso.ai">
                                </div>
                            </div>
                            <div class="p-3 bg-light rounded border mb-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" name="kapso_sandbox_mode" id="kapso_sandbox_mode" value="1" <?= ($settings['kapso_sandbox_mode'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-bold" for="kapso_sandbox_mode">
                                        Aktifkan Mode Sandbox (Pengujian)
                                    </label>
                                </div>
                                <div class="small text-muted mb-2">
                                    Gunakan mode ini untuk menghindari risiko banned pada nomor biasa. Di mode sandbox Kapso, pesan pengujian diarahkan ke nomor tester yang sudah terdaftar.
                                </div>
                                <label class="form-label small fw-bold">Nomor Tester Sandbox</label>
                                <input type="text" name="kapso_sandbox_test_number" class="form-control font-monospace" value="<?= esc($settings['kapso_sandbox_test_number'] ?? '6289674577831') ?>" placeholder="6289674577831">
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-dark btn-lg fw-bold" id="btnSimpanPengaturan">
                                <i class="fal fa-save me-1"></i> Simpan Pengaturan Gateway
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Box Tes Pengiriman / Koneksi -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm border-top border-success border-4 mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold"><i class="fab fa-whatsapp me-2 text-success"></i>Uji Coba Pengiriman Pesan</h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted" id="tesDesc">
                        Kirim pesan uji coba ke nomor WhatsApp Anda untuk memverifikasi bahwa koneksi API Fonnte telah berfungsi dengan baik.
                    </p>

                    <div id="alertTes" class="alert d-none"></div>

                    <form id="formTesKoneksi">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nomor Tujuan WhatsApp</label>
                            <input type="text" id="tesNomor" class="form-control font-monospace" value="<?= esc($settings['kapso_sandbox_test_number'] ?? '6289674577831') ?>" placeholder="08xxx atau 628xxx" required>
                            <div class="form-text small" id="tesNomorHelp">Format: 08123456789 atau 628123456789.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Pesan Uji Coba</label>
                            <textarea id="tesPesan" class="form-control font-monospace" rows="4">Halo! Ini adalah pesan pengujian koneksi WhatsApp CRM Djuragan POS via Fonnte Gateway.</textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success fw-bold" id="btnTesKirim">
                                <i class="fab fa-whatsapp me-1"></i> <span id="btnTesText">Kirim Pesan Uji Coba</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Panduan Penggunaan / Arsitektur -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold text-dark"><i class="fal fa-info-circle me-2 text-primary"></i>Catatan Pengembang &amp; Integrasi</h6>
                    <ul class="small text-muted ps-3 mb-0">
                        <li class="mb-1"><strong>Provider Fonnte:</strong> Mengirim pesan langsung via gateway WhatsApp Fonnte tanpa batasan template Meta Cloud API.</li>
                        <li class="mb-1"><strong>Modular Pattern:</strong> Panggilan API menggunakan <code>WhatsAppProviderInterface</code>, kompatibel untuk Fonnte maupun Kapso.</li>
                        <li class="mb-1"><strong>Webhook Inbound:</strong> Endpoint <code><?= site_url('webhook/whatsapp') ?></code> otomatis menangani balasan pesan masuk dari Fonnte.</li>
                        <li>Seluruh pesan yang dikirim otomatis tersimpan di <strong>Riwayat Pesan CRM</strong> dan tersinkronisasi ke <strong>Live Chat</strong>.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Scan QR Fonnte -->
<div class="modal fade" id="modalQrFonnte" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fal fa-qrcode me-2"></i>Koneksi WhatsApp Fonnte</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div id="loadingQr" class="py-3">
                    <div class="spinner-border text-success mb-2" role="status"></div>
                    <div class="text-muted small">Mengambil QR Code dari Fonnte...</div>
                </div>
                <div id="wrapperQr" class="d-none">
                    <img id="imgQrFonnte" src="" alt="QR Code" class="img-fluid border rounded shadow-sm p-2 bg-white mb-3" style="max-height: 260px;">
                    <p class="small text-muted mb-0">
                        Buka WhatsApp di ponsel Anda &gt; <strong>Perangkat Tertaut (Linked Devices)</strong> &gt; scan kode QR di atas.
                    </p>
                </div>
                <div id="errorQr" class="alert alert-danger d-none small text-start"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-success btn-sm" id="btnRefreshQr">
                    <i class="fal fa-sync-alt me-1"></i> Refresh QR
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

    var modalQr = new bootstrap.Modal(document.getElementById('modalQrFonnte'));

    // Highlight aktif box provider & sesuaikan UI
    function updateProviderHighlight() {
        var provider = $('#wa_provider').val();
        if (provider === 'fonnte') {
            $('#boxFonnte').css('opacity', '1');
            $('#boxKapso').css('opacity', '0.5');
            $('#tesDesc').text('Kirim pesan uji coba ke nomor WhatsApp Anda untuk memverifikasi bahwa koneksi API Fonnte telah berfungsi dengan baik.');
            $('#tesNomorHelp').text('Format nomor tujuan: 08xxx atau 628xxx.');
            if ($('#tesPesan').val().indexOf('Kapso') !== -1) {
                $('#tesPesan').val('Halo! Ini adalah pesan pengujian koneksi WhatsApp CRM Djuragan POS via Fonnte Gateway.');
            }
        } else {
            $('#boxKapso').css('opacity', '1');
            $('#boxFonnte').css('opacity', '0.5');
            $('#tesDesc').text('Kirim pesan uji coba ke nomor WhatsApp Anda untuk memverifikasi bahwa koneksi API ke Kapso telah berfungsi dengan baik.');
            $('#tesNomorHelp').text('Pastikan nomor ini telah diaktivasi di sandbox Kapso.');
            if ($('#tesPesan').val().indexOf('Fonnte') !== -1) {
                $('#tesPesan').val('Halo! Ini adalah pesan pengujian koneksi WhatsApp CRM Djuragan POS via Kapso Sandbox.');
            }
        }
    }
    $('#wa_provider').on('change', updateProviderHighlight);
    updateProviderHighlight();

    // Cek status koneksi Fonnte
    function cekStatusFonnte(silent) {
        var btn = $('#btnCekStatusFonnte');
        if (!silent) {
            btn.prop('disabled', true).html('<i class="fal fa-spinner fa-spin me-1"></i> Mengecek...');
        }
        $('#badgeFonnteStatus').removeClass('bg-success bg-danger bg-warning').addClass('bg-secondary').text('Mengecek...');

        $.ajax({
            url: '<?= site_url('admin/crm/cek_status_fonnte') ?>',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).html('<i class="fal fa-sync-alt me-1"></i> Cek Koneksi');

                if (res.status === true || res.device_status === 'connect') {
                    $('#badgeFonnteStatus').removeClass('bg-secondary bg-danger bg-warning').addClass('bg-success').html('<i class="fal fa-check-circle me-1"></i> Terhubung');
                    $('#panelDetailFonnte').removeClass('d-none');
                    $('#fonnteDeviceNum').text(res.device || '-');
                    $('#fonnteDeviceQuota').text((res.quota || 0) + ' pesan');
                    $('#fonnteDevicePackage').text(res.package || '-');
                    $('#fonnteDeviceExp').text(res.expired || '-');
                    $('#infoFonnteDetail').text('Perangkat WhatsApp terhubung dan siap digunakan untuk mengirim/menerima pesan.');
                } else if (res.device_status === 'disconnect') {
                    $('#badgeFonnteStatus').removeClass('bg-secondary bg-success bg-danger').addClass('bg-warning text-dark').html('<i class="fal fa-exclamation-triangle me-1"></i> Terputus (Scan QR)');
                    $('#panelDetailFonnte').addClass('d-none');
                    $('#infoFonnteDetail').text('Perangkat belum terhubung ke WhatsApp. Silakan klik tombol "Hubungkan / Scan QR" untuk menghubungkan.');
                } else {
                    var msg = res.reason || res.message || 'Token tidak valid atau belum disetel';
                    $('#badgeFonnteStatus').removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger').text('Error');
                    $('#panelDetailFonnte').addClass('d-none');
                    $('#infoFonnteDetail').text('Gagal terhubung: ' + msg);
                }
            },
            error: function() {
                btn.prop('disabled', false).html('<i class="fal fa-sync-alt me-1"></i> Cek Koneksi');
                $('#badgeFonnteStatus').removeClass('bg-secondary bg-success bg-warning').addClass('bg-danger').text('Koneksi Gagal');
                $('#infoFonnteDetail').text('Tidak dapat menghubungi server lokal untuk memeriksa status.');
            }
        });
    }

    $('#btnCekStatusFonnte').on('click', function() {
        cekStatusFonnte(false);
    });

    // Otomatis cek status jika token Fonnte sudah ada
    if ($('#fonnte_token').val().trim().length > 0) {
        cekStatusFonnte(true);
    }

    // Modal QR Fonnte
    function muatQrFonnte() {
        $('#loadingQr').removeClass('d-none');
        $('#wrapperQr').addClass('d-none');
        $('#errorQr').addClass('d-none');

        $.ajax({
            url: '<?= site_url('admin/crm/ambil_qr_fonnte') ?>',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                $('#loadingQr').addClass('d-none');
                if (res.status === true && res.url) {
                    var qrSrc = res.url;
                    if (!qrSrc.startsWith('data:') && !qrSrc.startsWith('http')) {
                        qrSrc = 'data:image/png;base64,' + qrSrc;
                    }
                    $('#imgQrFonnte').attr('src', qrSrc);
                    $('#wrapperQr').removeClass('d-none');
                } else {
                    var errMsg = res.reason || res.message || 'Gagal memuat QR Code dari Fonnte.';
                    $('#errorQr').removeClass('d-none').text(errMsg);
                }
            },
            error: function() {
                $('#loadingQr').addClass('d-none');
                $('#errorQr').removeClass('d-none').text('Gagal menghubungi server untuk mengambil QR Code.');
            }
        });
    }

    $('#btnBukaQrFonnte').on('click', function() {
        modalQr.show();
        muatQrFonnte();
    });

    $('#btnRefreshQr').on('click', function() {
        muatQrFonnte();
    });

    // Simpan Pengaturan
    $('#formPengaturan').on('submit', function(e) {
        e.preventDefault();

        var btn = $('#btnSimpanPengaturan');
        btn.prop('disabled', true).text('Menyimpan...');

        var formData = $(this).serialize();
        if (!$('#kapso_sandbox_mode').is(':checked')) {
            formData += '&kapso_sandbox_mode=0';
        }

        $.ajax({
            url: '<?= site_url('admin/crm/simpan_pengaturan') ?>',
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function(res) {
                if (res.status === 'success') {
                    $('#alertPengaturan').removeClass('d-none alert-danger').addClass('alert-success').text(res.message);
                    cekStatusFonnte(true);
                    setTimeout(function() {
                        $('#alertPengaturan').addClass('d-none');
                    }, 3500);
                } else {
                    $('#alertPengaturan').removeClass('d-none alert-success').addClass('alert-danger').text(res.message);
                }
                btn.prop('disabled', false).html('<i class="fal fa-save me-1"></i> Simpan Pengaturan Gateway');
            },
            error: function() {
                $('#alertPengaturan').removeClass('d-none alert-success').addClass('alert-danger').text('Gagal menyimpan pengaturan.');
                btn.prop('disabled', false).html('<i class="fal fa-save me-1"></i> Simpan Pengaturan Gateway');
            }
        });
    });

    // Tes Koneksi
    $('#formTesKoneksi').on('submit', function(e) {
        e.preventDefault();

        var nomor = $('#tesNomor').val().trim();
        var pesan = $('#tesPesan').val().trim();
        var btn = $('#btnTesKirim');

        if (!nomor || !pesan) {
            $('#alertTes').removeClass('d-none alert-success').addClass('alert-danger').text('Nomor dan pesan uji coba wajib diisi.');
            return;
        }

        btn.prop('disabled', true);
        $('#btnTesText').text('Mengirim Uji Coba...');

        $.ajax({
            url: '<?= site_url('admin/crm/tes_koneksi') ?>',
            type: 'POST',
            dataType: 'json',
            data: {
                nomor: nomor,
                pesan: pesan
            },
            success: function(res) {
                if (res.status === 'success') {
                    $('#alertTes').removeClass('d-none alert-danger').addClass('alert-success').text(res.message);
                } else {
                    $('#alertTes').removeClass('d-none alert-success').addClass('alert-danger').text(res.message);
                }
                btn.prop('disabled', false);
                $('#btnTesText').text('Kirim Pesan Uji Coba');
            },
            error: function() {
                $('#alertTes').removeClass('d-none alert-success').addClass('alert-danger').text('Gagal menghubungi server.');
                btn.prop('disabled', false);
                $('#btnTesText').text('Kirim Pesan Uji Coba');
            }
        });
    });
});
</script>
<?= $this->endSection() ?>
