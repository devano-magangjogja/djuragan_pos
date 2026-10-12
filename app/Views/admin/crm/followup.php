<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* style.css hanya menyetel .nav-pills, sedangkan tab di sini .nav-tabs dan
   .card-header-nya dipaksa padding + border sendiri. Tiap tab diberi garis tipis
   di pinggirnya supaya jelas yang mana sedang dibuka. */
.card-header.crm-subnav {
    padding: 0.5rem 0.75rem !important;
}
.crm-subnav .nav-tabs {
    border-bottom: 0;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.crm-subnav .nav-link {
    border: 1px solid #eaecf0 !important;
    border-radius: 10px !important;
    background: #ffffff !important;
    color: #667085 !important;
}
.crm-subnav .nav-link:hover {
    background: #f9fafb !important;
    border-color: #d0d5dd !important;
    color: #101828 !important;
}
.crm-subnav .nav-link.active {
    background: #f9fafb !important;
    border-color: #98a2b3 !important;
    color: #101828 !important;
}
/* judul, teks penjelasan dan tab di halaman ini dikecilkan */
.fup-judul {
    font-size: 1.15rem;
}
.fup-teks {
    font-size: 0.82rem !important;
}
.crm-subnav .nav-link {
    font-size: 0.92rem !important;
    padding: 0.75rem 1.3rem !important;
}
.crm-subnav .badge {
    font-size: 0.78rem !important;
}
/* kartu reminder: nama pelanggan panjang dipotong supaya tombol tidak terdorong
   keluar, dan tab boleh turun baris di layar sempit */
.reminder-nama {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
/* Khusus mobile (<=768px). Tampilan desktop tidak diubah sama sekali:
   di atas lebar ini tabel tetap 7 kolom seperti sebelumnya.
   Di HP, tabel digeser ke samping membuat kolom kanannya tidak terbaca, jadi
   tiap baris ditumpuk jadi kartu dan setiap sel menampilkan label kolomnya
   (data-label), ditambah nama pelanggan yang boleh turun baris. */
/* Tabel halaman ini tidak boleh melebar sampai memunculkan scroll horizontal:
   style.css memaksa judul kolom nowrap sehingga kolom kanan terpotong keluar.
   Judul boleh turun baris dan teks panjang dipotong di tengah kata. */
.fup-halaman .fup-tabel th {
    white-space: normal !important;
}
.fup-halaman .fup-tabel td,
.fup-halaman .fup-tabel th {
    overflow-wrap: anywhere;
}
@media (max-width: 767.98px) {
    .fup-tabel thead {
        display: none;
    }
    .fup-tabel,
    .fup-tabel tbody,
    .fup-tabel td {
        display: block;
        width: 100%;
    }
    /* dua kolom: Pelanggan bersebelahan dengan Nomor WhatsApp,
       Total Dibayar dengan Sisa Piutang; sisanya satu baris penuh.
       minmax(0, 1fr) wajib: 1fr saja punya batas bawah "auto", jadi satu sel
       yang isinya panjang (nomor tagihan, tombol) memaksa kartunya melebar
       melewati layar dan halaman jadi bisa digeser ke samping. */
    .fup-tabel tr {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        column-gap: 0.9rem;
        border: 1px solid #eaecf0;
        border-radius: 10px;
        margin-bottom: 0.6rem;
        padding: 0.15rem 0.7rem;
    }
    .fup-tabel td:nth-child(1),
    .fup-tabel td:nth-child(4),
    .fup-tabel td:nth-child(7) {
        grid-column: 1 / -1;
    }
    .fup-tabel td {
        padding: 0.5rem 0 !important;
        border-bottom: 1px dashed #eaecf0;
        box-shadow: none;
        text-align: left !important;
        white-space: normal !important;
        overflow-wrap: anywhere;
    }
    .fup-tabel tr td:last-child {
        border-bottom: 0;
    }
    .fup-tabel td::before {
        content: attr(data-label);
        display: block;
        margin-bottom: 0.15rem;
        font-size: 0.72rem !important;
        font-weight: 600;
        color: #98a2b3;
    }
    .fup-tabel td .btn {
        margin-right: 0.4rem;
    }
    .reminder-nama {
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
    }
    /* Bar tab di bawah judul: dirapatkan ke tepi kiri kartu dan boleh turun
       baris. Di HP tiap tab melebar penuh seukuran kartu di bawahnya (dulu
       hanya selebar teksnya sehingga sisi kanannya kosong banget). */
    .card-header.crm-subnav {
        padding: 0.4rem 0.4rem !important;
    }
    .crm-subnav .nav-item {
        flex: 1 1 100%;
    }
    .crm-subnav .nav-link {
        white-space: normal;
        width: 100%;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
    }
    /* Angka jumlah (badge) didorong ke tepi kanan tab, rata dengan garis kanan
       kartu. ms-1 di markup juga !important, jadi selector ini harus lebih
       spesifik (0,3,0) supaya menang. */
    .crm-subnav .nav-link .badge {
        margin-left: auto !important;
    }
    /* Wadahnya tidak boleh jadi area geser lagi di HP (kedua sumbu), tapi juga
       tidak boleh meluber: "visible" membuat isi yang kepanjangan ikut melebar
       ke kanan sampai melewati tepi layar (bar atas ikut terlihat terpotong).
       "hidden" menjaga semuanya di dalam bingkai. */
    .fup-halaman .table-responsive {
        overflow: hidden !important;
    }
    /* Judul + satu baris penjelasan di bawahnya boleh memotong di tengah kata. */
    .fup-halaman .fup-judul,
    .fup-halaman .fup-teks {
        overflow-wrap: anywhere;
        min-width: 0;
    }
    /* Semua paragraf penjelasan di halaman ini diberi ruang kanan yang sama,
       jadi barisnya berhenti sebelum tepi layar dan tidak terlihat "penuh". */
    .fup-halaman .fup-teks {
        padding-right: 0.9rem;
        line-height: 1.45;
    }
    /* Baris judul di HP disusun vertikal: judul + penjelasan duluan, tombol
       Live Chat WA turun ke baris sendiri. Flex row bikin kotak teksnya
       melebar sampai sebaris penuh, jadi di sini dijadikan blok biasa. */
    .fup-judul-baris {
        /* !important karena baris ini membawa kelas BS d-flex (utility-nya
           !important, jadi tanpa !important aturan ini kalah) */
        display: block !important;
    }
    .fup-judul-aksi {
        margin-top: 0.6rem;
    }
    /* Seluruh isi halaman dirapatkan ke tepi kiri-kanan layar HP (band 768px
       ke bawah, bukan hanya 576px) supaya bar tab dan teks punya ruang lebih. */
    body.has-app-sidebar > div.container-xxl {
        padding-left: 0.5rem !important;
        padding-right: 0.5rem !important;
    }
    /* Kotak cari ikut melebar penuh seperti tab di atasnya. width:100% saja
       tidak cukup: form-nya adalah flex item yang lebarnya mengikuti isi, jadi
       form-nya juga yang diminta mengambil satu baris penuh. */
    .fup-halaman form.d-flex {
        flex: 1 1 100%;
    }
    .fup-halaman .cari-merged {
        width: 100% !important;
    }
    /* Catatan follow-up memakai pre-wrap supaya barisnya terjaga, tapi token
       panjang (nomor WA / kode nota) tetap boleh dipotong agar tidak meluber. */
    .fup-halaman .fup-catatan {
        overflow-wrap: anywhere;
    }
}
@media (max-width: 575.98px) {
    .crm-subnav .nav-link {
        font-size: 0.85rem !important;
        padding: 0.5rem 0.85rem !important;
    }
    .fup-judul {
        font-size: 1.05rem;
    }
    .fup-halaman .table thead th,
    .fup-halaman .table tbody td {
        padding: 0.5rem 0.6rem !important;
    }
}
/* tombol filter segmen boleh turun baris, tidak mendorong tabel ke samping */
.fup-segmen {
    flex-wrap: wrap;
}
/* Kelas .cari-merged (kotak cari satu kolom) sekarang ada di style.css karena
   dipakai juga oleh halaman Pengiriman. */
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4 fup-halaman">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3 fup-judul-baris">
        <div>
            <h4 class="mb-0 fw-bold fup-judul">
                <i class="fal fa-tasks text-primary me-2"></i> Pusat Follow-up & Reminder
            </h4>
            <p class="text-muted small mb-0 fup-teks">Kelola tindakan follow-up customer, penagihan invoice, dan pengingat jadwal pakaian penting.</p>
        </div>
        <div class="d-flex align-items-center gap-2 fup-judul-aksi">
            <?= $this->include('admin/crm/tombol_wa') ?>
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

    <!-- Sub Nav Tabs Follow-up -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom crm-subnav">
            <ul class="nav nav-tabs m-0">
                <li class="nav-item">
                    <a class="nav-link fw-semibold <?= $tab === 'tagihan' ? 'active' : '' ?>" href="<?= site_url('admin/crm/followup?tab=tagihan') ?>">
                        <i class="fal fa-money-bill-wave me-2 text-danger"></i> Follow-up Tagihan Invoice
                        <span class="badge bg-danger text-white ms-1"><?= number_format($totalInvoicesTagihan ?? count($invoicesTagihan)) ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold <?= $tab === 'reminder' ? 'active' : '' ?>" href="<?= site_url('admin/crm/followup?tab=reminder') ?>">
                        <i class="fal fa-bell me-2 text-warning"></i> Pengingat Otomatis
                        <span class="badge bg-warning text-dark ms-1"><?= count($reminders) ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body p-3 p-md-4">

            <!-- ================= SUB TAB 1: FOLLOW-UP CUSTOMER ================= -->
            <?php if ($tab === 'customer'): ?>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="btn-group btn-group-sm fup-segmen">
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
                        <div class="cari-merged" style="width: 250px; max-width: 100%;">
                            <button type="submit" class="cari-kirim" title="Cari"><i class="fal fa-search"></i></button>
                            <input type="text" name="cari" class="form-control form-control-sm" placeholder="Cari nama atau judul..." value="<?= esc($cari) ?>">
                        </div>
                    </form>
                </div>

                <?php if (empty($followupList)): ?>
                    <?php if (trim((string) $cari) !== ''): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-search fa-3x mb-3 text-secondary opacity-50"></i>
                            <h5>Tidak ada hasil untuk "<?= esc($cari) ?>"</h5>
                            <p class="mb-0">Tidak ketemu follow-up yang cocok dengan kata itu pada bagian ini.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-clipboard-check fa-3x mb-3 text-secondary opacity-50"></i>
                            <h5>Tidak ada follow-up customer</h5>
                            <p class="mb-0">Semua tugas follow-up staff telah terselesaikan atau belum ada jadwal baru.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border fup-tabel">
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
                                        <td data-label="Jatuh Tempo">
                                            <span class="fw-bold <?= $f['tanggal_jatuh_tempo'] < date('Y-m-d') && $f['status'] === 'menunggu' ? 'text-danger' : 'text-dark' ?>">
                                                <?= date('d/m/Y', strtotime($f['tanggal_jatuh_tempo'])) ?>
                                            </span>
                                            <?php if ($f['tanggal_jatuh_tempo'] < date('Y-m-d') && $f['status'] === 'menunggu'): ?>
                                                <span class="badge bg-danger ms-1">Terlambat</span>
                                            <?php elseif ($f['tanggal_jatuh_tempo'] === date('Y-m-d') && $f['status'] === 'menunggu'): ?>
                                                <span class="badge bg-warning text-dark ms-1">Hari Ini</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Customer">
                                            <a href="<?= site_url('admin/crm/pelanggan/' . $f['pelanggan_id']) ?>" class="fw-bold text-decoration-none text-primary">
                                                <?= esc($f['nama_pelanggan']) ?>
                                            </a>
                                            <?php if (!empty($f['nomor_wa'])): ?>
                                                <div class="small text-muted">
                                                    <i class="fab fa-whatsapp text-success me-1"></i><?= esc($f['nomor_wa']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Kategori Tindakan">
                                            <span class="badge bg-light text-dark border">
                                                <?= ucfirst(str_replace('_', ' ', $f['kategori'])) ?>
                                            </span>
                                        </td>
                                        <td data-label="Judul & Instruksi">
                                            <div class="fw-semibold text-dark"><?= esc($f['judul']) ?></div>
                                            <?php if (!empty($f['catatan'])): ?>
                                                <div class="small text-muted fup-catatan" style="white-space: pre-wrap;"><?= esc($f['catatan']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status">
                                            <?php if ($f['status'] === 'selesai'): ?>
                                                <span class="badge bg-success"><i class="fal fa-check me-1"></i> Selesai</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="fal fa-clock me-1"></i> Menunggu</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small text-muted" data-label="Staff PIC">
                                            <?= esc($f['nama_staff'] ?? 'Staff') ?>
                                        </td>
                                        <td class="text-center" data-label="Aksi">
                                            <a href="<?= site_url('admin/crm/pelanggan/' . $f['pelanggan_id']) ?>" class="btn btn-sm btn-outline-primary me-2" title="Buka Profil 360">
                                                <i class="fal fa-id-card"></i>
                                            </a>
                                            <?php if (!empty($f['nomor_wa'])): ?>
                                                <a href="<?= site_url('admin/crm/chat?nomor=' . $f['nomor_wa']) ?>" class="btn btn-sm btn-outline-success me-2" title="Chat WhatsApp">
                                                    <i class="fab fa-whatsapp"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($f['status'] === 'menunggu'): ?>
                                                <form action="<?= site_url('admin/crm/followup/selesai/' . $f['id_followup']) ?>" method="post" class="d-inline" onsubmit="return confirm('Tandai follow-up ini telah selesai?');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-sm btn-success" title="Tandai Selesai">
                                                        <i class="fal fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <!-- ================= SUB TAB 2: FOLLOW-UP TAGIHAN ================= -->
            <?php elseif ($tab === 'tagihan'): ?>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <p class="text-muted small mb-0 fup-teks">
                        Daftar invoice yang statusnya belum lunas (Kredit, Belum Bayar, atau Menunggu Konfirmasi).
                        <?php if (!empty($totalInvoicesTagihan) && $totalInvoicesTagihan > count($invoicesTagihan)): ?>
                            Menampilkan <?= count($invoicesTagihan) ?> dari <strong><?= number_format($totalInvoicesTagihan) ?></strong> total invoice pending.
                        <?php endif; ?>
                    </p>
                    <form action="<?= site_url('admin/crm/followup') ?>" method="get" class="d-flex gap-2">
                        <input type="hidden" name="tab" value="tagihan">
                        <div class="cari-merged" style="width: 250px; max-width: 100%;">
                            <button type="submit" class="cari-kirim" title="Cari"><i class="fal fa-search"></i></button>
                            <input type="text" name="cari" class="form-control form-control-sm" placeholder="Cari invoice / pelanggan..." value="<?= esc($cari) ?>">
                        </div>
                    </form>
                </div>

                <?php if (empty($invoicesTagihan)): ?>
                    <?php if (trim((string) $cari) !== ''): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-search fa-3x mb-3 text-secondary opacity-50"></i>
                            <h5>Tidak ada hasil untuk "<?= esc($cari) ?>"</h5>
                            <p class="mb-0">Tidak ketemu invoice yang cocok dengan kata itu. Cek ejaan namanya atau nomornya.</p>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fal fa-check-circle fa-3x mb-3 text-success"></i>
                            <h5>Semua Tagihan Lunas!</h5>
                            <p class="mb-0">Tidak ada piutang invoice pending saat ini.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle border fup-tabel">
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
                                        <td data-label="Invoice">
                                            <span class="fw-bold text-dark">#<?= esc($inv['seri']) ?></span>
                                            <div class="small text-muted"><?= date('d/m/Y', strtotime($inv['tanggal_pesan'])) ?></div>
                                        </td>
                                        <td data-label="Pelanggan">
                                            <a href="<?= site_url('admin/crm/pelanggan/' . $inv['id_pelanggan']) ?>" class="fw-bold text-decoration-none">
                                                <?= esc($inv['nama_pelanggan']) ?>
                                            </a>
                                            <div class="small text-muted"><?= esc($inv['nama_juragan']) ?></div>
                                        </td>
                                        <td data-label="Nomor WhatsApp">
                                            <?php if (!empty($inv['nomor_hp'])): ?>
                                                <i class="fab fa-whatsapp text-success me-1"></i><?= esc($inv['nomor_hp']) ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Tanpa Nomor</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-semibold" data-label="Total Tagihan">
                                            Rp <?= number_format($inv['total_tagihan'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-end text-success" data-label="Telah Dibayar">
                                            Rp <?= number_format($inv['telah_dibayar'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-end text-danger fw-bold" data-label="Sisa Piutang">
                                            Rp <?= number_format($inv['sisa_tagihan'], 0, ',', '.') ?>
                                        </td>
                                        <td class="text-center" data-label="Aksi Tagih">
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

                    <?php if (!empty($totalPagesTagihan) && $totalPagesTagihan > 1) : ?>
                        <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top mt-3">
                            <div class="small text-muted mb-2 mb-md-0">
                                Halaman <?= $page ?> dari <?= $totalPagesTagihan ?> (Total <?= number_format($totalInvoicesTagihan) ?> invoice pending)
                            </div>
                            <ul class="pagination pagination-sm mb-0">
                                <?php if ($page > 1) : ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= site_url('admin/crm/followup?tab=tagihan&page=' . ($page - 1) . '&cari=' . urlencode($cari)) ?>">« Sebelumnya</a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end   = min($totalPagesTagihan, $page + 2);
                                for ($i = $start; $i <= $end; $i++) : ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="<?= site_url('admin/crm/followup?tab=tagihan&page=' . $i . '&cari=' . urlencode($cari)) ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPagesTagihan) : ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?= site_url('admin/crm/followup?tab=tagihan&page=' . ($page + 1) . '&cari=' . urlencode($cari)) ?>">Berikutnya »</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <!-- ================= SUB TAB 3: REMINDER OTOMATIS ================= -->
            <?php elseif ($tab === 'reminder'): ?>
                <div class="mb-3">
                    <p class="text-muted small mb-0 fup-teks">
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
                            <div class="col-12 col-sm-6 col-lg-4">
                                <div class="card border-0 shadow-sm h-100 border-start border-4 border-<?= esc($rem['warna']) ?> reminder-kartu">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-start mb-1 gap-2">
                                            <span class="badge bg-<?= esc($rem['warna']) ?>"><?= esc($rem['status_waktu']) ?></span>
                                            <span class="text-muted small text-nowrap"><i class="fal fa-calendar-alt me-1"></i><?= date('d M Y', strtotime($rem['tanggal'])) ?></span>
                                        </div>
                                        <h6 class="fw-bold mt-2 mb-1 text-dark"><?= esc($rem['judul']) ?></h6>
                                        <p class="text-muted small mb-2"><?= esc($rem['deskripsi']) ?></p>

                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top mt-2">
                                            <a href="<?= site_url('admin/crm/pelanggan/' . $rem['pelanggan_id']) ?>" class="fw-semibold text-decoration-none small reminder-nama">
                                                <i class="fal fa-user me-1"></i><?= esc($rem['nama_pelanggan']) ?>
                                            </a>
                                            <div class="d-flex gap-1 ms-auto">
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



<!-- Modal Kirim Tagihan WhatsApp -->
<div class="modal fade" id="modalTagihanWa" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
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
