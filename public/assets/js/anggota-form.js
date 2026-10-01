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
        var n = $daftar.children('.anggota-baris').length;

        $daftar.closest('.card').find('.jumlah-anggota').text(n);
    }

    /** Satu baris anggota baru; nama boleh langsung diisi lewat $isi. */
    function baris($daftar, nama) {
        var $cadang = $daftar.closest('.card').find('.anggota-cadang').first();

        if ($cadang.length === 0) {
            return null;
        }

        var $row = $($cadang.html().split(GANTIAN).join(kunci()));

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

            var $isi = $(this).closest('.card').find('.anggota-isi').first().toggleClass('d-none');

            $(this).attr('aria-expanded', $isi.hasClass('d-none') ? 'false' : 'true');
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
}(jQuery));
