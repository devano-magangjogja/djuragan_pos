/*
 * Pencarian pelanggan di form orderan (tulis & sunting).
 *
 * Yang dicari hanya pelanggan yang pernah bertransaksi pada juragan yang sedang
 * dipilih (filter-nya ada di PelangganModel::cari). Setelah satu nama dipilih,
 * hidden id + kartu alamat diisi persis seperti hasil modal "Tambah".
 * Enter pada nama yang tidak ada di daftar memicu 'pelanggan:buat' supaya halaman
 * bisa membuka form isian dengan nama sudah terisi.
 * Dipasang lewat window.CARI_PELANGGAN di view masing-masing.
 */
(function ($) {
    'use strict';

    var url = window.CARI_PELANGGAN;

    if (! url) {
        return;
    }

    function target(input) {
        return $(input).hasClass('kirimKe') ? 'kirimKe' : 'pemesan';
    }

    function daftar(input) {
        var $grup = $(input).closest('.input-group'),
            $saran = $grup.children('.saran-pelanggan');

        if ($saran.length === 0) {
            $saran = $('<div class="saran-pelanggan list-group position-absolute shadow w-100" style="top:100%;z-index:1050;display:none"></div>')
                .appendTo($grup.addClass('position-relative'));
        }

        return $saran;
    }

    function baris($parent, kelas, teks) {
        $parent.append($('<span class="' + kelas + '"></span>').text(teks || ''));
    }

    function tampilkan($input, hasil, pesan) {
        var $saran = daftar($input).empty();

        if (hasil.length === 0) {
            $saran.append($('<div class="list-group-item text-muted small py-2 saran-kosong"></div>').text(pesan));
        }

        $.each(hasil, function (i, a) {
            var $item = $('<button type="button" class="list-group-item list-group-item-action text-start py-2"></button>')
                .data('pelanggan', a);

            baris($item, 'd-block fw-bold', a.nama);
            baris($item, 'd-block small', (a.hp || []).join(' / '));
            baris($item, 'd-block small text-muted', a.full);

            $saran.append($item);
        });

        $saran.show();
    }

    function isiKartu(targetNama, a) {
        var $kartu = $('#alamat_' + targetNama).empty();

        $kartu.append($('<h6 class="text-muted fw-normal"></h6>').text(targetNama === 'kirimKe' ? 'Penerima' : 'Pemesan'));
        baris($kartu, 'd-block fw-bold', a.nama);

        var $hp = $('<span class="d-block"></span>');
        $.each(a.hp || [], function (i, v) {
            if (i === 1) {
                $hp.append(' / ');
            }
            $hp.append(document.createTextNode(v));
        });
        $kartu.append($hp);
        baris($kartu, 'd-block', a.full);

        $('.hidden_id [name="id_' + targetNama + '"]').val(a.id);
        $('.form_' + targetNama).hide();
        $('.info-data-' + targetNama).show();
    }

    function cari($input) {
        var q = $.trim($input.val()),
            juragan = parseInt($('select[name="juragan"]').val(), 10) || 0,
            jalan = (parseInt($input.data('token'), 10) || 0) + 1;

        $input.data('token', jalan);

        if (q.length < 2) {
            daftar($input).hide();
            return;
        }

        if (juragan < 1) {
            tampilkan($input, [], 'Pilih juragan dulu ya.');
            return;
        }

        $.getJSON(url, { q: q, juragan_id: juragan }).done(function (b) {
            var hasil = (b && b.results) || [];

            if (parseInt($input.data('token'), 10) !== jalan) {
                return;
            }

            tampilkan($input, hasil, 'Pelanggan dengan kata itu belum pernah order di sini.');
        }).fail(function () {
            if (parseInt($input.data('token'), 10) !== jalan) {
                return;
            }

            tampilkan($input, [], 'Pencarian gagal, coba lagi.');
        });
    }

    var saatKetik = (function () {
        var timer;

        return function (input) {
            clearTimeout(timer);
            timer = setTimeout(function () {
                cari($(input));
            }, 300);
        };
    }());

    // Dihubungkan langsung ke inputnya, bukan lewat document. Handler bawaan
    // halaman memblokir tombol Enter pada semua input lewat delegasi di
    // document, jadi Enter di sini harus diproses lebih dulu.
    $(document)
        .on('click', '.saran-pelanggan button', function (e) {
            e.preventDefault();

            var a = $(this).data('pelanggan'),
                $input = $(this).closest('.input-group').find('.cari_pelanggan'),
                targetNama;

            if (! a) {
                return;
            }

            targetNama = target($input);
            isiKartu(targetNama, a);

            // Halaman orderan pengguna: pemesan yang baru dipilih langsung ikut jadi
            // alamat kirim. Halaman admin tidak - di sana ada tombol
            // "Sama dengan Pemesan" sendiri.
            if (targetNama === 'pemesan' && $('#salinPemesan').length === 0 && $('.hidden_id [name="id_kirimKe"]').val() === '') {
                isiKartu('kirimKe', a);
                $('#tambah_pemesan_kirimKe').attr('id', 'tambah_pemesan');
            }

            $input.val('');
            daftar($input).hide();

            // halaman yang pakai satu kotak berganti mode (admin) menyahut ini
            $(document).trigger('pelanggan:terisi', [targetNama]);
        })
        .on('mousedown', function (e) {
            if ($(e.target).closest('.cari_pelanggan, .saran-pelanggan').length === 0) {
                $('.saran-pelanggan').hide();
            }
        });

    $(function () {
        $('.cari_pelanggan')
            .on('input', function () {
                saatKetik(this);
            })
            .on('focus', function () {
                if ($.trim($(this).val()).length >= 2) {
                    cari($(this));
                }
            })
            .on('keydown', function (e) {
                var $input = $(this),
                    $saran = daftar(this),
                    $aktif, $item, nama, i;

                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.stopPropagation();

                    $aktif = $saran.children('.active');

                    if ($aktif.length > 0) {
                        $aktif.trigger('click');

                        return;
                    }

                    nama = $.trim($input.val());

                    if (nama.length >= 2) {
                        $saran.hide();

                        // nama belum pernah order di sini: buka form isian, nama sudah terisi
                        $(document).trigger('pelanggan:buat', [target($input), nama]);
                    }

                    return;
                }

                if (! $saran.is(':visible')) {
                    return;
                }

                if (e.key === 'Escape') {
                    $saran.hide();

                    return;
                }

                $item = $saran.children('button');

                if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                    e.preventDefault();

                    if ($item.length === 0) {
                        return;
                    }

                    i = $item.index($saran.children('.active'));
                    i = e.key === 'ArrowDown' ? (i + 1) % $item.length : (i < 1 ? $item.length - 1 : i - 1);
                    $item.removeClass('active').eq(i).addClass('active');
                }
            });
    });
}(jQuery));
