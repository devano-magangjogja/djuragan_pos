<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    /* Hanya untuk tampilan responsif: tanggal ditarik ke tepi kanan barisnya
       supaya baris badge + tanggal terpakai penuh. Di atas 992px tidak ada
       satu pun yang berubah dari susunan aslinya. */
    @media (max-width: 991.98px) {
        .pengingat-waktu {
            justify-content: space-between;
        }
    }

    /* Kartu KPI di layar sempit hanya kebagian setengah lebar (134px ruang
       isinya), sementara angkanya ditulis penuh: "Rp 8.328.184.447" butuh
       159px pada ukuran h4 bawaan sehingga jatuh ke baris kedua dan kartunya
       jadi lebih tinggi dari tetangganya. Angkanya dikecilkan ke 1,05rem
       (butuh 124px, sisa 10px) dan labelnya ke 0,72rem supaya "Follow-up
       Tagihan" tidak belah dua. Desktop tidak disentuh. */
    @media (max-width: 767.98px) {
        .card h3.fw-bold,
        .card h4.fw-bold {
            font-size: 1.05rem;
        }

        .card span.text-muted.small.fw-semibold {
            font-size: 0.72rem;
        }

        /* grup kartu kedua tidak memakai h-100 seperti grup pertama; hanya di
           layar sempit tingginya disamakan supaya dua kartu sekolom tidak beda */
        .kartu-kpi-sejajar {
            height: 100%;
        }
    }

    /* Judul halaman di layar sempit ikut ukuran judul halaman lain: rumus h3
       Bootstrap, sama seperti <h1 class="h3"> di Produk / Pencatatan COD.
       Desktop tidak disentuh, tetap ukuran h4 bawaannya. */
    @media (max-width: 991.98px) {
        .judul-dasbor {
            font-size: calc(1.3rem + 0.6vw);
        }
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold judul-dasbor">
                <i class="fal fa-chart-pie text-primary me-2 d-none d-lg-inline"></i>
                <?php /* desktop persis seperti semula (ikon + judul penuh); di bawah
                        breakpoint kolomnya (992px) ikon dan "& WhatsApp Center"
                        dilepas supaya judulnya muat satu baris */ ?>
                <span class="d-lg-none">Dashboard CRM</span>
                <span class="d-none d-lg-inline">Dashboard CRM &amp; WhatsApp Center</span>
            </h4>
        </div>
        <div class="d-flex align-items-center gap-2 flex-nowrap">
            <?= $this->include('admin/crm/tombol_wa') ?>
            <?php if (($provider ?? '') !== 'fonnte') : ?>
                <?php if ($isSandbox) : ?>
                    <span class="btn btn-sm pe-none fw-semibold text-nowrap bg-warning text-dark border-warning" title="Mode sandbox Kapso aktif">
                        <i class="fal fa-flask me-1"></i> Sandbox
                    </span>
                <?php else : ?>
                    <span class="btn btn-sm pe-none fw-semibold text-nowrap bg-success text-white border-success" title="Production mode">
                        <i class="fal fa-check-circle me-1"></i> Production
                    </span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>



    <!-- 6 KPI Bercerita Utama -->
    <div class="row g-3 mb-4">
        <!-- Customer Aktif Bulan Ini -->
        <div class="col-xl-2 col-md-4 col-6">
            <a href="<?= site_url('admin/crm/pelanggan?status=aktif') ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Customer Aktif</span>
                            <i class="fal fa-user-check text-success fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-success mt-1"><?= number_format($ringkasan['aktif_bulan_ini']) ?></h3>
                        <div class="text-muted small mt-1" style="font-size: 11px;">transaksi 30 hari terakhir</div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Customer Baru Bulan Ini -->
        <div class="col-xl-2 col-md-4 col-6">
            <a href="<?= site_url('admin/crm/pelanggan?status=baru') ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-info">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Customer Baru</span>
                            <i class="fal fa-sparkles text-info fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-info mt-1"><?= number_format($ringkasan['pelanggan_baru_bulan_ini']) ?></h3>
                        <div class="text-muted small mt-1" style="font-size: 11px;">order perdana bulan ini</div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Customer Repeat Order -->
        <div class="col-xl-2 col-md-4 col-6">
            <a href="<?= site_url('admin/crm/pelanggan?segmen=repeat') ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Repeat Order</span>
                            <i class="fal fa-sync text-primary fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-primary mt-1"><?= number_format($ringkasan['repeat_order']) ?></h3>
                        <div class="text-muted small mt-1" style="font-size: 11px;">pelanggan pesan > 1x</div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Customer Tidak Aktif / Pasif -->
        <div class="col-xl-2 col-md-4 col-6">
            <a href="<?= site_url('admin/crm/pelanggan?status=tidak_aktif') ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-secondary">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Customer Pasif</span>
                            <i class="fal fa-user-clock text-secondary fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-secondary mt-1"><?= number_format($ringkasan['pasif_pelanggan']) ?></h3>
                        <div class="text-muted small mt-1" style="font-size: 11px;">> 60 hari tanpa order</div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Prospek Belum Closing -->
        <div class="col-xl-2 col-md-4 col-6">
            <a href="<?= site_url('admin/crm/pelanggan?status=prospek') ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Belum Closing</span>
                            <i class="fal fa-comments text-warning fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-warning mt-1"><?= number_format($ringkasan['prospek_belum_closing']) ?></h3>
                        <div class="text-muted small mt-1" style="font-size: 11px;">prospek/tanya di WA</div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Follow-up Tagihan -->
        <div class="col-xl-2 col-md-4 col-6">
            <a href="<?= site_url('admin/crm/followup?tab=tagihan') ?>" class="text-decoration-none text-reset">
                <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small fw-semibold">Follow-up Tagihan</span>
                            <i class="fal fa-money-bill-wave text-danger fs-5"></i>
                        </div>
                        <h3 class="fw-bold mb-0 text-danger mt-1"><?= number_format($ringkasan['tagihan_pending']) ?></h3>
                        <div class="text-muted small mt-1" style="font-size: 11px;">invoice belum lunas</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Ringkasan Akumulasi & Finansial -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm bg-white p-3 kartu-kpi-sejajar">
                <span class="text-muted small fw-semibold">Total Pelanggan<span class="d-none d-lg-inline"> Terdaftar</span></span>
                <h4 class="fw-bold mb-0 text-dark mt-1"><?= number_format($ringkasan['total_pelanggan']) ?> customer</h4>
                <div class="small text-muted" style="font-size: 11px;">database kontak & akun</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm bg-white p-3 kartu-kpi-sejajar">
                <span class="text-muted small fw-semibold">Akumulasi Omset POS</span>
                <h4 class="fw-bold mb-0 text-success mt-1">Rp <?= number_format($ringkasan['total_omset'], 0, ',', '.') ?></h4>
                <div class="small text-muted" style="font-size: 11px;">seluruh riwayat transaksi</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm bg-white p-3 kartu-kpi-sejajar">
                <span class="text-muted small fw-semibold"><span class="d-none d-lg-inline">Tagihan </span>Belum Lunas</span>
                <h4 class="fw-bold mb-0 text-danger mt-1">Rp <?= number_format($ringkasan['total_piutang'], 0, ',', '.') ?></h4>
                <div class="small text-muted" style="font-size: 11px;"><?= number_format($ringkasan['tagihan_pending']) ?> invoice pending</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm bg-white p-3 kartu-kpi-sejajar">
                <span class="text-muted small fw-semibold">Peluang Merge<span class="d-none d-lg-inline"> Akun Duplikat</span></span>
                <h4 class="fw-bold mb-0 text-primary mt-1"><?= number_format($ringkasan['duplikat_terdeteksi']) ?> grup</h4>
                <div class="small text-muted" style="font-size: 11px;"><a href="<?= site_url('admin/crm/duplikat') ?>" class="text-decoration-none">Periksa & gabungkan &rarr;</a></div>
            </div>
        </div>
    </div>

    <!-- Dua Kolom: Pengingat Tindakan Hari Ini & Menu Cepat -->
    <div class="row g-4 mb-4">
        <!-- Kolom Kiri: Reminder & Jadwal Penting Hari Ini -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-lg-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">
                        <i class="fal fa-bell text-warning me-2"></i> Pengingat & Tindakan Hari Ini
                    </h6>
                    <?php /* desktop: tautannya satu baris dengan judulnya di sisi kanan */ ?>
                    <div class="text-end d-none d-lg-block">
                        <a href="<?= site_url('admin/crm/followup?tab=reminder') ?>" class="small text-decoration-none">
                            Lihat Semua Jadwal &rarr;
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($reminders)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-smile fa-3x mb-2 text-success opacity-50"></i>
                            <p class="mb-0">Tidak ada jadwal mendesak atau follow-up tertunda hari ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($reminders as $r): ?>
                                <div class="list-group-item p-3 d-flex justify-content-between align-items-center">
                                    <?php /* pembungkus teksnya harus memanjang mengisi sisa
                                            baris, kalau tidak baris badge + tanggal ikut
                                            mengecil pas isi teksnya dan tanggalnya tidak
                                            bisa digeser ke kanan */ ?>
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="pengingat-waktu d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-<?= esc($r['warna']) ?>"><?= esc($r['status_waktu']) ?></span>
                                            <span class="text-muted small text-nowrap"><i class="fal fa-calendar-alt me-1"></i><?= date('d M Y', strtotime($r['tanggal'])) ?></span>
                                        </div>
                                        <h6 class="mb-1 fw-bold text-dark"><?= esc($r['judul']) ?></h6>
                                        <p class="mb-0 text-muted small"><?= esc($r['deskripsi']) ?></p>
                                    </div>
                                    <div class="text-end flex-shrink-0 ms-3">
                                        <a href="<?= site_url('admin/crm/pelanggan/' . $r['pelanggan_id']) ?>" class="btn btn-sm btn-outline-primary me-2" title="Profil 360">
                                            <i class="fal fa-id-card"></i>
                                        </a>
                                        <?php if (!empty($r['nomor_wa'])): ?>
                                            <a href="<?= site_url('admin/crm/chat?nomor=' . $r['nomor_wa']) ?>" class="btn btn-sm btn-outline-success" title="Chat WA">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php /* layar sempit: tautannya masuk ke dalam kartu, di pojok kanan
                        bawah daftar, jadi ia ikut turun kalau pengingatnya lebih
                        dari satu; paddingnya mengikuti isian kartu (p-3) */ ?>
                <div class="text-end px-3 pb-3 d-lg-none">
                    <a href="<?= site_url('admin/crm/followup?tab=reminder') ?>" class="small text-decoration-none">
                        Lihat Semua Jadwal &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Shortcut Aksi Cepat CRM & Info Gateway -->
        <div class="col-lg-5">
            <div class="row g-3">
                <!-- Shortcut Live Chat -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                    <i class="fab fa-whatsapp fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Live Chat WhatsApp</h6>
                                    <small class="text-muted">Chat 2 arah langsung dengan customer</small>
                                </div>
                            </div>
                            <a href="<?= site_url('admin/crm/chat') ?>" class="btn btn-success btn-sm fw-semibold">
                                Buka Chat
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Shortcut Follow-up Customer -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                    <i class="fal fa-tasks fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Follow-up Customer & Tagihan</h6>
                                    <small class="text-muted">Tindak lanjuti prospek, fitting, dan invoice</small>
                                </div>
                            </div>
                            <a href="<?= site_url('admin/crm/followup') ?>" class="btn btn-primary btn-sm fw-semibold">
                                Buka Follow-up
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Shortcut Broadcast Promo -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                    <i class="fal fa-bullhorn fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Broadcast Promo Terarah</h6>
                                    <small class="text-muted">Kirim promo khusus segmen yang opt-in</small>
                                </div>
                            </div>
                            <a href="<?= site_url('admin/crm/broadcast') ?>" class="btn btn-info btn-sm fw-semibold text-white">
                                Broadcast
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Shortcut Duplikat -->
                <div class="col-12">
                    <div class="card border-0 shadow-sm bg-white">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 48px; height: 48px;">
                                    <i class="fal fa-clone fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Deteksi & Gabung Duplikat</h6>
                                    <small class="text-muted">Rapikan akun kembar tanpa hilang transaksi</small>
                                </div>
                            </div>
                            <a href="<?= site_url('admin/crm/duplikat') ?>" class="btn btn-outline-danger btn-sm fw-semibold">
                                Review Duplikat
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
