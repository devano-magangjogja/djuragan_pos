<?php
/**
 * Variabel di bawah disuntik runtime oleh view() lewat extract() di framework;
 * dideklarasikan di sini agar analyzer (Intelephense P1008) tidak salah lapor.
 *
 * @var string $cari        kata kunci pencarian dari query string
 * @var array  $invoices    daftar invoice belum lunas (satu halaman)
 * @var int    $totalTagihan total seluruh pesanan belum lunas
 * @var int    $page        nomor halaman aktif (1-based)
 * @var int    $totalPages  jumlah halaman
 * @var string $template    isi pesan template WhatsApp
 * @var array  $toko         id_juragan => nama toko (pilihan toko di form catatan)
 */
?>
<?= $this->extend('template/main') ?>

<?= $this->section('css') ?>
<style>
/* ikon cari ikut di dalam field, bukan kotak terpisah di kirinya */
.form-control.crm-cari {
    padding-left: 2.1rem !important;
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Follow-up Tagihan Belum Lunas</h1>
        </div>
    </div>


    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="<?= site_url('admin/crm/tagihan') ?>" class="row g-2 align-items-center">
                <div class="col-8 col-md-5">
                    <div class="position-relative">
                        <i class="fal fa-search text-muted position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                        <input type="text" name="cari" class="form-control crm-cari" placeholder="Cari nomor invoice, nama, atau no HP..." value="<?= esc($cari) ?>">
                    </div>
                </div>
                <div class="col-4 col-md-auto d-flex flex-column flex-md-row gap-2">
                    <button type="submit" class="btn btn-dark w-100"><i class="fal fa-filter me-1"></i> Filter</button>
                    <?php if (!empty($cari)) : ?>
                        <a href="<?= site_url('admin/crm/tagihan') ?>" class="btn btn-outline-secondary w-100">Reset</a>
                    <?php endif; ?>
                </div>
                <div class="col text-end text-muted small">
                    Ditemukan <strong><?= number_format($totalTagihan) ?></strong> pesanan belum lunas
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice</th>
                        <th>Juragan</th>
                        <th>Pelanggan</th>
                        <th>Nomor HP</th>
                        <th>Total Belanja</th>
                        <th>Telah Dibayar</th>
                        <th>Sisa Tagihan</th>
                        <th class="text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)) : ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <?php if (trim((string) $cari) !== '') : ?>
                                    <i class="fal fa-search fa-3x mb-2 d-block"></i>
                                    Tidak ada hasil untuk "<?= esc($cari) ?>".
                                <?php else : ?>
                                    <i class="fal fa-check-circle fa-3x text-success mb-2 d-block"></i>
                                    Tidak ada antrean tagihan yang perlu difollow-up.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($invoices as $inv) : ?>
                            <tr>
                                <td>
                                    <strong><?= esc($inv['seri']) ?></strong>
                                    <div class="small text-muted"><?= date('d M Y', strtotime($inv['tanggal_pesan'])) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= esc($inv['nama_juragan']) ?></span>
                                </td>
                                <td>
                                    <strong><?= esc($inv['nama_pelanggan']) ?></strong>
                                </td>
                                <td>
                                    <span class="font-monospace"><?= esc($inv['nomor_hp'] ?: '-') ?></span>
                                </td>
                                <td>Rp <?= number_format($inv['total_tagihan'], 0, ',', '.') ?></td>
                                <td>Rp <?= number_format($inv['telah_dibayar'], 0, ',', '.') ?></td>
                                <td>
                                    <span class="badge bg-danger fs-6">
                                        Rp <?= number_format($inv['sisa_tagihan'], 0, ',', '.') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <button type="button" 
                                            class="btn btn-sm btn-success btn-followup text-nowrap"
                                            data-id="<?= $inv['id_invoice'] ?>"
                                            data-seri="<?= esc($inv['seri']) ?>"
                                            data-nama="<?= esc($inv['nama_pelanggan']) ?>"
                                            data-hp="<?= esc($inv['nomor_hp']) ?>"
                                            data-pelanggan-id="<?= $inv['id_pelanggan'] ?>" data-juragan="<?= (int) ($inv['juragan_id'] ?? 0) ?>"
                                            data-total="<?= number_format($inv['total_tagihan'], 0, ',', '.') ?>"
                                            data-sisa="<?= number_format($inv['sisa_tagihan'], 0, ',', '.') ?>"
                                            data-link="<?= site_url('download/invoice/' . $inv['seri']) ?>">
                                        <i class="fal fa-note-sticky me-1"></i> Catat Penagihan
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1) : ?>
            <?php
            $akhir = (int) $totalPages;

            // kunci pencarian ikut dibawa, kalau tidak halaman 2 diam-diam kembali
            // menampilkan daftar tanpa filter
            $tautanHalaman = static function (int $n) use ($cari): string {
                $query = ['page' => $n];

                if ($cari !== '') {
                    $query['cari'] = $cari;
                }

                return site_url('admin/crm/tagihan?' . http_build_query($query));
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
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3 crm-pager">
                <div class="small text-muted">
                    Halaman <?= $page ?> dari <?= $akhir ?> (Total <?= number_format($totalTagihan) ?> pesanan)
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

<!-- Modal Catat Penagihan -->
<div class="modal fade" id="modalFollowup" tabindex="-1" aria-labelledby="modalFollowupLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <?= form_open('admin/tugas/catatan', ['id' => 'formCatatTagihan']) ?>
            <div class="modal-header">
                <h5 class="modal-title" id="modalFollowupLabel">
                    <i class="fal fa-note-sticky me-2"></i>Catat Penagihan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nomor Invoice</label>
                        <input type="text" id="targetSeri" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nama Pelanggan</label>
                        <input type="text" id="targetNama" class="form-control" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Nomor WhatsApp</label>
                        <input type="text" id="targetHp" class="form-control font-monospace" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Sisa Tagihan</label>
                        <input type="text" id="targetSisa" class="form-control text-danger fw-bold" readonly>
                    </div>
                </div>

                <input type="hidden" name="pelanggan_id" id="targetPelangganId" value="">
                <input type="hidden" name="seri" id="targetSeriVal" value="">

                <div class="mb-3">
                    <label class="form-label small fw-bold">Draf pesan pengingat</label>
                    <textarea id="targetPesan" class="form-control font-monospace" rows="5" readonly></textarea>
                    <div class="d-flex align-items-center gap-2 flex-wrap mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill text-nowrap" id="btnSalinPesan">
                            <i class="fal fa-copy me-1"></i> <span id="btnSalinText">Salin draf</span>
                        </button>
                        <span class="small text-muted">Pesan tidak dikirim otomatis — salin drafnya lalu kirim dari aplikasi WA, lalu catat hasilnya di sini.</span>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Ringkasan percakapan <span class="text-danger">*</span></label>
                    <textarea name="ringkasan" id="targetRingkasan" class="form-control" rows="3" required placeholder="mis. sudah ditelepon, janji bayar lusa"></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Tindakan lanjutan</label>
                        <input type="text" name="tindakan" class="form-control" maxlength="200" placeholder="mis. Tagih sisa #AM1234">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Jadwal ulang</label>
                        <input type="date" name="tenggat" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Prioritas</label>
                        <select name="prioritas" class="form-select">
                            <option value="rendah">Rendah</option>
                            <option value="normal" selected>Normal</option>
                            <option value="tinggi">Tinggi</option>
                            <option value="mendesak">Mendesak</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Toko</label>
                        <?php if (($toko ?? []) !== []) : ?>
                            <select class="form-select form-select-sm" name="juragan_id" id="targetToko" required>
                                <?php foreach ($toko as $id_toko => $nama_t) : ?>
                                    <option value="<?= (int) $id_toko ?>"><?= esc($nama_t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else : ?>
                            <div class="small text-danger">
                                <i class="fal fa-triangle-exclamation me-1"></i> Akun ini tidak memegang toko, catatan tidak bisa disimpan.
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Label pelanggan (opsional)</label>
                        <input type="text" name="nama_tag_baru" class="form-control" maxlength="60" placeholder="mis. piutang macet">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-sm btn-primary"><i class="fal fa-save me-1"></i> Simpan catatan</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script defer src="https://kit.fontawesome.com/22a9d93fa2.js" data-auto-replace-svg="nest" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    var rawTemplate = <?= json_encode($template) ?>;
    var modalEl     = document.getElementById('modalFollowup');
    var modal       = new bootstrap.Modal(modalEl);

    function isi(id, nilai) {
        var el = document.getElementById(id);

        if (el !== null) {
            el.value = nilai;
        }
    }

    // draf hanya untuk disalin manual; jalur kirim otomatis sudah dicabut
    function draf(d) {
        return String(rawTemplate || '')
            .replace(/\{nama\}/g, d.nama)
            .replace(/\{invoice\}/g, d.seri)
            .replace(/\{total\}/g, d.total)
            .replace(/\{sisa\}/g, d.sisa)
            .replace(/\{link_invoice\}/g, d.link);
    }

    function tandaiTersalin() {
        var label = document.getElementById('btnSalinText');
        label.textContent = 'Tersalin';
        window.setTimeout(function() { label.textContent = 'Salin draf'; }, 1500);
    }

    function salinLewatKolom(teks) {
        var area = document.getElementById('targetPesan');
        area.select();
        area.setSelectionRange(0, teks.length);

        try {
            document.execCommand('copy');
        } catch (err) {
            // clipboard ditolak browser; drafnya tetap terseleksi dan bisa disalin tangan
        }

        if (window.getSelection) {
            window.getSelection().removeAllRanges();
        }
    }

    function salinDraf() {
        var teks = document.getElementById('targetPesan').value;

        if (teks === '') {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(teks).then(tandaiTersalin, function() {
                salinLewatKolom(teks);
                tandaiTersalin();
            });

            return;
        }

        salinLewatKolom(teks);
        tandaiTersalin();
    }

    document.querySelectorAll('.btn-followup').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var d = btn.dataset;

            isi('targetPelangganId', d.pelangganId || ''); isi('targetToko', d.juragan || '');
            isi('targetSeriVal', d.seri || '');
            isi('targetSeri', d.seri || '');
            isi('targetNama', d.nama || '');
            isi('targetHp', d.hp || '');
            isi('targetSisa', d.sisa ? 'Rp ' + d.sisa : '');
            isi('targetPesan', draf({
                nama: d.nama || '',
                seri: d.seri || '',
                total: d.total || '',
                sisa: d.sisa || '',
                link: d.link || ''
            }));

            // catatan nota sebelumnya tidak boleh ikut terbawa ke nota berikutnya
            ['ringkasan', 'tindakan', 'tenggat', 'nama_tag_baru'].forEach(function(nama) {
                var el = modalEl.querySelector('[name="' + nama + '"]');

                if (el !== null) {
                    el.value = '';
                }
            });

            document.getElementById('btnSalinText').textContent = 'Salin draf';

            modal.show();
        });
    });

    var btnSalin = document.getElementById('btnSalinPesan');

    if (btnSalin !== null) {
        btnSalin.addEventListener('click', salinDraf);
    }
});
</script>
<?= $this->endSection() ?>
