<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
    /* Badge ini duduk semeja dengan tombol, jadi kotak dan hurufnya
       mengikuti .btn-sm, bukan .badge. */
    .card-header .badge.jumlah-grup {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.32rem 0.7rem !important;
        font-size: 0.78rem !important;
        line-height: 1.4;
        letter-spacing: normal !important;
        border-radius: 8px !important;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0 fw-bold">
                <i class="fal fa-clone text-primary me-2"></i> Deteksi & Penggabungan Akun Duplikat
            </h4>
            <p class="text-muted small mb-0">Mendeteksi customer ganda berdasarkan kesamaan nomor WhatsApp atau kemiripan nama, serta fitur merge tanpa kehilangan riwayat transaksi.</p>
        </div>
    </div>


    <div class="alert alert-info border-0 shadow-sm mb-4">
        <div class="d-flex align-items-start gap-2">
            <i class="fal fa-info-circle fs-5 mt-1 text-primary"></i>
            <div>
                <strong>Penting tentang Penggabungan (Merge):</strong>
                Ketika dua akun digabungkan, seluruh data invoice POS, mutasi pembayaran, riwayat chat WhatsApp, aktivitas timeline, catatan staff, dan tag dari akun kedua akan otomatis dialihkan ke akun utama yang Anda pertahankan. Akun kedua akan dinonaktifkan sehingga data CRM tetap akurat dan tidak ada transaksi yang hilang.
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">Daftar Akun yang Terindikasi Duplikat</h6>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border jumlah-grup"><?= $totalDuplikat ?> Grup Duplikat Terdeteksi</span>
                <button class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalManualMerge">
                    <i class="fal fa-code-merge me-1"></i> Gabungkan
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($duplikatList)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fal fa-check-circle fa-3x mb-3 text-success"></i>
                    <h5>Database Bersih!</h5>
                    <p class="mb-0">Tidak ditemukan nomor WhatsApp ganda atau nama ganda di database customer.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kriteria Duplikat</th>
                                <th>Kunci Kembar</th>
                                <th>Contoh Nama Terdaftar</th>
                                <th class="text-center">Jumlah Akun</th>
                                <th>ID Akun Terlibat</th>
                                <th class="text-center">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($duplikatList as $d): ?>
                                <tr>
                                    <td>
                                        <?php if ($d['jenis'] === 'nomor'): ?>
                                            <span class="badge bg-success"><i class="fab fa-whatsapp me-1"></i> Nomor WA Sama</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark"><i class="fal fa-user me-1"></i> Nama Mirip</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code class="fw-bold text-dark"><?= esc($d['kunci']) ?></code>
                                    </td>
                                    <td>
                                        <strong><?= esc($d['contoh_nama']) ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger rounded-pill px-2 py-1"><?= (int) $d['jumlah_akun'] ?> akun</span>
                                    </td>
                                    <td>
                                        <?php 
                                        $ids = explode(',', $d['id_akun']);
                                        foreach ($ids as $idItem): 
                                        ?>
                                            <a href="<?= site_url('admin/crm/pelanggan/' . trim($idItem)) ?>" target="_blank" class="badge bg-light text-primary border text-decoration-none me-1">
                                                #<?= esc(trim($idItem)) ?> <i class="fal fa-external-link-alt" style="font-size: 9px;"></i>
                                            </a>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary fw-semibold btn-buka-merge"
                                                data-ids="<?= esc($d['id_akun']) ?>"
                                                data-nama="<?= esc($d['contoh_nama']) ?>"
                                                data-kunci="<?= esc($d['kunci']) ?>">
                                            <i class="fal fa-code-merge me-1"></i> Review & Gabungkan
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($totalPages > 1) : ?>
            <?php
            $akhir = (int) $totalPages;

            $tautanHalaman = static function (int $n): string {
                return site_url('admin/crm/duplikat?' . http_build_query(['page' => $n]));
            };

            // Jendela 5 nomor di sekitar halaman aktif, plus halaman pertama/terakhir
            // dengan penanda "..." supaya halaman terjauh tetap bisa diklik.
            $dari   = max(1, min($page - 2, $akhir - 4));
            $sampai = min($akhir, $dari + 4);

            $nomorHalaman = [];
            if ($dari > 1) {
                $nomorHalaman[] = 1;
                if ($dari > 2) {
                    $nomorHalaman[] = null;
                }
            }
            for ($n = $dari; $n <= $sampai; $n++) {
                $nomorHalaman[] = $n;
            }
            if ($sampai < $akhir) {
                if ($sampai < $akhir - 1) {
                    $nomorHalaman[] = null;
                }
                $nomorHalaman[] = $akhir;
            }
            ?>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                <div class="small text-muted">
                    Halaman <?= $page ?> dari <?= $akhir ?> (Total <?= number_format($totalDuplikat) ?> grup)
                </div>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1) : ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $tautanHalaman($page - 1) ?>">&laquo; Sebelumnya</a>
                        </li>
                    <?php else : ?>
                        <li class="page-item disabled">
                            <span class="page-link">&laquo; Sebelumnya</span>
                        </li>
                    <?php endif; ?>

                    <?php foreach ($nomorHalaman as $n) : ?>
                        <?php if ($n === null) : ?>
                            <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                        <?php elseif ($n === $page) : ?>
                            <li class="page-item active" aria-current="page"><span class="page-link"><?= $n ?></span></li>
                        <?php else : ?>
                            <li class="page-item"><a class="page-link" href="<?= $tautanHalaman($n) ?>"><?= $n ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($page < $akhir) : ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $tautanHalaman($page + 1) ?>">Berikutnya &raquo;</a>
                        </li>
                    <?php else : ?>
                        <li class="page-item disabled">
                            <span class="page-link">Berikutnya &raquo;</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Merge Customer -->
<div class="modal fade" id="modalMergeCustomer" tabindex="-1">
    <div class="modal-dialog">
        <form id="formProsesMerge">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Penggabungan Akun Customer</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Pilih satu akun yang akan dipertahankan sebagai profil utama. Seluruh riwayat transaksi dan pesan dari akun duplikat akan disatukan ke akun utama tersebut.
                    </p>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ID Customer Utama (Yang Dipertahankan)</label>
                        <select name="id_utama" id="selectIdUtama" class="form-select form-select-sm" required>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ID Customer Duplikat (Yang Akan Digabung & Dinonaktifkan)</label>
                        <select name="id_duplikat" id="selectIdDuplikat" class="form-select form-select-sm" required>
                        </select>
                    </div>
                    <div id="alertMerge" class="d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" id="btnProsesMergeSubmit" class="btn btn-danger btn-sm fw-semibold">
                        <i class="fal fa-code-merge me-1"></i> Konfirmasi Gabungkan Akun
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Manual Merge -->
<div class="modal fade" id="modalManualMerge" tabindex="-1">
    <div class="modal-dialog">
        <form id="formManualMerge">
            <?= csrf_field() ?>
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Gabungkan Akun Manual</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ID Customer Utama</label>
                        <input type="number" name="id_utama" class="form-control form-control-sm" placeholder="Contoh: 105" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">ID Customer Duplikat</label>
                        <input type="number" name="id_duplikat" class="form-control form-control-sm" placeholder="Contoh: 842" required>
                    </div>
                    <div id="alertManualMerge" class="d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" id="btnManualMergeSubmit" class="btn btn-primary btn-sm">Gabungkan Sekarang</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalMerge = new bootstrap.Modal(document.getElementById('modalMergeCustomer'));

    document.querySelectorAll('.btn-buka-merge').forEach(btn => {
        btn.addEventListener('click', function() {
            const rawIds = this.dataset.ids.split(',');
            const selUtama = document.getElementById('selectIdUtama');
            const selDup = document.getElementById('selectIdDuplikat');
            selUtama.innerHTML = '';
            selDup.innerHTML = '';

            rawIds.forEach((id, idx) => {
                const opt1 = document.createElement('option');
                opt1.value = id.trim();
                opt1.text = 'Customer #' + id.trim() + (idx === 0 ? ' (Pertama Terdaftar)' : '');
                selUtama.appendChild(opt1);

                const opt2 = document.createElement('option');
                opt2.value = id.trim();
                opt2.text = 'Customer #' + id.trim();
                if (idx === 1) opt2.selected = true;
                selDup.appendChild(opt2);
            });

            document.getElementById('alertMerge').className = 'd-none';
            modalMerge.show();
        });
    });

    function handleMergeForm(formId, alertId, btnId) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById(btnId);
            const alertBox = document.getElementById(alertId);
            btn.disabled = true;
            btn.innerHTML = '<i class="fal fa-spinner fa-spin me-1"></i> Memproses Merge...';

            fetch('<?= site_url('admin/crm/duplikat/merge') ?>', {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = 'Konfirmasi Gabungkan Akun';
                if (data.status === 'success') {
                    alertBox.className = 'alert alert-success mt-2 small';
                    alertBox.innerHTML = '<i class="fal fa-check-circle me-1"></i> ' + data.message;
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    alertBox.className = 'alert alert-danger mt-2 small';
                    alertBox.innerHTML = '<i class="fal fa-exclamation-triangle me-1"></i> ' + data.message;
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = 'Konfirmasi Gabungkan Akun';
                alertBox.className = 'alert alert-danger mt-2 small';
                alertBox.innerHTML = 'Terjadi kesalahan sistem.';
            });
        });
    }

    handleMergeForm('formProsesMerge', 'alertMerge', 'btnProsesMergeSubmit');
    handleMergeForm('formManualMerge', 'alertManualMerge', 'btnManualMergeSubmit');
});
</script>
<?= $this->endSection() ?>
