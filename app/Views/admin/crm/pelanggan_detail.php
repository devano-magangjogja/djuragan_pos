<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <!-- Breadcrumb & Back -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <a href="<?= site_url('admin/crm/pelanggan') ?>" class="text-decoration-none text-muted small">
                <i class="fal fa-arrow-left me-1"></i> Kembali ke Data Pelanggan
            </a>
            <h4 class="mb-0 fw-bold mt-1">
                <i class="fal fa-id-card text-primary me-2"></i> Profil Customer 360
            </h4>
        </div>
        <div class="d-flex gap-2">
            <?php if (!empty($pelanggan['nomor_utama'])): ?>
                <a href="<?= site_url('admin/crm/chat') ?>?nomor=<?= esc($pelanggan['nomor_utama']) ?>" class="btn btn-outline-success btn-sm fw-semibold">
                    <i class="fab fa-whatsapp me-1"></i> Buka di Live Chat
                </a>
            <?php endif; ?>
            <button class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalKirimWaDirect">
                <i class="fal fa-paper-plane me-1"></i> Kirim WhatsApp
            </button>
        </div>
    </div>

    <?php if (session()->getFlashdata('pesan')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fal fa-check-circle me-2"></i><?= session()->getFlashdata('pesan') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fal fa-exclamation-triangle me-2"></i><?= session()->getFlashdata('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Header Kartu Utama Customer -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center">
                <!-- Avatar & Identitas Pokok -->
                <div class="col-lg-5 col-md-6 mb-3 mb-lg-0 border-end">
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle bg-primary bg-opacity-10 text-primary fw-bold fs-3 rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width: 70px; height: 70px;">
                            <?= strtoupper(substr($pelanggan['nama_pelanggan'], 0, 2)) ?>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <h4 class="mb-0 fw-bold"><?= esc($pelanggan['nama_pelanggan']) ?></h4>
                                <?php
                                $statusBadge = match ($pelanggan['status_crm']) {
                                    'loyal'       => ['badge bg-warning text-dark', 'Customer Loyal', 'fa-crown'],
                                    'aktif'       => ['badge bg-success', 'Customer Aktif', 'fa-check-circle'],
                                    'baru'        => ['badge bg-info text-dark', 'Customer Baru', 'fa-sparkles'],
                                    'tidak_aktif' => ['badge bg-secondary', 'Tidak Aktif', 'fa-moon'],
                                    default       => ['badge bg-light text-dark border', 'Prospek / Lead', 'fa-user-clock'],
                                };
                                ?>
                                <span class="<?= $statusBadge[0] ?> px-2 py-1">
                                    <i class="fal <?= $statusBadge[2] ?> me-1"></i> <?= $statusBadge[1] ?>
                                </span>
                            </div>
                            <div class="text-muted mt-1 small">
                                <span class="me-3">
                                    <i class="fab fa-whatsapp text-success me-1"></i>
                                    <strong><?= esc($pelanggan['nomor_utama'] ?: 'Belum ada nomor') ?></strong>
                                </span>
                                <span>
                                    <i class="fal fa-map-marker-alt text-danger me-1"></i>
                                    <?= esc($pelanggan['kota'] ?: 'Kota belum diset') ?>
                                    <?= !empty($pelanggan['provinsi']) ? ', ' . esc($pelanggan['provinsi']) : '' ?>
                                </span>
                            </div>
                            <div class="text-muted small mt-1">
                                <i class="fal fa-calendar-alt me-1"></i> Terdaftar sejak: <?= !empty($pelanggan['terdaftar_sejak']) ? date('d M Y', (int) $pelanggan['terdaftar_sejak']) : '-' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status CRM, Tag, & Opt-In Control -->
                <div class="col-lg-7 col-md-6 ps-lg-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                        <!-- Tag Customer -->
                        <div>
                            <span class="text-muted small fw-semibold d-block mb-1">Tag Customer:</span>
                            <div class="d-flex flex-wrap align-items-center gap-1">
                                <?php if (!empty($pelanggan['tags'])): ?>
                                    <?php foreach ($pelanggan['tags'] as $t): ?>
                                        <span class="badge bg-<?= esc($t['warna']) ?> d-inline-flex align-items-center py-1 px-2">
                                            <?= esc($t['nama_tag']) ?>
                                            <form action="<?= site_url('admin/crm/pelanggan/hapus_tag/' . $pelanggan['id_pelanggan'] . '/' . $t['id_tag']) ?>" method="post" class="d-inline ms-1" onsubmit="return confirm('Hapus tag ini?');">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-link btn-xs text-white p-0 text-decoration-none" style="font-size: 10px; line-height: 1;">&times;</button>
                                            </form>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">Belum ada tag.</span>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 11px;" data-bs-toggle="modal" data-bs-target="#modalTambahTag">
                                    <i class="fal fa-plus me-1"></i> Tambah Tag
                                </button>
                            </div>
                        </div>

                        <!-- Status CRM Edit & Opt-in -->
                        <div class="text-end">
                            <form action="<?= site_url('admin/crm/pelanggan/simpan_profil/' . $pelanggan['id_pelanggan']) ?>" method="post" class="d-flex align-items-center gap-2">
                                <?= csrf_field() ?>
                                <div class="form-check form-switch text-start me-2" title="Izin menerima broadcast promo">
                                    <input class="form-check-input" type="checkbox" name="opt_in_broadcast" value="1" id="optInSwitch" <?= ($pelanggan['opt_in_broadcast'] ?? 1) == 1 ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <label class="form-check-label small" for="optInSwitch">
                                        <?= ($pelanggan['opt_in_broadcast'] ?? 1) == 1 ? '<span class="text-success fw-semibold">Opt-in Promo</span>' : '<span class="text-danger fw-semibold">Opt-out Promo</span>' ?>
                                    </label>
                                </div>
                                <select name="status_crm" class="form-select form-select-sm" onchange="this.form.submit()" style="width: 140px;">
                                    <option value="prospek" <?= $pelanggan['status_crm'] === 'prospek' ? 'selected' : '' ?>>Prospek / Lead</option>
                                    <option value="baru" <?= $pelanggan['status_crm'] === 'baru' ? 'selected' : '' ?>>Customer Baru</option>
                                    <option value="aktif" <?= $pelanggan['status_crm'] === 'aktif' ? 'selected' : '' ?>>Customer Aktif</option>
                                    <option value="loyal" <?= $pelanggan['status_crm'] === 'loyal' ? 'selected' : '' ?>>Customer Loyal</option>
                                    <option value="tidak_aktif" <?= $pelanggan['status_crm'] === 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                                </select>
                            </form>
                        </div>
                    </div>

                    <!-- Alamat Lengkap & Catatan Cepat -->
                    <div class="bg-light p-2 rounded small mt-2">
                        <div class="row">
                            <div class="col-sm-6">
                                <strong>Alamat:</strong> <?= esc($pelanggan['alamat'] ?: 'Belum ada alamat lengkap') ?>
                            </div>
                            <div class="col-sm-6 text-sm-end text-muted">
                                <strong>Transaksi Terakhir:</strong> 
                                <?= !empty($nilai['transaksi_terakhir']) ? date('d M Y', strtotime($nilai['transaksi_terakhir'])) : 'Belum pernah order' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Ringkasan Nilai Customer (Customer Value Metrics) -->
    <div class="row g-3 mb-4">
        <!-- Total Transaksi -->
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold">Total Transaksi</span>
                    <h3 class="fw-bold mb-0 text-primary mt-1"><?= number_format($nilai['total_transaksi']) ?></h3>
                    <div class="text-muted small" style="font-size: 11px;">orderan di POS</div>
                </div>
            </div>
        </div>
        <!-- Total Pengeluaran -->
        <div class="col-xl-3 col-md-4 col-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold">Total Pengeluaran</span>
                    <h3 class="fw-bold mb-0 text-success mt-1">Rp <?= number_format($nilai['total_pengeluaran'], 0, ',', '.') ?></h3>
                    <div class="text-muted small" style="font-size: 11px;">akumulasi nilai belanja</div>
                </div>
            </div>
        </div>
        <!-- Rata-rata Nilai Transaksi -->
        <div class="col-xl-2 col-md-4 col-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold">Rata-rata Transaksi</span>
                    <h4 class="fw-bold mb-0 text-dark mt-1">Rp <?= number_format($nilai['rata_rata_nilai'], 0, ',', '.') ?></h4>
                    <div class="text-muted small" style="font-size: 11px;">per transaksi</div>
                </div>
            </div>
        </div>
        <!-- Breakdown Jenis Order -->
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold d-block mb-1">Rincian Jenis Order</span>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <span class="badge bg-primary px-2 py-1" title="Penjualan Ready Stock">
                            <i class="fal fa-shopping-bag me-1"></i> Jual: <?= $nilai['count_penjualan'] ?>
                        </span>
                        <span class="badge bg-success px-2 py-1" title="Custom / Pembuatan Pakaian">
                            <i class="fal fa-scissors me-1"></i> Custom: <?= $nilai['count_custom'] ?>
                        </span>
                        <span class="badge bg-info px-2 py-1" title="Sewa / Rental Pakaian">
                            <i class="fal fa-sync me-1"></i> Sewa: <?= $nilai['count_sewa'] ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <!-- Sisa Piutang / Tagihan -->
        <div class="col-xl-2 col-md-6 col-12">
            <div class="card border-0 shadow-sm h-100 <?= $nilai['sisa_piutang'] > 0 ? 'border-danger border-start border-4' : '' ?>">
                <div class="card-body p-3">
                    <span class="text-muted small fw-semibold">Sisa Tagihan / Piutang</span>
                    <h4 class="fw-bold mb-0 <?= $nilai['sisa_piutang'] > 0 ? 'text-danger' : 'text-muted' ?> mt-1">
                        Rp <?= number_format($nilai['sisa_piutang'], 0, ',', '.') ?>
                    </h4>
                    <div class="small <?= $nilai['sisa_piutang'] > 0 ? 'text-danger fw-semibold' : 'text-success' ?>" style="font-size: 11px;">
                        <?= $nilai['sisa_piutang'] > 0 ? 'Belum lunas' : 'Semua transaksi lunas' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navigation Tabs Customer 360 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom p-0">
            <ul class="nav nav-tabs card-header-tabs m-0 px-3" id="customer360Tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active py-3 px-4 fw-semibold" id="tab-timeline-btn" data-bs-toggle="tab" data-bs-target="#tab-timeline" type="button">
                        <i class="fal fa-stream me-2 text-primary"></i> Timeline Aktivitas
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-4 fw-semibold" id="tab-transaksi-btn" data-bs-toggle="tab" data-bs-target="#tab-transaksi" type="button">
                        <i class="fal fa-file-invoice-dollar me-2 text-success"></i> Riwayat Transaksi POS
                        <span class="badge bg-light text-dark border ms-1"><?= count($transaksiList) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-4 fw-semibold" id="tab-pembayaran-btn" data-bs-toggle="tab" data-bs-target="#tab-pembayaran" type="button">
                        <i class="fal fa-money-check-alt me-2 text-info"></i> Pembayaran
                        <span class="badge bg-light text-dark border ms-1"><?= count($pembayaranList) ?></span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-4 fw-semibold" id="tab-ukuran-btn" data-bs-toggle="tab" data-bs-target="#tab-ukuran" type="button">
                        <i class="fal fa-tape me-2 text-warning"></i> Ukuran & Preferensi Pakaian
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-4 fw-semibold" id="tab-catatan-btn" data-bs-toggle="tab" data-bs-target="#tab-catatan" type="button">
                        <i class="fal fa-comment-alt-lines me-2 text-secondary"></i> Catatan Staff
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link py-3 px-4 fw-semibold" id="tab-followup-btn" data-bs-toggle="tab" data-bs-target="#tab-followup" type="button">
                        <i class="fal fa-calendar-check me-2 text-danger"></i> Follow-up
                        <span class="badge bg-light text-dark border ms-1"><?= count($followupList) ?></span>
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="customer360Content">
                
                <!-- ================= TAB 1: TIMELINE AKTIVITAS ================= -->
                <div class="tab-pane fade show active" id="tab-timeline" role="tabpanel">
                    <div class="row">
                        <!-- Timeline feed -->
                        <div class="col-lg-8 border-end pe-lg-4">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <h6 class="fw-bold mb-0">Urutan Aktivitas & Interaksi Customer</h6>
                                <span class="badge bg-light text-muted border">Multi-channel (WA, Order, Fitting, Kasir)</span>
                            </div>

                            <?php if (empty($timeline)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fal fa-history fa-3x mb-3 text-secondary opacity-50"></i>
                                    <p class="mb-0">Belum ada aktivitas yang tercatat untuk customer ini.</p>
                                </div>
                            <?php else: ?>
                                <div class="timeline-container position-relative ps-4" style="border-left: 2px solid #e9ecef;">
                                    <?php foreach ($timeline as $item): ?>
                                        <div class="timeline-item mb-4 position-relative">
                                            <!-- Bullet icon -->
                                            <div class="position-absolute rounded-circle d-flex align-items-center justify-content-center bg-<?= esc($item['warna']) ?> text-white shadow-sm" 
                                                 style="left: -33px; top: 0; width: 30px; height: 30px; font-size: 13px;">
                                                <i class="fal <?= esc($item['icon']) ?>"></i>
                                            </div>
                                            <div class="card border bg-light bg-opacity-50 shadow-none">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <h6 class="fw-bold mb-0 text-dark"><?= esc($item['judul']) ?></h6>
                                                        <span class="text-muted small" style="font-size: 11px;">
                                                            <?= date('d M Y, H:i', (int) $item['waktu']) ?>
                                                        </span>
                                                    </div>
                                                    <p class="mb-1 text-secondary small" style="white-space: pre-wrap;"><?= esc($item['deskripsi']) ?></p>
                                                    <div class="text-muted small" style="font-size: 11px;">
                                                        <i class="fal fa-user-edit me-1"></i> Dicatat oleh: <strong><?= esc($item['actor']) ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Panel Tambah Aktivitas Manual -->
                        <div class="col-lg-4 ps-lg-4 mt-4 mt-lg-0">
                            <div class="card border bg-white shadow-sm">
                                <div class="card-header bg-white py-3 border-bottom">
                                    <h6 class="fw-bold mb-0">
                                        <i class="fal fa-plus-circle text-primary me-1"></i> Catat Interaksi Baru
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form action="<?= site_url('admin/crm/pelanggan/tambah_aktivitas/' . $pelanggan['id_pelanggan']) ?>" method="post">
                                        <?= csrf_field() ?>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Jenis Interaksi / Aktivitas</label>
                                            <select name="tipe" class="form-select form-select-sm" required>
                                                <option value="catatan">Catatan Staff</option>
                                                <option value="fitting">Fitting Pakaian</option>
                                                <option value="telepon">Panggilan Telepon</option>
                                                <option value="datang">Datang ke Toko</option>
                                                <option value="pengambilan">Pengambilan Pakaian</option>
                                                <option value="pengembalian">Pengembalian Pakaian Sewa</option>
                                                <option value="wa">Interaksi WhatsApp</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Judul Ringkas</label>
                                            <input type="text" name="judul" class="form-control form-control-sm" placeholder="Contoh: Customer fitting jas kedua" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Keterangan / Hasil Interaksi</label>
                                            <textarea name="deskripsi" class="form-control form-control-sm" rows="4" placeholder="Tuliskan catatan hasil interaksi dengan customer..."></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Hubungkan dengan Invoice (Opsional)</label>
                                            <select name="invoice_id" class="form-select form-select-sm">
                                                <option value="">-- Tanpa Invoice Terkait --</option>
                                                <?php foreach ($transaksiList as $inv): ?>
                                                    <option value="<?= $inv['id_invoice'] ?>">#<?= esc($inv['seri']) ?> (<?= esc($inv['tipe_label']) ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">
                                            <i class="fal fa-save me-1"></i> Simpan ke Timeline
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 2: RIWAYAT TRANSAKSI POS ================= -->
                <div class="tab-pane fade" id="tab-transaksi" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0">Seluruh Transaksi POS Customer</h6>
                        <span class="text-muted small">Menampilkan semua pembelian pakaian, pesanan custom, dan sewa</span>
                    </div>

                    <?php if (empty($transaksiList)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-receipt fa-3x mb-3 text-secondary opacity-50"></i>
                            <p class="mb-0">Belum ada transaksi POS yang terhubung dengan customer ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead class="table-light">
                                    <tr>
                                        <th>Invoice</th>
                                        <th>Tanggal</th>
                                        <th>Jenis Pesanan</th>
                                        <th>Item Pakaian / Layanan</th>
                                        <th class="text-end">Total Tagihan</th>
                                        <th class="text-end">Dibayar</th>
                                        <th class="text-end">Sisa</th>
                                        <th>Status Pembayaran</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transaksiList as $t): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold text-dark">#<?= esc($t['seri']) ?></span>
                                                <div class="small text-muted"><?= esc($t['nama_juragan']) ?></div>
                                            </td>
                                            <td class="small">
                                                <?= date('d/m/Y', strtotime($t['tanggal_pesan'])) ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= esc($t['tipe_badge']) ?> px-2 py-1">
                                                    <?= esc($t['tipe_label']) ?>
                                                </span>
                                                <?php if (!empty($t['rincian_data']['deadline'])): ?>
                                                    <div class="text-muted" style="font-size: 11px;">
                                                        Deadline: <?= date('d/m/y', strtotime($t['rincian_data']['deadline'])) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($t['rincian_data']['ambil'])): ?>
                                                    <div class="text-muted" style="font-size: 11px;">
                                                        Sewa: <?= date('d/m', strtotime($t['rincian_data']['ambil'])) ?> - <?= date('d/m/y', strtotime($t['rincian_data']['kembali'] ?? '')) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($t['items'])): ?>
                                                    <ul class="list-unstyled mb-0 small">
                                                        <?php foreach ($t['items'] as $item): ?>
                                                            <li>
                                                                <i class="fal fa-tshirt text-muted me-1"></i>
                                                                <strong><?= esc($item['kode']) ?></strong> 
                                                                <?= !empty($item['ukuran']) ? '(' . esc($item['ukuran']) . ')' : '' ?> 
                                                                &times; <?= (int) $item['qty'] ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <span class="text-muted small fst-italic">Tanpa rincian item</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end fw-semibold">
                                                Rp <?= number_format($t['total_tagihan'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-end text-success">
                                                Rp <?= number_format($t['telah_dibayar'], 0, ',', '.') ?>
                                            </td>
                                            <td class="text-end <?= $t['sisa_tagihan'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                                Rp <?= number_format($t['sisa_tagihan'], 0, ',', '.') ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusBayar = match ($t['status_pembayaran']) {
                                                    '6' => ['badge bg-success', 'Lunas'],
                                                    '4' => ['badge bg-warning text-dark', 'Kredit / Cicilan'],
                                                    '5' => ['badge bg-info', 'Kelebihan'],
                                                    '2', '3' => ['badge bg-warning text-dark', 'Konfirmasi'],
                                                    default => ['badge bg-danger', 'Belum Bayar'],
                                                };
                                                ?>
                                                <span class="<?= $statusBayar[0] ?>"><?= $statusBayar[1] ?></span>
                                            </td>
                                            <td class="text-center">
                                                <a href="<?= site_url('admin/invoice/lihat/' . $t['id_invoice']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-1 px-2" title="Buka Detail Invoice POS">
                                                    <i class="fal fa-external-link-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ================= TAB 3: RIWAYAT PEMBAYARAN ================= -->
                <div class="tab-pane fade" id="tab-pembayaran" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0">Riwayat Mutasi Pembayaran Masuk</h6>
                    </div>

                    <?php if (empty($pembayaranList)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-money-bill-wave fa-3x mb-3 text-secondary opacity-50"></i>
                            <p class="mb-0">Belum ada riwayat pembayaran yang tercatat.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Tanggal Bayar</th>
                                        <th>Terkait Invoice</th>
                                        <th>Metode / Rekening Bank</th>
                                        <th class="text-end">Nominal Pembayaran</th>
                                        <th>Status Verifikasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pembayaranList as $p): ?>
                                        <tr>
                                            <td class="text-muted small">#PAY-<?= $p['id_pembayaran'] ?></td>
                                            <td><?= date('d/m/Y H:i', (int) $p['tanggal_pembayaran']) ?></td>
                                            <td>
                                                <span class="fw-bold">#<?= esc($p['invoice_seri']) ?></span>
                                            </td>
                                            <td>
                                                <i class="fal fa-university me-1 text-muted"></i>
                                                <?= esc($p['nama_bank']) ?>
                                                <?= !empty($p['atas_nama']) ? 'a.n ' . esc($p['atas_nama']) : '' ?>
                                            </td>
                                            <td class="text-end fw-bold text-success">
                                                Rp <?= number_format($p['total_pembayaran'], 0, ',', '.') ?>
                                            </td>
                                            <td>
                                                <?php if ($p['status'] === '3'): ?>
                                                    <span class="badge bg-success"><i class="fal fa-check me-1"></i> Terverifikasi (Dana Ada)</span>
                                                <?php elseif ($p['status'] === '2'): ?>
                                                    <span class="badge bg-danger">Dana Tidak Ada</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark">Belum Dicek</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ================= TAB 4: DATA UKURAN & PREFERENSI PAKAIAN ================= -->
                <div class="tab-pane fade" id="tab-ukuran" role="tabpanel">
                    <form action="<?= site_url('admin/crm/pelanggan/simpan_ukuran/' . $pelanggan['id_pelanggan']) ?>" method="post">
                        <?= csrf_field() ?>
                        <div class="row">
                            <!-- Preferensi & Ukuran Standar -->
                            <div class="col-lg-4 border-end pe-lg-4">
                                <h6 class="fw-bold mb-3 text-primary">
                                    <i class="fal fa-sliders-h me-1"></i> Ukuran Standar & Preferensi
                                </h6>
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Ukuran Baju / Kemeja Standar</label>
                                    <input type="text" name="ukuran_baju_standar" class="form-control" value="<?= esc($pelanggan['ukuran_baju_standar'] ?? '') ?>" placeholder="Misal: XL / L / Custom">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Ukuran Celana Standar</label>
                                    <input type="text" name="ukuran_celana_standar" class="form-control" value="<?= esc($pelanggan['ukuran_celana_standar'] ?? '') ?>" placeholder="Misal: 33 / 34">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Ukuran Jas Standar</label>
                                    <input type="text" name="ukuran_jas_standar" class="form-control" value="<?= esc($pelanggan['ukuran_jas_standar'] ?? '') ?>" placeholder="Misal: 50 / L Slim Fit">
                                </div>

                                <hr>

                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Warna Favorit / Sering Dipilih</label>
                                    <input type="text" name="preferensi_warna" class="form-control" value="<?= esc($pelanggan['preferensi_warna'] ?? '') ?>" placeholder="Misal: Navy, Hitam, Earth Tone">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Bahan Kain yang Disukai</label>
                                    <input type="text" name="preferensi_bahan" class="form-control" value="<?= esc($pelanggan['preferensi_bahan'] ?? '') ?>" placeholder="Misal: Semi Wool, Katun Oxford, Toyobo">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Model Pakaian Favorit</label>
                                    <input type="text" name="preferensi_model" class="form-control" value="<?= esc($pelanggan['preferensi_model'] ?? '') ?>" placeholder="Misal: Slim Fit, Double Breasted, Kerah Shanghai">
                                </div>
                            </div>

                            <!-- Detail Ukuran Tubuh Kustom (Custom Tailor Measurements) -->
                            <div class="col-lg-8 ps-lg-4 mt-4 mt-lg-0">
                                <h6 class="fw-bold mb-3 text-primary">
                                    <i class="fal fa-ruler-combined me-1"></i> Data Ukuran Kustom Tubuh (CM)
                                </h6>
                                <p class="text-muted small">
                                    Data ukuran ini otomatis digunakan saat customer memesan pakaian custom baru agar staff tidak perlu mengulang menanyakan ukuran dari awal.
                                </p>

                                <?php $u = $pelanggan['detail_ukuran_array'] ?? []; ?>

                                <div class="row g-3">
                                    <!-- Bagian Atasan / Jas / Kemeja -->
                                    <div class="col-12">
                                        <div class="bg-light p-2 rounded fw-semibold small text-dark mb-2">
                                            <i class="fal fa-tshirt me-1 text-primary"></i> Ukuran Atasan (Baju / Kemeja / Jas)
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lingkar Dada (LD)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lingkar_dada" class="form-control" value="<?= esc($u['lingkar_dada'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Panjang Baju (PB)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="panjang_baju" class="form-control" value="<?= esc($u['panjang_baju'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lebar Bahu</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lebar_bahu" class="form-control" value="<?= esc($u['lebar_bahu'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Panjang Lengan</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="panjang_lengan" class="form-control" value="<?= esc($u['panjang_lengan'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lingkar Leher</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lingkar_leher" class="form-control" value="<?= esc($u['lingkar_leher'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Panjang Jas</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="panjang_jas" class="form-control" value="<?= esc($u['panjang_jas'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>

                                    <!-- Bagian Celana -->
                                    <div class="col-12 mt-3">
                                        <div class="bg-light p-2 rounded fw-semibold small text-dark mb-2">
                                            <i class="fal fa-columns me-1 text-primary"></i> Ukuran Bawahan (Celana)
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lingkar Pinggang</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lingkar_pinggang" class="form-control" value="<?= esc($u['lingkar_pinggang'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Panjang Celana</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="panjang_celana" class="form-control" value="<?= esc($u['panjang_celana'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lingkar Paha</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lingkar_paha" class="form-control" value="<?= esc($u['lingkar_paha'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lingkar Pinggul</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lingkar_pinggul" class="form-control" value="<?= esc($u['lingkar_pinggul'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Pesak (Selangkangan)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="pesak" class="form-control" value="<?= esc($u['pesak'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 col-6">
                                        <label class="form-label small">Lingkar Kaki Bawah</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="lingkar_kaki" class="form-control" value="<?= esc($u['lingkar_kaki'] ?? '') ?>" placeholder="0">
                                            <span class="input-group-text">cm</span>
                                        </div>
                                    </div>

                                    <div class="col-12 mt-3">
                                        <label class="form-label small fw-semibold">Catatan Khusus Bentuk Tubuh / Pola Jahitan</label>
                                        <textarea name="catatan_ukuran" class="form-control form-control-sm" rows="3" placeholder="Misal: Bahu kanan agak turun 1cm, perut buncit, minta celana agak longgar di paha."><?= esc($u['catatan_ukuran'] ?? '') ?></textarea>
                                    </div>
                                </div>

                                <div class="mt-4 pt-3 border-top text-end">
                                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                                        <i class="fal fa-save me-1"></i> Simpan Data Ukuran & Preferensi
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- ================= TAB 5: CATATAN STAFF ================= -->
                <div class="tab-pane fade" id="tab-catatan" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-6 border-end pe-lg-4">
                            <h6 class="fw-bold mb-3">Catatan Tetap Profil Customer</h6>
                            <form action="<?= site_url('admin/crm/pelanggan/simpan_profil/' . $pelanggan['id_pelanggan']) ?>" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status_crm" value="<?= esc($pelanggan['status_crm']) ?>">
                                <input type="hidden" name="opt_in_broadcast" value="<?= esc($pelanggan['opt_in_broadcast'] ?? 1) ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label small text-muted">
                                        Catatan ini selalu terlihat saat staf membuka profil customer (misal: kebiasaan, preferensi waktu dihubungi, persiapan acara nikah, dll).
                                    </label>
                                    <textarea name="catatan" class="form-control" rows="8" placeholder="Tuliskan catatan profil penting mengenai customer ini..."><?= esc($pelanggan['catatan_crm'] ?? '') ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                                    <i class="fal fa-save me-1"></i> Simpan Catatan Profil
                                </button>
                            </form>
                        </div>
                        <div class="col-lg-6 ps-lg-4 mt-4 mt-lg-0">
                            <h6 class="fw-bold mb-3">Tambah Catatan Interaksi Cepat (Masuk Timeline)</h6>
                            <form action="<?= site_url('admin/crm/pelanggan/tambah_aktivitas/' . $pelanggan['id_pelanggan']) ?>" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="tipe" value="catatan">
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Judul Catatan</label>
                                    <input type="text" name="judul" class="form-control form-control-sm" placeholder="Misal: Customer menanyakan paket wedding 2027" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">Isi Catatan</label>
                                    <textarea name="deskripsi" class="form-control form-control-sm" rows="5" placeholder="Detail catatan staff..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-outline-primary btn-sm fw-semibold">
                                    <i class="fal fa-plus me-1"></i> Tambahkan ke Timeline
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ================= TAB 6: FOLLOW-UP & REMINDER ================= -->
                <div class="tab-pane fade" id="tab-followup" role="tabpanel">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="fw-bold mb-0">Daftar Jadwal Tindakan & Follow-up</h6>
                        <button class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambahFollowup">
                            <i class="fal fa-plus me-1"></i> Buat Follow-up Baru
                        </button>
                    </div>

                    <?php if (empty($followupList)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-calendar-times fa-3x mb-3 text-secondary opacity-50"></i>
                            <p class="mb-0">Tidak ada jadwal follow-up untuk customer ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border">
                                <thead class="table-light">
                                    <tr>
                                        <th>Jatuh Tempo</th>
                                        <th>Tipe / Kategori</th>
                                        <th>Judul & Catatan</th>
                                        <th>Status</th>
                                        <th>Staff</th>
                                        <th class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($followupList as $f): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold <?= $f['tanggal_jatuh_tempo'] < date('Y-m-d') && $f['status'] === 'menunggu' ? 'text-danger' : 'text-dark' ?>">
                                                    <?= date('d/m/Y', strtotime($f['tanggal_jatuh_tempo'])) ?>
                                                </span>
                                                <?php if ($f['tanggal_jatuh_tempo'] < date('Y-m-d') && $f['status'] === 'menunggu'): ?>
                                                    <span class="badge bg-danger ms-1">Terlambat</span>
                                                <?php elseif ($f['tanggal_jatuh_tempo'] === date('Y-m-d') && $f['status'] === 'menunggu'): ?>
                                                    <span class="badge bg-warning text-dark ms-1">Hari Ini</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?= ucfirst(str_replace('_', ' ', $f['kategori'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= esc($f['judul']) ?></strong>
                                                <?php if (!empty($f['catatan'])): ?>
                                                    <div class="small text-muted" style="white-space: pre-wrap;"><?= esc($f['catatan']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($f['status'] === 'selesai'): ?>
                                                    <span class="badge bg-success"><i class="fal fa-check me-1"></i> Selesai</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="fal fa-clock me-1"></i> Menunggu</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?= esc($f['nama_staff'] ?? 'Staff') ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($f['status'] === 'menunggu'): ?>
                                                    <form action="<?= site_url('admin/crm/followup/selesai/' . $f['id_followup']) ?>" method="post" class="d-inline" onsubmit="return confirm('Tandai follow-up ini telah selesai?');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-sm btn-success py-1 px-2">
                                                            <i class="fal fa-check me-1"></i> Selesai
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small"><i class="fal fa-check-double text-success"></i></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ================= MODAL TAMBAH TAG ================= -->
<div class="modal fade" id="modalTambahTag" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form action="<?= site_url('admin/crm/pelanggan/tambah_tag/' . $pelanggan['id_pelanggan']) ?>" method="post">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Tambah Tag Customer</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pilih dari Tag yang Ada</label>
                        <select name="tag_id" class="form-select form-select-sm">
                            <option value="">-- Pilih Tag --</option>
                            <?php foreach ($allTags as $t): ?>
                                <option value="<?= $t['id_tag'] ?>"><?= esc($t['nama_tag']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="text-center text-muted small my-2">-- atau buat tag baru --</div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nama Tag Baru</label>
                        <input type="text" name="nama_tag_baru" class="form-control form-control-sm" placeholder="Misal: Wedding 2026">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Warna Badge</label>
                        <select name="warna" class="form-select form-select-sm">
                            <option value="primary">Biru (Primary)</option>
                            <option value="success">Hijau (Success)</option>
                            <option value="info">Cyan (Info)</option>
                            <option value="warning">Kuning (Warning)</option>
                            <option value="danger">Merah (Danger)</option>
                            <option value="dark">Hitam (Dark)</option>
                            <option value="secondary">Abu-abu (Secondary)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Tambahkan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL BUAT FOLLOW-UP ================= -->
<div class="modal fade" id="modalTambahFollowup" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= site_url('admin/crm/followup/simpan') ?>" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="pelanggan_id" value="<?= $pelanggan['id_pelanggan'] ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Buat Jadwal Follow-up / Reminder</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Kategori Tindakan</label>
                        <select name="kategori" class="form-select form-select-sm" required>
                            <option value="tanya_harga">Follow-up Prospek (Tanya Harga)</option>
                            <option value="fitting">Pengingat Jadwal Fitting</option>
                            <option value="pengambilan">Pengingat Pengambilan Pakaian</option>
                            <option value="pengembalian_sewa">Pengingat Pengembalian Sewa</option>
                            <option value="konfirmasi_ukuran">Konfirmasi Ukuran Pakaian</option>
                            <option value="penawaran">Penawaran Promo / Produk Baru</option>
                            <option value="tagihan">Follow-up Pembayaran</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Judul Tugas / Tindakan</label>
                        <input type="text" name="judul" class="form-control form-control-sm" placeholder="Misal: Hubungi customer untuk fitting jas tahap 2" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tanggal Jatuh Tempo Tindakan</label>
                        <input type="date" name="tanggal_jatuh_tempo" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan / Detail yang Perlu Ditanyakan</label>
                        <textarea name="catatan" class="form-control form-control-sm" rows="3" placeholder="Informasi tambahan untuk staff yang akan mengeksekusi..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Jadwalkan Follow-up</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL KIRIM WHATSAPP LANGSUNG ================= -->
<div class="modal fade" id="modalKirimWaDirect" tabindex="-1">
    <div class="modal-dialog">
        <form id="formKirimWaDirect">
            <?= csrf_field() ?>
            <input type="hidden" name="nomor" value="<?= esc($pelanggan['nomor_utama']) ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">
                        <i class="fab fa-whatsapp text-success me-1"></i> Kirim WhatsApp ke <?= esc($pelanggan['nama_pelanggan']) ?>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Nomor Tujuan</label>
                        <input type="text" class="form-control form-control-sm bg-light" value="<?= esc($pelanggan['nomor_utama']) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Gunakan Template Pesan</label>
                        <select id="selectTemplateWaDirect" class="form-select form-select-sm">
                            <option value="">-- Ketik Pesan Manual --</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= esc($t['pesan']) ?>"><?= esc($t['judul']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Isi Pesan WhatsApp</label>
                        <textarea id="pesanWaDirect" name="pesan" class="form-control form-control-sm" rows="6" placeholder="Tuliskan pesan WhatsApp..." required></textarea>
                    </div>
                    <div id="alertWaDirect" class="d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" id="btnSubmitWaDirect" class="btn btn-success btn-sm fw-semibold">
                        <i class="fal fa-paper-plane me-1"></i> Kirim Sekarang
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Template dropdown selection
    const selTpl = document.getElementById('selectTemplateWaDirect');
    const txtPesan = document.getElementById('pesanWaDirect');
    if (selTpl && txtPesan) {
        selTpl.addEventListener('change', function() {
            if (this.value) {
                let text = this.value.replace('{nama}', '<?= addslashes($pelanggan['nama_pelanggan']) ?>');
                txtPesan.value = text;
            }
        });
    }

    // Handle Direct WA submit via AJAX
    const formWa = document.getElementById('formKirimWaDirect');
    const alertBox = document.getElementById('alertWaDirect');
    const btnSubmit = document.getElementById('btnSubmitWaDirect');

    if (formWa) {
        formWa.addEventListener('submit', function(e) {
            e.preventDefault();
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<i class="fal fa-spinner fa-spin me-1"></i> Mengirim...';
            alertBox.className = 'd-none';

            const formData = new FormData(formWa);

            fetch('<?= site_url('admin/crm/pelanggan/kirim_pesan/' . $pelanggan['id_pelanggan']) ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fal fa-paper-plane me-1"></i> Kirim Sekarang';
                if (data.status === 'success') {
                    alertBox.className = 'alert alert-success mt-2 small';
                    alertBox.innerHTML = '<i class="fal fa-check-circle me-1"></i> ' + data.message + (data.notice ? '<br><small class="text-muted">' + data.notice + '</small>' : '');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    alertBox.className = 'alert alert-danger mt-2 small';
                    alertBox.innerHTML = '<i class="fal fa-exclamation-triangle me-1"></i> ' + data.message;
                }
            })
            .catch(err => {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="fal fa-paper-plane me-1"></i> Kirim Sekarang';
                alertBox.className = 'alert alert-danger mt-2 small';
                alertBox.innerHTML = 'Terjadi kesalahan jaringan atau server.';
            });
        });
    }
});
</script>
<?= $this->endSection() ?>
