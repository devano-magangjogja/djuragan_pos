/*
 * Panel "Foto Orderan" di form Tulis dan form Sunting.
 *
 * Foto sengaja TIDAK ikut form.serialize(). Orderan baru belum punya id saat
 * form dibuka, jadi berkasnya ditahan dulu di peramban lalu diunggah sendiri ke
 * api/foto/unggah/<id> setelah orderannya tersimpan. Di form Sunting id-nya
 * sudah ada, jadi berkas langsung naik.
 *
 * Peramban memperkecil dan menuliskan ulang gambar lewat canvas: kiriman jadi
 * ringan dan EXIF (termasuk koordinat HP) hilang sebelum meninggalkan mesin.
 * Server tetap memvalidasi ulang, jadi script yang gagal dimuat pun tidak
 * membuka celah.
 */
(function ($) {
    'use strict';

    var SISI = 1600;
    var MUTU = 0.85;
    var ANTRE = [];

    function $panel() {
        return $('.foto-panel').first();
    }

    function kunci() {
        return Math.round(new Date().getTime() + (Math.random() * 100));
    }

    /** Semua kartu yang menempati jatah: tersimpan, antrean, dan yang masih diolah. */
    function jumlah() {
        return $panel().find('.foto-kartu').length;
    }

    function hitung() {
        $panel().find('.jumlah-foto').text(jumlah());
    }

    function sisa() {
        return Math.max(0, Number($panel().attr('data-maks')) - jumlah());
    }

    function kartu(atribut) {
        return $('<div class="col-4 col-sm-3 col-md-2 foto-kartu proses"></div>')
            .attr(atribut || {})
            .append(
                $('<div class="foto-kotak"></div>').append(
                    $('<a class="foto-buka" target="_blank" rel="noopener"></a>').append(
                        $('<img class="foto-gambar" alt="Foto orderan">')
                    ),
                    $('<button type="button" class="foto-buang" aria-label="Hapus foto" title="Hapus foto"><i class="fal fa-trash-alt"></i></button>'),
                    $('<span class="foto-tunggu" aria-hidden="true"><i class="fal fa-spinner"></i></span>')
                ),
                $('<div class="foto-nama small"></div>')
            );
    }

    /** Gambar siap tampil + nama aslinya; kelas proses dibuang. */
    function isi($k, alamat, nama, penuh) {
        $k.find('.foto-buka').attr('href', penuh || alamat);
        $k.find('.foto-gambar').attr('src', alamat);
        $k.find('.foto-nama').text(nama || '').attr('title', nama || '');
        $k.removeClass('proses');

        hitung();
    }

    function pesanGalat(pesan) {
        var $kotak = $panel().find('.foto-galat');

        $kotak.empty();

        if (pesan.length === 0) {
            $kotak.addClass('d-none');

            return;
        }

        $kotak
            .append('<i class="fal fa-info-circle me-1"></i>')
            .append(pesan.map(function (p) {
                return $('<div></div>').text(p);
            }))
            .removeClass('d-none');
    }

    /** Pesan dari server; jawaban bisa berupa teks biasa kalau bukan JSON. */
    function teks(xhr) {
        var j = xhr.responseJSON;

        if (typeof j === 'string') {
            return j;
        }

        if (j && typeof j.status === 'string') {
            return j.status;
        }

        if (xhr.status === 0) {
            return 'Koneksi terputus, fotonya tidak terkirim.';
        }

        return 'Foto gagal diunggah, coba lagi ya.';
    }

    /**
     * Perkecil ke SISI px sisi terpanjang lalu tulis ulang lewat canvas.
     * Gambar yang tidak bisa dibuka dibiarkan apa adanya supaya server yang
     * menolak dan pesannya sampai ke pengguna.
     */
    function kecil(file) {
        var dfd = $.Deferred();
        var alamat = URL.createObjectURL(file);
        var img = new Image();

        img.onload = function () {
            URL.revokeObjectURL(alamat);

            var lebar = img.naturalWidth || img.width;
            var tinggi = img.naturalHeight || img.height;
            var skala = Math.max(lebar, tinggi) > SISI ? SISI / Math.max(lebar, tinggi) : 1;

            lebar = Math.max(1, Math.round(lebar * skala));
            tinggi = Math.max(1, Math.round(tinggi * skala));

            var kanvas = document.createElement('canvas');
            kanvas.width = lebar;
            kanvas.height = tinggi;

            var ctx = kanvas.getContext('2d');
            var jpg = file.type !== 'image/png' && file.type !== 'image/webp';

            if (jpg) {
                // JPEG tidak punya saluran alpha, bagian transparan jadi hitam tanpa latar
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, lebar, tinggi);
            }

            ctx.drawImage(img, 0, 0, lebar, tinggi);

            try {
                kanvas.toBlob(function (b) {
                    dfd.resolve(b || file);
                }, jpg ? 'image/jpeg' : file.type, MUTU);
            } catch (e) {
                dfd.resolve(file);
            }
        };

        img.onerror = function () {
            URL.revokeObjectURL(alamat);
            dfd.resolve(file);
        };

        img.src = alamat;

        return dfd.promise();
    }

    function namai(nama) {
        var n = String(nama || '').trim();

        return n === '' ? 'foto' : n.slice(0, 60);
    }

    function pilih(files) {
        var $p = $panel();
        var datang = [].slice.call(files || []).filter(function (f) {
            return /^image\/(jpeg|pjpeg|png|webp)$/.test(f.type);
        });

        // berkas yang sama harus bisa dipilih lagi tanpa memuat ulang halaman
        $p.find('.foto-input').val('');

        if (datang.length === 0) {
            pesanGalat(['Yang dipilih bukan JPG, PNG, atau WebP.']);

            return;
        }

        if (datang.length > sisa()) {
            pesanGalat(['Sisa jatah cuma ' + sisa() + ' foto, yang kelebihan belum ikut dipilih.']);
            datang = datang.slice(0, sisa());
        } else {
            pesanGalat([]);
        }

        datang.forEach(function (f) {
            var k = kunci();
            var $k = kartu({ 'data-kunci': k });

            $p.find('.foto-daftar').append($k);

            kecil(f).always(function (blob) {
                // kartunya bisa saja sudah dibuang pengguna selama gambar diolah
                if (!document.body.contains($k[0])) {
                    return;
                }

                var satu = { kunci: k, blob: blob, nama: namai(f.name) };

                if (Number($p.attr('data-invoice')) > 0) {
                    kirim($p.attr('data-invoice'), [satu], $k);

                    return;
                }

                ANTRE.push(satu);
                isi($k, URL.createObjectURL(blob), satu.nama);
            });
        });
    }

    /**
     * Unggah. `$k` diisi di form Sunting (satu kartu per berkas); di form Tulis
     * kartu pratinjau dibuang setelah antrean naik.
     */
    function kirim(id, daftar, $k) {
        var fd = new FormData();

        daftar.forEach(function (s) {
            fd.append('foto[]', s.blob, s.nama);
        });

        return $.ajax({
            url: $panel().attr('data-unggah') + '/' + id,
            type: 'post',
            data: fd,
            processData: false,
            contentType: false
        }).done(function (res) {
            if ($k) {
                $k.remove();
            } else {
                daftar.forEach(function (s) {
                    $('.foto-kartu[data-kunci="' + s.kunci + '"]').remove();
                });
                ANTRE = [];
            }

            (res.foto || []).forEach(function (f) {
                var $baru = kartu({ 'data-id': f.id_foto });

                $panel().find('.foto-daftar').append($baru);
                isi($baru, f.alamat_kecil || f.alamat, f.nama_asli, f.alamat);
            });

            pesanGalat(res.galat || []);

            return res;
        }).fail(function (xhr) {
            if ($k) {
                $k.remove();
            }

            pesanGalat([teks(xhr)]);
        });
    }

    /**
     * Foto tidak ikut naik: orderannya SUDAH tersimpan, jadi tombol Simpan
     * dikunci supaya menekan Simpan dua kali tidak melahirkan orderan kembar. Panel
     * diubah jadi mode sunting agar fotonya bisa diulang dari sini.
     */
    function tinggal(id, url) {
        var $p = $panel();

        $p.attr('data-invoice', id);

        var $b = $('#iForm').find('button[type="submit"]').first();

        if ($b.length > 0) {
            $b.prop('disabled', true).html('<i class="fal fa-check"></i> Orderan Tersimpan');

            if ($('#foto-lanjut').length === 0) {
                $('<a></a>', {
                    id: 'foto-lanjut',
                    href: url,
                    class: 'btn btn-outline-primary w-100 mt-2 rounded-[0.75rem] py-2.5 text-uppercase',
                    text: 'Lewati foto, lihat orderan'
                }).insertAfter($b);
            }
        }
    }

    /** Dipanggil script halaman: antrean naik dulu, baru pindah halaman. */
    function selesai(id, url) {
        if (ANTRE.length === 0) {
            document.location.href = url;

            return $.Deferred().resolve().promise();
        }

        var daftar = ANTRE.slice();

        return kirim(id, daftar).done(function (res) {
            if (res && (res.galat || []).length > 0) {
                tinggal(id, url);

                return;
            }

            document.location.href = url;
        }).fail(function () {
            tinggal(id, url);
        });
    }

    $(document)
        .on('click', '.buka-foto', function (e) {
            e.preventDefault();

            var $card = $(this).closest('.card'),
                $isi = $card.find('.foto-isi').first(),
                buka = $isi.hasClass('d-none');

            $isi.toggleClass('d-none', !buka);
            $(this).attr('aria-expanded', buka ? 'true' : 'false');
            $card.find('.ikon-panah-foto').toggleClass('rotate-180', buka);
        })
        .on('change', '.foto-input', function () {
            pilih(this.files);
        })
        .on('dragover', '.foto-pilih', function (e) {
            e.preventDefault();
            $(this).addClass('foto-seret');
        })
        .on('dragleave', '.foto-pilih', function (e) {
            e.preventDefault();
            $(this).removeClass('foto-seret');
        })
        .on('drop', '.foto-pilih', function (e) {
            e.preventDefault();
            $(this).removeClass('foto-seret');

            if (e.originalEvent.dataTransfer) {
                pilih(e.originalEvent.dataTransfer.files);
            }
        })
        .on('click', '.foto-buang', function () {
            var $k = $(this).closest('.foto-kartu'),
                id = $k.attr('data-id'),
                k = $k.attr('data-kunci');

            if (id) {
                $.post($panel().attr('data-hapus') + '/' + id)
                    .done(function () {
                        $k.remove();
                        hitung();
                        pesanGalat([]);
                    })
                    .fail(function (xhr) {
                        pesanGalat([teks(xhr)]);
                    });

                return;
            }

            ANTRE = ANTRE.filter(function (s) {
                return s.kunci !== k;
            });
            $k.remove();
            hitung();
        });

    $(function () {
        if ($panel().length === 0) {
            return;
        }

        hitung();
    });

    window.FOTO = {
        ada: function () {
            return $panel().length > 0 && ANTRE.length > 0;
        },
        kirim: selesai
    };
}(jQuery));
