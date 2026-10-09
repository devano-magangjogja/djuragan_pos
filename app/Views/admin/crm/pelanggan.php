<?= $this->extend('template/main') ?>

<?= $this->section('content') ?>
<?= $this->include('admin/navbar') ?>

<div class="container-xxl py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0 fw-bold">
                <i class="fal fa-users text-primary me-2"></i> Data Pelanggan CRM
            </h4>
            <p class="text-muted small mb-0">Kelola profil customer, segmentasi perilaku, status interaksi, dan tag pelanggan.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= site_url('admin/crm/duplikat') ?>" class="btn btn-outline-danger btn-sm fw-semibold">
                <i class="fal fa-clone me-1"></i> Cek Duplikat
            </a>
            <?= $this->include('admin/crm/tombol_wa') ?>
        </div>
    </div>

    <?= $this->include('admin/crm/nav_crm') ?>

    <!-- Filter & Pencarian Diperkuat -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="<?= site_url('admin/crm/pelanggan') ?>" class="row g-2 align-items-center">
                <!-- Search input -->
                <div class="col-lg-3 col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fal fa-search text-muted"></i></span>
                        <input type="text" name="cari" class="form-control" placeholder="Cari nama, WA, ID, atau kota..." value="<?= esc($cari) ?>">
                    </div>
                </div>

                <!-- Status CRM -->
                <div class="col-lg-2 col-md-3 col-6">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="semua" <?= $status === 'semua' ? 'selected' : '' ?>>Semua Status CRM</option>
                        <option value="prospek" <?= $status === 'prospek' ? 'selected' : '' ?>>Prospek / Belum Closing</option>
                        <option value="baru" <?= $status === 'baru' ? 'selected' : '' ?>>Customer Baru</option>
                        <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Customer Aktif</option>
                        <option value="loyal" <?= $status === 'loyal' ? 'selected' : '' ?>>Customer Loyal</option>
                        <option value="tidak_aktif" <?= $status === 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif / Pasif</option>
                    </select>
                </div>

                <!-- Segmen Perilaku -->
                <div class="col-lg-3 col-md-3 col-6">
                    <select name="segmen" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="semua" <?= $segmen === 'semua' ? 'selected' : '' ?>>Semua Segmen Perilaku</option>
                        <option value="sewa" <?= $segmen === 'sewa' ? 'selected' : '' ?>>Sering Sewa / Rental</option>
                        <option value="custom" <?= $segmen === 'custom' ? 'selected' : '' ?>>Custom Order / Pembuatan</option>
                        <option value="repeat" <?= $segmen === 'repeat' ? 'selected' : '' ?>>Repeat Order (> 1x)</option>
                        <option value="sekali" <?= $segmen === 'sekali' ? 'selected' : '' ?>>Order Sekali</option>
                        <option value="high_spender" <?= $segmen === 'high_spender' ? 'selected' : '' ?>>High Spender (> Rp 2 Jt)</option>
                        <option value="piutang" <?= $segmen === 'piutang' ? 'selected' : '' ?>>Memiliki Piutang</option>
                        <option value="pasif" <?= $segmen === 'pasif' ? 'selected' : '' ?>>Pasif (> 60 Hari)</option>
                        <option value="prospek" <?= $segmen === 'prospek' ? 'selected' : '' ?>>Belum Pernah Order (0 Transaksi)</option>
                    </select>
                </div>

                <!-- Filter Tag -->
                <div class="col-lg-2 col-md-4 col-6">
                    <select name="tag_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="0">Semua Tag</option>
                        <?php foreach ($allTags as $t): ?>
                            <option value="<?= $t['id_tag'] ?>" <?= $tagId == $t['id_tag'] ? 'selected' : '' ?>>
                                Tag: <?= esc($t['nama_tag']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="col-lg-2 col-md-4 col-6 d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold"><i class="fal fa-filter me-1"></i> Filter</button>
                    <?php if (!empty($cari) || $segmen !== 'semua' || $status !== 'semua' || $tagId > 0) : ?>
                        <a href="<?= site_url('admin/crm/pelanggan') ?>" class="btn btn-outline-secondary btn-sm" title="Reset Filter"><i class="fal fa-undo"></i></a>
                    <?php endif; ?>
                </div>

                <div class="col-12 text-muted small mt-2">
                    Ditemukan: <strong><?= number_format($totalData) ?></strong> customer
                    <?php if ($tagId > 0 || $status !== 'semua' || $segmen !== 'semua'): ?>
                        (difilter)
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Data Pelanggan -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 220px;">Customer & Status</th>
                        <th>Kontak WhatsApp</th>
                        <th>Lokasi</th>
                        <th class="text-center">Order POS</th>
                        <th class="text-end">Total Belanja</th>
                        <th class="text-end">Sisa Piutang</th>
                        <th>Order Terakhir</th>
                        <th class="text-center" style="width: 140px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daftarPelanggan)) : ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fal fa-users-slash fa-3x text-muted mb-2 d-block opacity-50"></i>
                                Tidak ada data customer yang sesuai dengan filter atau kata kunci pencarian.
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($daftarPelanggan as $p) : ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-primary bg-opacity-10 text-primary fw-bold rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 36px; height: 36px; font-size: 13px;">
                                            <?= strtoupper(substr($p['nama_pelanggan'], 0, 2)) ?>
                                        </div>
                                        <div>
                                            <a href="<?= site_url('admin/crm/pelanggan/' . $p['id_pelanggan']) ?>" class="fw-bold text-dark text-decoration-none d-block">
                                                <?= esc($p['nama_pelanggan']) ?>
                                            </a>
                                            <!-- Status CRM badge -->
                                            <?php
                                            $st = $p['status_crm'] ?? 'prospek';
                                            $badgeInfo = match ($st) {
                                                'loyal'       => ['badge bg-warning text-dark', 'Loyal'],
                                                'aktif'       => ['badge bg-success', 'Aktif'],
                                                'baru'        => ['badge bg-info text-dark', 'Baru'],
                                                'tidak_aktif' => ['badge bg-secondary', 'Pasif'],
                                                default       => ['badge bg-light text-muted border', 'Prospek'],
                                            };
                                            ?>
                                            <span class="<?= $badgeInfo[0] ?>" style="font-size: 10px;"><?= $badgeInfo[1] ?></span>

                                            <!-- Tags -->
                                            <?php if (!empty($p['tags'])): ?>
                                                <?php foreach (array_slice($p['tags'], 0, 2) as $t): ?>
                                                    <span class="badge bg-<?= esc($t['warna']) ?>" style="font-size: 10px;"><?= esc($t['nama_tag']) ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($p['tags']) > 2): ?>
                                                    <span class="badge bg-light text-muted border" style="font-size: 10px;">+<?= count($p['tags']) - 2 ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($p['nomor_utama'])): ?>
                                        <a href="https://wa.me/<?= preg_replace('/[^\d]/', '', $p['nomor_utama']) ?>" target="_blank" class="text-decoration-none text-success font-monospace fw-semibold small">
                                            <i class="fab fa-whatsapp me-1"></i><?= esc($p['nomor_utama']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small fst-italic">Belum ada nomor</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= esc($p['kota'] ?: ($p['provinsi'] ?: '-')) ?>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-dark"><?= number_format($p['jumlah_order']) ?>x</span>
                                    <div class="text-muted small" style="font-size: 11px;">(<?= number_format($p['qty']) ?> pcs)</div>
                                </td>
                                <td class="text-end fw-semibold">
                                    Rp <?= number_format($p['nilai'], 0, ',', '.') ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($p['sisa'] > 0) : ?>
                                        <span class="badge bg-danger">
                                            Rp <?= number_format($p['sisa'], 0, ',', '.') ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="badge bg-light text-success border">Lunas</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= !empty($p['order_terakhir']) ? date('d M Y', strtotime($p['order_terakhir'])) : '<span class="text-muted fst-italic">Belum ada</span>' ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= site_url('admin/crm/pelanggan/' . $p['id_pelanggan']) ?>" class="btn btn-primary" title="Buka Profil Customer 360">
                                            <i class="fal fa-id-card me-1"></i> 360°
                                        </a>
                                        <?php if (!empty($p['nomor_utama'])): ?>
                                            <a href="<?= site_url('admin/crm/chat?nomor=' . $p['nomor_utama']) ?>" class="btn btn-outline-success" title="Chat WhatsApp">
                                                <i class="fab fa-whatsapp"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
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

            $tautanHalaman = static function (int $n) use ($segmen, $status, $tagId, $cari): string {
                return site_url('admin/crm/pelanggan?' . http_build_query([
                    'page'   => $n,
                    'segmen' => $segmen,
                    'status' => $status,
                    'tag_id' => $tagId,
                    'cari'   => $cari,
                ]));
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
                    Halaman <?= $page ?> dari <?= $akhir ?> (Total <?= number_format($totalData) ?> data)
                </div>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($page > 1) : ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= $tautanHalaman($page - 1) ?>">« Sebelumnya</a>
                        </li>
                    <?php else : ?>
                        <li class="page-item disabled">
                            <span class="page-link">« Sebelumnya</span>
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
                            <a class="page-link" href="<?= $tautanHalaman($page + 1) ?>">Berikutnya »</a>
                        </li>
                    <?php else : ?>
                        <li class="page-item disabled">
                            <span class="page-link">Berikutnya »</span>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
