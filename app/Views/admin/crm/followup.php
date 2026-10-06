<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold">
                <i class="fal fa-tasks text-primary me-2"></i> Pusat Follow-up & Reminder
            </h4>
            <p class="text-muted small mb-0">Kelola tindakan follow-up customer, penagihan invoice, dan pengingat jadwal pakaian penting.</p>
        </div>
        <button class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambahFollowupGlobal">
            <i class="fal fa-plus me-1"></i> Buat Follow-up Customer
        </button>
    </div>

    <?= $this->include('admin/crm/nav_crm') ?>

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

    <!-- Sub Nav Tabs Follow-up -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom p-0">
            <ul class="nav nav-tabs card-header-tabs m-0 px-3">
                <li class="nav-item">
                    <a class="nav-link py-3 px-4 fw-semibold <?= $tab === 'customer' ? 'active text-primary' : 'text-muted' ?>" href="<?= site_url('admin/crm/followup?tab=customer') ?>">
                        <i class="fal fa-user-check me-2"></i> Follow-up Customer
                        <span class="badge bg-light text-dark border ms-1"><?= count($followupList) ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-3 px-4 fw-semibold <?= $tab === 'tagihan' ? 'active text-primary' : 'text-muted' ?>" href="<?= site_url('admin/crm/followup?tab=tagihan') ?>">
                        <i class="fal fa-money-bill-wave me-2 text-danger"></i> Follow-up Tagihan Invoice
                        <span class="badge bg-danger text-white ms-1"><?= count($invoicesTagihan) ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link py-3 px-4 fw-semibold <?= $tab === 'reminder' ? 'active text-primary' : 'text-muted' ?>" href="<?= site_url('admin/crm/followup?tab=reminder') ?>">
                        <i class="fal fa-bell me-2 text-warning"></i> Pengingat Otomatis (Reminder)
                        <span class="badge bg-warning text-dark ms-1"><?= count($reminders) ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">

            <!-- ================= SUB TAB 1: FOLLOW-UP CUSTOMER ================= -->
            <?php if ($tab === 'customer'): ?>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="btn-group btn-group-sm">
                        <a href="<?= site_url('admin/crm/followup?tab=customer&status=menunggu') ?>" class="btn <?= $status === 'menunggu' ? 'btn-primary fw-semibold' : 'btn-outline-secondary' ?>">
                            Menunggu Tindakan
                        </a>
                        <a href="<?= site_url('admin/crm/followup?tab=customer&status=selesai') ?>" class="btn <?= $status === 'selesai' ? 'btn-primary fw-semibold' : 'btn-outline-secondary' ?>">
                            Riwayat Selesai
                        </a>
                        <a href="<?= site_url('admin/crm/followup?tab=customer&status=semua') ?>" class="btn <?= $status === 'semua' ? 'btn-primary fw-semibold' : 'btn-outline-secondary' ?>">
                            Semua
                        </a>
                    </div>
                    <form action="<?= site_url('admin/crm/followup') ?>" method="get" class="d-flex gap-2">
                        <input type="hidden" name="tab" value="customer">
                        <input type="hidden" name="status" value="<?= esc($status) ?>">
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <input type="text" name="cari" class="form-control" placeholder="Cari nama atau judul..." value="<?= esc($cari) ?>">
                            <button type="submit" class="btn btn-outline-secondary"><i class="fal fa-search"></i></button>
                        </div>
                    </form>
                </div>

                <?php if (empty($followupList)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fal fa-clipboard-check fa-3x mb-3 text-secondary opacity-50"></i>
                        <h5>Tidak ada follow-up customer</h5>
                        <p class="mb-0">Semua tugas follow-up staff telah terselesaikan atau belum ada jadwal baru.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border">
                            <thead class="table-light">
                                <tr>
                                    <th>Jatuh Tempo</th>
                                    <th>Customer</th>
                                    <th>Kategori Tindakan</th>
                                    <th>Judul & Instruksi</th>
                                    <th>Status</th>
                                    <th>Staff PIC</th>
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
                                            <a href="<?= site_url('admin/crm/pelanggan/' . $f['pelanggan_id']) ?>" class="fw-bold text-decoration-none text-primary">
                                                <?= esc($f['nama_pelanggan']) ?>
                                            </a>
                                            <?php if (!empty($f['nomor_wa'])): ?>
                                                <div class="small text-muted">
                                                    <i class="fab fa-whatsapp text-success me-1"></i><?= esc($f['nomor_wa']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= ucfirst(str_replace('_', ' ', $f['kategori'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= esc($f['judul']) ?></div>
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
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= site_url('admin/crm/pelanggan/' . $f['pelanggan_id']) ?>" class="btn btn-outline-primary" title="Buka Profil 360">
                                                    <i class="fal fa-id-card"></i>
                                                </a>
                                                <?php if (!empty($f['nomor_wa'])): ?>
                                                    <a href="<?= site_url('admin/crm/chat?nomor=' . $f['nomor_wa']) ?>" class="btn btn-outline-success" title="Chat WhatsApp">
                                                        <i class="fab fa-whatsapp"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($f['status'] === 'menunggu'): ?>
                                                    <form action="<?= site_url('admin/crm/followup/selesai/' . $f['id_followup']) ?>" method="post" class="d-inline" onsubmit="return confirm('Tandai follow-up ini telah selesai?');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-success" title="Tandai Selesai">
                                                            <i class="fal fa-check"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <!-- ================= SUB TAB 2: FOLLOW-UP TAGIHAN ================= -->
            <?php elseif ($tab === 'tagihan'): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="text-muted small mb-0">Daftar invoice yang statusnya belum lunas (Kredit, Belum Bayar, atau Menunggu Konfirmasi).</p>
                    <form action="<?= site_url('admin/crm/followup') ?>" method="get" class="d-flex gap-2">
                        <input type="hidden" name="tab" value="tagihan">
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <input type="text" name="cari" class="form-control" placeholder="Cari invoice / pelanggan..." value="<?= esc($cari) ?>">
                            <button type="submit" class="btn btn-outline-secondary"><i class="fal fa-search"></i></button>
                        </div>
                    </form>
                </div>

                <?php if (empty($invoicesTagihan)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fal fa-check-circle fa-3x mb-3 text-success"></i>
                        <h5>Semua Tagihan Lunas!</h5>
                        <p class="mb-0">Tidak ada piutang invoice pending saat ini.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice</th>
                                    <th>Pelanggan</th>
                                    <th>Nomor WhatsApp</th>
                                    <th class="text-end">Total Tagihan</th>
                                    <th class="text-end">Telah Dibayar</th>
                                    <th class="text-end">Sisa Piutang</th>
                                    <th class="text-center">Aksi Tagih</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoicesTagihan as $inv): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark">#<?= esc($inv['seri']) ?></span>
                                            <div class="small text-muted"><?= date('d/m/Y', strtotime($inv['tanggal_pesan'])) ?></div>
                                        </td>
                                        <td>
                                            <a href="<?= site_url('admin/crm/pelanggan/' . $inv['id_pelanggan']) ?>" class="fw-bold text-decoration-none">
                                                <?= esc($inv['nama_pelanggan']) ?>
                                            </a>
                                            <div class="small text-muted"><?= esc($inv['nama_juragan']) ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($inv['nomor_hp'])): ?>
                                                <i class="fab fa-whatsapp text-success me-1"></i><?= esc($inv['nomor_hp']) ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Tanpa Nomor</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            Rp <?= number_format($inv['total_tagihan'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-end text-success">
                                            Rp <?= number_format($inv['telah_dibayar'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-end text-danger fw-bold">
                                            Rp <?= number_format($inv['sisa_tagihan'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($inv['nomor_hp'])): ?>
                                                <button class="btn btn-sm btn-outline-success fw-semibold btn-kirim-tagihan"
                                                        data-id="<?= $inv['id_invoice'] ?>"
                                                        data-seri="<?= esc($inv['seri']) ?>"
                                                        data-nama="<?= esc($inv['nama_pelanggan']) ?>"
                                                        data-nomor="<?= esc($inv['nomor_hp']) ?>"
                                                        data-total="Rp <?= number_format($inv['total_tagihan'], 0, ',', '.') ?>"
                                                        data-sisa="Rp <?= number_format($inv['sisa_tagihan'], 0, ',', '.') ?>">
                                                    <i class="fab fa-whatsapp me-1"></i> Kirim Tagihan
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <!-- ================= SUB TAB 3: REMINDER OTOMATIS ================= -->
            <?php elseif ($tab === 'reminder'): ?>
                <div class="mb-3">
                    <p class="text-muted small mb-0">
                        Pengingat otomatis yang dideteksi oleh sistem berdasarkan jadwal sewa (ambil/kembali), deadline pembuatan pesanan custom, dan follow-up jatuh tempo.
                    </p>
                </div>

                <?php if (empty($reminders)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fal fa-bell-slash fa-3x mb-3 text-secondary opacity-50"></i>
                        <h5>Tidak ada jadwal mendesak saat ini</h5>
                        <p class="mb-0">Tidak ada pengembalian sewa, deadline, atau follow-up yang jatuh tempo dalam 3 hari ke depan.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($reminders as $rem): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card border-0 shadow-sm h-100 border-start border-4 border-<?= esc($rem['warna']) ?>">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <span class="badge bg-<?= esc($rem['warna']) ?>"><?= esc($rem['status_waktu']) ?></span>
                                            <span class="text-muted small"><i class="fal fa-calendar-alt me-1"></i><?= date('d M Y', strtotime($rem['tanggal'])) ?></span>
                                        </div>
                                        <h6 class="fw-bold mt-2 mb-1 text-dark"><?= esc($rem['judul']) ?></h6>
                                        <p class="text-muted small mb-2"><?= esc($rem['deskripsi']) ?></p>
                                        
                                        <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-2">
                                            <a href="<?= site_url('admin/crm/pelanggan/' . $rem['pelanggan_id']) ?>" class="fw-semibold text-decoration-none small">
                                                <i class="fal fa-user me-1"></i><?= esc($rem['nama_pelanggan']) ?>
                                            </a>
                                            <div class="d-flex gap-1">
                                                <?php if (!empty($rem['nomor_wa'])): ?>
                                                    <a href="<?= site_url('admin/crm/chat?nomor=' . $rem['nomor_wa']) ?>" class="btn btn-xs btn-outline-success py-0 px-2" style="font-size: 11px;">
                                                        <i class="fab fa-whatsapp"></i> Chat
                                                    </a>
                                                <?php endif; ?>
                                                <a href="<?= site_url('admin/crm/pelanggan/' . $rem['pelanggan_id']) ?>" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size: 11px;">
                                                    Profil 360
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Modal Tambah Follow-up Global -->
<div class="modal fade" id="modalTambahFollowupGlobal" tabindex="-1">
    <div class="modal-dialog">
        <form action="<?= site_url('admin/crm/followup/simpan') ?>" method="post">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Buat Follow-up Customer Baru</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ID / Nama Customer</label>
                        <input type="number" name="pelanggan_id" class="form-control form-control-sm" placeholder="Masukkan ID Pelanggan" required>
                        <div class="form-text small">Atau buka profil customer 360 untuk membuat follow-up langsung dari halamannya.</div>
                    </div>
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
                        <label class="form-label small fw-semibold">Judul Follow-up</label>
                        <input type="text" name="judul" class="form-control form-control-sm" placeholder="Misal: Tanya kelanjutan order jas pengantin" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tanggal Jatuh Tempo</label>
                        <input type="date" name="tanggal_jatuh_tempo" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Catatan Petunjuk</label>
                        <textarea name="catatan" class="form-control form-control-sm" rows="3" placeholder="Informasi detail untuk staff..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Jadwal</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Kirim Tagihan WhatsApp -->
<div class="modal fade" id="modalTagihanWa" tabindex="-1">
    <div class="modal-dialog">
        <form id="formTagihanWa">
            <?= csrf_field() ?>
            <input type="hidden" name="id_invoice" id="tagihanInvoiceId">
            <input type="hidden" name="tipe_pesan" value="tagihan">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Kirim WhatsApp Follow-up Tagihan</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nomor Tujuan</label>
                        <input type="text" name="nomor" id="tagihanNomor" class="form-control form-control-sm" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Isi Pesan WhatsApp</label>
                        <textarea name="pesan" id="tagihanPesan" class="form-control form-control-sm" rows="6" required></textarea>
                    </div>
                    <div id="alertTagihan" class="d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" id="btnKirimTagihanModal" class="btn btn-success btn-sm">
                        <i class="fab fa-whatsapp me-1"></i> Kirim Sekarang
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const rawTemplate = <?= json_encode($templateTagihan) ?>;
    const modalTagihan = new bootstrap.Modal(document.getElementById('modalTagihanWa'));

    document.querySelectorAll('.btn-kirim-tagihan').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const seri = this.dataset.seri;
            const nama = this.dataset.nama;
            const nomor = this.dataset.nomor;
            const total = this.dataset.total;
            const sisa = this.dataset.sisa;

            let msg = rawTemplate
                .replace(/{nama}/g, nama)
                .replace(/{invoice}/g, '#' + seri)
                .replace(/{total}/g, total)
                .replace(/{sisa}/g, sisa);

            document.getElementById('tagihanInvoiceId').value = id;
            document.getElementById('tagihanNomor').value = nomor;
            document.getElementById('tagihanPesan').value = msg;
            document.getElementById('alertTagihan').className = 'd-none';

            modalTagihan.show();
        });
    });

    const form = document.getElementById('formTagihanWa');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnKirimTagihanModal');
            const alertBox = document.getElementById('alertTagihan');
            btn.disabled = true;
            btn.innerHTML = '<i class="fal fa-spinner fa-spin me-1"></i> Mengirim...';

            fetch('<?= site_url('admin/crm/kirim_wa') ?>', {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-whatsapp me-1"></i> Kirim Sekarang';
                if (data.status === 'success') {
                    alertBox.className = 'alert alert-success mt-2 small';
                    alertBox.innerHTML = '<i class="fal fa-check-circle me-1"></i> ' + data.message;
                    setTimeout(() => modalTagihan.hide(), 1500);
                } else {
                    alertBox.className = 'alert alert-danger mt-2 small';
                    alertBox.innerHTML = '<i class="fal fa-exclamation-triangle me-1"></i> ' + data.message;
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-whatsapp me-1"></i> Kirim Sekarang';
                alertBox.className = 'alert alert-danger mt-2 small';
                alertBox.innerHTML = 'Terjadi kesalahan sistem.';
            });
        });
    }
});
</script>
<?= $this->endSection() ?>
