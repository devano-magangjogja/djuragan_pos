/*
 * Panel "Anggota Rombongan" di form Tulis dan form Sunting.
 *
 * Barisnya diambil dari <template class="anggota-cadang"> yang ditulis PHP lewat
 * anggota_baris_form(), jadi markup-nya tidak ditulis dua kali di dua tempat.
 * Setiap baris sudah berisi input bernama anggota[k][...] dan panel ukuran
 * dengan data-nama yang sama, sehingga form.serialize() bawaan halaman ikut
 * mengirimnya tanpa perlu hook tambahan di script bawaan halaman.
 */
(function ($) {
    'use strict';

    var GANTIAN = '__K__';

    function kunci() {
        return Math.round(new Date().getTime() + (Math.random() * 100));
    }

    function hitung($daftar) {
        var $baris = $daftar.children('.anggota-baris');

        // nomor kartu selalu ulang 1..n tiap ada tambah/hapus baris
        $baris.each(function (i) {
            $(this).find('.anggota-nomor').first().text(i + 1);
        });

        $daftar.closest('.card').find('.jumlah-anggota').text($baris.length);
    }

    /** Satu baris anggota baru; nama boleh langsung diisi lewat $isi. */
    function baris($daftar, nama) {
        var $cadang = $daftar.closest('.card').find('.anggota-cadang').first();

        if ($cadang.length === 0) {
            return null;
        }

        var $row = $($cadang.html().split(GANTIAN).join(kunci()));

        // Wariskan jenis busana dari anggota pertama jika sudah pernah dipilih
        var jenisPertama = $daftar.find('.uk-jenis').first().val();
        if (jenisPertama) {
            $row.find('.ukuran-panel').attr('data-jenis', jenisPertama);
        }

        $daftar.append($row);

        if (window.UKURAN) {
            window.UKURAN.tempel($row.find('.ukuran-panel'));
        }

        if (nama) {
            $row.find('.anggota-nama').val(nama);
        }

        hitung($daftar);

        return $row;
    }

    /** "1. A.N DEVI" -> "A.N DEVI"; nomor urut dari WA jangan ikut jadi nama. */
    function bersihkan(teks) {
        var n = String(teks === undefined ? '' : teks)
            .replace(/^\s*[-*•]\s*/, '')
            .replace(/^\s*\d+[.)]\s*/, '')
            .trim();

        return n.slice(0, 60);
    }

    $(document)
        .on('click', '.buka-anggota', function (e) {
            e.preventDefault();

            var $card = $(this).closest('.card'),
                $isi = $card.find('.anggota-isi').first(),
                $panah = $(this).find('.ikon-panah-anggota'),
                buka = $isi.hasClass('d-none');

            $isi.toggleClass('d-none', !buka);
            $(this).attr('aria-expanded', buka ? 'true' : 'false');
            $panah.toggleClass('rotate-180', buka);

            // Jika dibuka dan belum ada baris sama sekali, otomatis munculkan 1 anggota
            if (buka) {
                var $daftar = $isi.find('.anggota-daftar').first();
                if ($daftar.children('.anggota-baris').length === 0) {
                    baris($daftar);
                }
            }
        })
        .on('click', '.tambah-anggota', function () {
            baris($(this).closest('.anggota-isi').find('.anggota-daftar').first());
        })
        .on('click', '.hapus-anggota', function () {
            var $daftar = $(this).closest('.anggota-daftar');

            $(this).closest('.anggota-baris').remove();
            hitung($daftar);
        })
        .on('click', '.tambah-tempelan', function () {
            var $isi = $(this).closest('.anggota-isi'),
                $daftar = $isi.find('.anggota-daftar').first(),
                $kotak = $isi.find('#anggota_tempel'),
                nama = $kotak.val().split(/\r?\n/).map(bersihkan).filter(function (n) {
                    return n !== '';
                });

            if (nama.length === 0) {
                $kotak.focus();

                return;
            }

            $.each(nama, function (i, n) {
                baris($daftar, n);
            });

            $kotak.val('');
        });

    // baris warisan server di form Sunting: nomornya baru bisa diisi di sini
    $(function () {
        $('.anggota-daftar').each(function () {
            hitung($(this));
        });
    });
}(jQuery));
