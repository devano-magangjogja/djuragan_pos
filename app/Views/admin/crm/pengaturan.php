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
    </div>

    <?= $this->include('admin/crm/nav_crm') ?>

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
                                <option value="kapso" <?= ($settings['wa_provider'] ?? '') === 'kapso' ? 'selected' : '' ?>>Kapso (Meta WhatsApp Cloud API)</option>
                                <option value="fonnte" <?= ($settings['wa_provider'] ?? '') === 'fonnte' ? 'selected' : '' ?>>Fonnte (Indonesian WhatsApp Gateway)</option>
                            </select>
                            <div class="form-text small mt-2">
                                Anda dapat berganti provider kapan saja tanpa perlu mengubah alur pengiriman pesan aplikasi.
                            </div>
                        </div>

                        <!-- Tab/Box Kapso -->
                        <div id="boxKapso" class="mb-4">
                            <h6 class="fw-bold border-bottom pb-2 text-primary">
                                <i class="fab fa-whatsapp me-1"></i> Pengaturan Kapso
                            </h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Kapso API Key / Token</label>
                                <input type="text" name="kapso_api_key" class="form-control font-monospace" value="<?= esc($settings['kapso_api_key'] ?? '') ?>" placeholder="5959a150..." required>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Phone Number ID</label>
                                    <input type="text" name="kapso_phone_number_id" class="form-control font-monospace" value="<?= esc($settings['kapso_phone_number_id'] ?? '') ?>" placeholder="597907523413541" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Base URL Endpoint</label>
                                    <input type="text" name="kapso_base_url" class="form-control font-monospace" value="<?= esc($settings['kapso_base_url'] ?? 'https://api.kapso.ai') ?>" placeholder="https://api.kapso.ai" required>
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

                        <!-- Tab/Box Fonnte -->
                        <div id="boxFonnte" class="mb-4">
                            <h6 class="fw-bold border-bottom pb-2 text-success">
                                <i class="fal fa-plug me-1"></i> Pengaturan Fonnte (Alternatif)
                            </h6>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Fonnte API Token</label>
                                <input type="text" name="fonnte_token" class="form-control font-monospace" value="<?= esc($settings['fonnte_token'] ?? '') ?>" placeholder="Masukkan token Fonnte jika beralih ke Fonnte">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Base URL Fonnte</label>
                                <input type="text" name="fonnte_base_url" class="form-control font-monospace" value="<?= esc($settings['fonnte_base_url'] ?? 'https://api.fonnte.com') ?>">
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
                    <p class="small text-muted">
                        Kirim pesan uji coba ke nomor WhatsApp Anda untuk memverifikasi bahwa koneksi API ke Kapso atau Fonnte telah berfungsi dengan baik.
                    </p>

                    <div id="alertTes" class="alert d-none"></div>

                    <form id="formTesKoneksi">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nomor Tujuan WhatsApp</label>
                            <input type="text" id="tesNomor" class="form-control font-monospace" value="<?= esc($settings['kapso_sandbox_test_number'] ?? '6285161384750') ?>" placeholder="628xxx" required>
                            <div class="form-text small">Pastikan nomor ini telah diaktivasi di sandbox Kapso.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Pesan Uji Coba</label>
                            <textarea id="tesPesan" class="form-control font-monospace" rows="4">Halo! Ini adalah pesan pengujian koneksi WhatsApp CRM Djuragan POS via Kapso Sandbox.</textarea>
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
                    <h6 class="fw-bold text-dark"><i class="fal fa-info-circle me-2 text-primary"></i>Catatan Pengembang</h6>
                    <ul class="small text-muted ps-3 mb-0">
                        <li class="mb-1"><strong>Modular Pattern:</strong> Panggilan API menggunakan <code>WhatsAppProviderInterface</code>.</li>
                        <li class="mb-1"><strong>Sandbox Safe:</strong> Pesan pengujian dialihkan secara aman tanpa risiko spamming ke nomor pelanggan asli.</li>
                        <li class="mb-1"><strong>Maintenance Friendly:</strong> Jika beralih ke Fonnte, cukup isi token Fonnte dan ubah opsi provider aktif di form sebelah.</li>
                        <li>Seluruh pesan yang dikirim otomatis tercatat di <strong>Riwayat Pesan</strong>.</li>
                    </ul>
                </div>
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

    // Highlight aktif box provider
    function updateProviderHighlight() {
        var provider = $('#wa_provider').val();
        if (provider === 'kapso') {
            $('#boxKapso').css('opacity', '1');
            $('#boxFonnte').css('opacity', '0.6');
        } else {
            $('#boxKapso').css('opacity', '0.6');
            $('#boxFonnte').css('opacity', '1');
        }
    }
    $('#wa_provider').on('change', updateProviderHighlight);
    updateProviderHighlight();

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
