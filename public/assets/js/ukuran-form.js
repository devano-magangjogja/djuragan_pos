/*
 * Panel inputan ukuran per item orderan (form Tulis, form Sunting, modal produk).
 *
 * Kerangkanya dibangun server lewat ukuran_form_panel() di fungsi_helper.php;
 * file ini yang mengisi dropdown jenis produk dan kolom angkanya dari
 * window.UKURAN_FORM. Panel baris lama memakai prefix nama dari data-nama supaya
 * angka yang sudah tercatat ikut terkirim terus saat orderan disunting, panel
 * modal tidak punya nama (kunci barisnya baru ada saat produk ditambah, jadi
 * nilainya diambil lewat UKURAN.nilai()).
 */
(function ($) {
    'use strict';

    var data = window.UKURAN_FORM;

    if (! data) {
        return;
    }

    function lepas(teks) {
        return String(teks === undefined || teks === null ? '' : teks)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function tambah(daftar, lebih) {
        var punya = {};

        $.each(daftar, function (i, k) {
            punya[k.id_komponen] = true;
        });

        $.each(lebih || [], function (i, k) {
            if (! punya[k.id_komponen]) {
                daftar.push(k);
            }
        });

        return daftar;
    }

    function daftar(jenis, lebih) {
        var pakai = (data.komponen[0] || []).slice();

        if (jenis !== '' && jenis !== null && jenis !== undefined) {
            pakai = pakai.concat(data.komponen[jenis] || []);
        }

        return tambah(pakai, lebih);
    }

    function kolom(k, nilai, nama) {
        var nm = nama ? ' name="' + nama + '[nilai_ukuran][' + k.id_komponen + ']"' : '';

        return '<div class="col-6 col-sm-4 col-lg-3 mb-2">'
            + '<label class="form-label">' + lepas(k.nama)
            + (k.satuan ? ' <span class="text-muted">' + lepas(k.satuan) + '</span>' : '')
            + '</label>'
            + '<input type="number" class="form-control form-control-sm uk-field" step="0.1" min="0"'
            + ' data-key="' + k.id_komponen + '" data-label="' + lepas(k.nama) + '" data-satuan="' + lepas(k.satuan) + '"'
            + nm + ' value="' + lepas(nilai) + '" autocomplete="off">'
            + '</div>';
    }

    function pilihan(jenis) {
        var html = ['<option value="">Pilih jenis produk</option>'];

        $.each(data.jenis, function (id, nama) {
            html.push('<option value="' + id + '"' + (String(jenis) === String(id) ? ' selected' : '') + '>' + lepas(nama) + '</option>');
        });

        return '<div class="col-12 mb-1"><select class="form-select form-select-sm uk-jenis">' + html.join('') + '</select></div>';
    }

    function angka($panel) {
        var hasil = {};

        $panel.find('.uk-field').each(function () {
            var v = $.trim($(this).val() || '');

            if (v !== '') {
                hasil[$(this).data('key')] = v;
            }
        });

        return hasil;
    }

    /** "Lingkar Dada 102 cm" per kolom yang terisi (koma, bukan titik). */
    function isiRingkas($panel) {
        var bagian = [];

        $panel.find('.uk-field').each(function () {
            var v = $.trim($(this).val() || ''),
                s = $(this).data('satuan');

            if (v !== '') {
                bagian.push($(this).data('label') + ' ' + v.replace('.', ',') + (s ? ' ' + s : ''));
            }
        });

        return bagian;
    }

    function ringkas($panel) {
        var $ringkas = $panel.closest('.ukuran-wrapper').find('.uk-ringkas').first(),
            bagian = isiRingkas($panel);

        if ($ringkas.length === 0) {
            return;
        }

        var teks = bagian.length > 3
            ? bagian.slice(0, 3).join(' · ') + ' +' + (bagian.length - 3)
            : bagian.join(' · ');

        // ringkasan cuma tampil selama panelnya masih ditutup
        $ringkas.text(teks).toggleClass('d-none', teks === '' || ! $panel.hasClass('d-none'));
    }

    function isi($panel, jenis) {
        var nilai = $.extend({}, $panel.data('nilai') || {}, angka($panel)),
            nama = $panel.data('nama') || '',
            html = [];

        $.each(daftar(jenis, $panel.data('lebih')), function (i, k) {
            html.push(kolom(k, nilai[k.id_komponen], nama));
        });

        $panel.find('.uk-isi').html(html.length === 0
            ? '<div class="col-12"><p class="small text-muted mb-0">Pilih jenis produk dulu supaya kolom ukurannya muncul.</p></div>'
            : html.join(''));

        ringkas($panel);
    }

    /** Isi satu kerangka panel; dipakai waktu halaman dimuat dan waktu baris baru ditambah. */
    function tempel(panel) {
        var $panel = $(panel);

        $panel.html(pilihan($panel.data('jenis')) + '<div class="uk-isi row gx-2 w-100"></div>');
        isi($panel, $panel.data('jenis') || '');

        return $panel;
    }

    $(function () {
        $('.ukuran-panel').each(function () {
            tempel(this);
        });
    });

    $(document)
        .on('change', '.uk-jenis', function () {
            var $panel = $(this).closest('.ukuran-panel');

            isi($panel, $(this).val());
        })
        .on('input', '.uk-field', function () {
            ringkas($(this).closest('.ukuran-panel'));
        })
        .on('click', '.buka-ukuran', function (e) {
            e.preventDefault();

            var $panel = $(this).closest('.ukuran-wrapper').find('.ukuran-panel').first().toggleClass('d-none');

            $(this).attr('aria-expanded', $panel.hasClass('d-none') ? 'false' : 'true');
            ringkas($panel);
        });

    // dipakai JS bawaan halaman untuk memindahkan angka ke hidden input baris baru
    window.UKURAN = {
        tempel: tempel,
        nilai: function (panel) {
            var hasil = [];

            $(panel).find('.uk-field').each(function () {
                var v = $.trim($(this).val() || '');

                if (v !== '') {
                    hasil.push({
                        key: $(this).data('key'),
                        label: $(this).data('label'),
                        satuan: $(this).data('satuan'),
                        nilai: v,
                    });
                }
            });

            return hasil;
        },
        ringkas: function (panel) {
            return isiRingkas($(panel)).join(' · ');
        },
        kosongkan: function (panel) {
            var $panel = $(panel);

            $panel.find('.uk-jenis').val('');
            $panel.data('nilai', {});
            isi($panel, '');
        }
    };
}(jQuery));
