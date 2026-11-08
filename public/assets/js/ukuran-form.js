/*
 * Panel inputan ukuran per item orderan (form Tulis, form Sunting, modal produk).
 *
 * Kerangkanya dibangun server lewat ukuran_form_panel() di fungsi_helper.php;
 * file ini yang mengisi dropdown jenis produk dan kolom angkanya dari
 * window.UKURAN_FORM. Panel baris lama memakai prefix nama dari data-nama supaya
 * angka yang sudah tercatat ikut terkirim terus saat orderan disunting, panel
 * modal tidak punya nama (kunci barisnya baru ada saat produk ditambah, jadi
 * nilainya diambil lewat UKURAN.nilai()).
 *
 * Panel anggota rombongan bertanda data-rombongan dan tidak punya dropdown jenis
 * sendiri: jenisnya mengikuti barang yang sudah masuk orderan (data-jenis-set,
 * ditulis ROMBONGAN.sinkron() di anggota-form.js) dan tampil sebagai tab, jadi
 * satu pesanan bisa memuat beberapa jenis busana sekaligus.
 */
(function ($) {
  "use strict";

  var data = window.UKURAN_FORM;

  if (!data) {
    return;
  }

  function lepas(teks) {
    return String(teks === undefined || teks === null ? "" : teks)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function tambah(daftar, lebih) {
    var punya = {};

    $.each(daftar, function (i, k) {
      punya[k.id_komponen] = true;
    });

    $.each(lebih || [], function (i, k) {
      if (!punya[k.id_komponen]) {
        daftar.push(k);
      }
    });

    return daftar;
  }

  function daftar(jenis, lebih) {
    var pakai = (data.komponen[0] || []).slice();

    if (jenis !== "" && jenis !== null && jenis !== undefined) {
      pakai = pakai.concat(data.komponen[jenis] || []);
    }

    return tambah(pakai, lebih);
  }

  function kolom(k, nilai, nama) {
    var nm = nama
      ? ' name="' + nama + "[nilai_ukuran][" + k.id_komponen + ']"'
      : "";
    var sat = k.satuan
      ? '<span class="input-group-text bg-white border-start-0 text-muted px-2 text-xs">' +
        lepas(k.satuan) +
        "</span>"
      : "";

    return (
      '<div class="col-6 col-sm-4 col-md-3 mb-2">' +
      '<label class="form-label text-xs fw-semibold text-secondary mb-1 d-block text-truncate" title="' +
      lepas(k.nama) +
      '">' +
      lepas(k.nama) +
      "</label>" +
      (k.satuan
        ? '<div class="input-group input-group-sm">' +
          '<input type="number" class="form-control form-control-sm uk-field bg-white border-end-0" step="0.1" min="0"' +
          ' data-key="' +
          k.id_komponen +
          '" data-label="' +
          lepas(k.nama) +
          '" data-satuan="' +
          lepas(k.satuan) +
          '"' +
          nm +
          ' value="' +
          lepas(nilai) +
          '" autocomplete="off" placeholder="0">' +
          sat +
          "</div>"
        : '<input type="number" class="form-control form-control-sm uk-field bg-white" step="0.1" min="0"' +
          ' data-key="' +
          k.id_komponen +
          '" data-label="' +
          lepas(k.nama) +
          '" data-satuan="' +
          lepas(k.satuan) +
          '"' +
          nm +
          ' value="' +
          lepas(nilai) +
          '" autocomplete="off" placeholder="0">') +
      "</div>"
    );
  }

  function pilihan(jenis) {
    var html = ['<option value="">-- Pilih Jenis Busana / Pola --</option>'];

    $.each(data.jenis, function (id, nama) {
      html.push(
        '<option value="' +
          id +
          '"' +
          (String(jenis) === String(id) ? " selected" : "") +
          ">" +
          lepas(nama) +
          "</option>",
      );
    });

    return (
      '<div class="col-12 mb-2 pb-2 border-bottom border-ink-200/70">' +
      '<select class="form-select form-select-sm bg-white uk-jenis">' +
      html.join("") +
      "</select>" +
      "</div>"
    );
  }

  function angka($panel) {
    var hasil = {};

    $panel.find(".uk-field").each(function () {
      var v = $.trim($(this).val() || "");

      if (v !== "") {
        hasil[$(this).data("key")] = v;
      }
    });

    return hasil;
  }

  /** "Lingkar Dada 102 cm" per kolom yang terisi (koma, bukan titik). */
  function kelompok($kolom) {
    var bagian = [];

    $kolom.each(function () {
      var v = $.trim($(this).val() || ""),
        s = $(this).data("satuan");

      if (v !== "") {
        bagian.push(
          $(this).data("label") +
            " " +
            v.replace(".", ",") +
            (s ? " " + s : ""),
        );
      }
    });

    return bagian;
  }

  function isiRingkas($panel) {
    if ($panel.attr("data-rombongan")) {
      var bag = [];

      $panel.find(".uk-grup").each(function () {
        var isi = kelompok($(this).find(".uk-field"));

        if (isi.length > 0) {
          bag.push($(this).attr("data-nama-jenis") + ": " + isi.join(" · "));
        }
      });

      return bag;
    }

    return kelompok($panel.find(".uk-field"));
  }

  function ringkas($panel) {
    var $wrapper = $panel.closest(".ukuran-wrapper"),
      $ringkas = $wrapper.find(".uk-ringkas").first(),
      bagian = isiRingkas($panel),
      jenisTeks = $panel.find(".uk-jenis option:selected").text();

    if ($ringkas.length === 0) {
      return;
    }

    var labelJenis =
      $panel.find(".uk-jenis").val() &&
      jenisTeks &&
      jenisTeks.indexOf("--") === -1
        ? jenisTeks + ": "
        : "";

    var teks =
      bagian.length > 0
        ? labelJenis +
          (bagian.length > 3
            ? bagian.slice(0, 3).join(" · ") + " +" + (bagian.length - 3)
            : bagian.join(" · "))
        : "";

    // ringkasan cuma tampil selama panelnya masih ditutup
    $ringkas
      .text(teks)
      .toggleClass("d-none", teks === "" || !$panel.hasClass("d-none"));

    // perbarui status tombol buka-ukuran jika ada
    var $btn = $wrapper.find(".buka-ukuran");
    if ($btn.length > 0 && $panel.hasClass("d-none")) {
      $btn
        .find(".teks-buka")
        .text(bagian.length > 0 ? "Ubah Ukuran" : "Atur Ukuran");
    }
  }

  function isi($panel, jenis) {
    var nilai = $.extend({}, $panel.data("nilai") || {}, angka($panel)),
      nama = $panel.data("nama") || "",
      html = [];

    $.each(daftar(jenis, $panel.data("lebih")), function (i, k) {
      html.push(kolom(k, nilai[k.id_komponen], nama));
    });

    if (html.length === 0) {
      $panel
        .find(".uk-isi")
        .html(
          '<div class="col-12 py-2"><p class="small text-muted mb-0"><i class="fal fa-info-circle me-1"></i> Pilih jenis busana di atas untuk menampilkan kolom ukuran.</p></div>',
        );
    } else {
      var tombolTutup =
        $panel.closest(".ukuran-wrapper").find(".buka-ukuran").length > 0
          ? '<div class="col-12 mt-1 pt-2 border-top border-ink-200/60 d-flex justify-content-between align-items-center">' +
            '<span class="text-xs text-muted"><i class="fal fa-check-circle text-success me-1"></i>Ukuran tersimpan otomatis</span>' +
            '<button type="button" class="btn btn-xs btn-light border py-1 px-2.5 text-xs tutup-ukuran-btn"><i class="fal fa-check me-1"></i>Selesai</button>' +
            "</div>"
          : "";
      $panel.find(".uk-isi").html(html.join("") + tombolTutup);
    }

    ringkas($panel);
  }

  /* ---- panel anggota rombongan: satu tab per jenis busana yang dipakai ---- */

  /**
   * Tab yang harus tampil untuk satu anggota: komponen umum sekali (kalau ada),
   * satu tab per jenis yang diterapkan orderan urut barangnya masuk, lalu tab
   * untuk angka tersimpan yang komponennya sudah tidak masuk daftar — tanpa tab
   * itu nilainya ikut terhapus waktu orderan disimpan. Komponen semacam itu
   * membawa jenisnya sendiri, jadi ia kembali ke tab jenisnya kalau jenis itu
   * masih dipakai orderan, dan bukan dilempar ke satu tab tanpa nama.
   */
  function tabRombongan($panel) {
    var tab = [],
      byJenis = {},
      punya = {},
      umum = (data.komponen[0] || []).slice();

    if (umum.length > 0) {
      tab.push({ kunci: "0", nama: "Ukuran Umum", komponen: umum });

      $.each(umum, function (i, k) {
        punya[k.id_komponen] = true;
      });
    }

    $.each(
      String($panel.attr("data-jenis-set") || "").split(","),
      function (i, mentah) {
        var id = $.trim(mentah);

        if (id === "" || id === "0" || !data.jenis[id] || byJenis[id]) {
          return;
        }

        var komponen = (data.komponen[id] || []).slice();

        $.each(komponen, function (j, k) {
          punya[k.id_komponen] = true;
        });

        byJenis[id] = { kunci: id, nama: data.jenis[id], komponen: komponen };
        tab.push(byJenis[id]);
      },
    );

    var yatim = {},
      urut = [];

    $.each($panel.data("lebih") || [], function (i, k) {
      if (punya[k.id_komponen]) {
        return;
      }

      punya[k.id_komponen] = true;

      var j = k.jenis_id && data.jenis[k.jenis_id] ? String(k.jenis_id) : "";

      if (j !== "" && byJenis[j]) {
        byJenis[j].komponen.push(k);

        return;
      }

      var kunci = j === "" ? "lain" : "s" + j;

      if (!yatim[kunci]) {
        yatim[kunci] = {
          kunci: kunci,
          nama: j === "" ? "Ukuran Lain" : data.jenis[j],
          komponen: [],
        };
        urut.push(kunci);
      }

      yatim[kunci].komponen.push(k);
    });

    $.each(urut, function (i, kunci) {
      tab.push(yatim[kunci]);
    });

    return tab;
  }

  /**
   * Angka yang sudah diisi untuk jenis yang baru dibuang dari orderan tidak boleh
   * hilang diam-diam: komponennya dipindah ke cadangan supaya tetap tampil (tab
   * "Ukuran Lain") dan tetap terkirim waktu orderan disimpan.
   */
  function selamatkan($panel, set) {
    var cakupan = {},
      lebih = ($panel.data("lebih") || []).slice(),
      punya = {};

    $.each(data.komponen[0] || [], function (i, k) {
      cakupan[k.id_komponen] = true;
    });

    $.each(set, function (i, id) {
      $.each(data.komponen[id] || [], function (j, k) {
        cakupan[k.id_komponen] = true;
      });
    });

    $.each(lebih, function (i, k) {
      punya[k.id_komponen] = true;
    });

    $panel.find(".uk-field").each(function () {
      var id = parseInt($(this).data("key"), 10);

      if (cakupan[id] || punya[id] || $.trim($(this).val() || "") === "") {
        return;
      }

      punya[id] = true;
      lebih.push({
        id_komponen: id,
        nama: $(this).data("label"),
        satuan: $(this).data("satuan"),
      });
    });

    $panel.data("lebih", lebih);
  }

  function pilihTab($panel, kunci) {
    $panel.attr("data-jenis-aktif", kunci);

    $panel.find(".uk-tab-jenis").each(function () {
      var nyala = $(this).attr("data-tab") === kunci;

      $(this)
        .toggleClass("btn-primary", nyala)
        .toggleClass("text-white", nyala)
        .toggleClass("btn-light", !nyala)
        .toggleClass("text-ink-700", !nyala)
        .attr("aria-pressed", nyala ? "true" : "false");
    });

    $panel.find(".uk-grup").each(function () {
      $(this).toggleClass("d-none", $(this).attr("data-tab") !== kunci);
    });
  }

  function isiRombongan($panel) {
    var tab = tabRombongan($panel),
      nilai = $.extend({}, $panel.data("nilai") || {}, angka($panel)),
      nama = $panel.data("nama") || "",
      aktif = String($panel.attr("data-jenis-aktif") || ""),
      cocok = false;

    if (tab.length === 0) {
      $panel
        .attr("data-jenis-aktif", "")
        .html(
          '<div class="w-100 py-2"><p class="small text-muted mb-0">' +
            '<i class="fal fa-info-circle me-1 text-primary"></i>' +
            "Belum ada jenis busana pada orderan ini. Tambahkan barangnya lewat " +
            "<strong>Tambah Barang</strong>, kolom ukuran tiap anggota menyesuaikan sendiri." +
            "</p></div>",
        );

      ringkas($panel);

      return;
    }

    $.each(tab, function (i, t) {
      cocok = cocok || t.kunci === aktif;
    });

    if (!cocok) {
      aktif = tab[0].kunci;
    }

    var pill = [],
      grup = [];

    $.each(tab, function (i, t) {
      var html = [];

      $.each(t.komponen, function (j, k) {
        html.push(kolom(k, nilai[k.id_komponen], nama));
      });

      pill.push(
        '<button type="button" class="uk-tab-jenis btn btn-sm btn-light border rounded-pill px-2.5 py-1 text-xs fw-semibold"' +
          ' data-tab="' +
          t.kunci +
          '">' +
          lepas(t.nama) +
          "</button>",
      );

      grup.push(
        '<div class="uk-grup w-100" data-tab="' +
          t.kunci +
          '" data-nama-jenis="' +
          lepas(t.nama) +
          '">' +
          '<div class="uk-isi row gx-2 w-100">' +
          (html.length > 0
            ? html.join("")
            : '<div class="col-12"><p class="small text-muted mb-0">Jenis ini belum punya kolom ukuran.</p></div>') +
          "</div></div>",
      );
    });

    var tombolTutup =
      $panel.closest(".ukuran-wrapper").find(".buka-ukuran").length > 0
        ? '<div class="w-100 mt-1 pt-2 border-top border-ink-200/60 d-flex justify-content-between align-items-center">' +
          '<span class="text-xs text-muted"><i class="fal fa-check-circle text-success me-1"></i>Ukuran tersimpan otomatis</span>' +
          '<button type="button" class="btn btn-xs btn-light border py-1 px-2.5 text-xs tutup-ukuran-btn"><i class="fal fa-check me-1"></i>Selesai</button>' +
          "</div>"
        : "";

    $panel.html(
      '<div class="uk-tab d-flex flex-wrap gap-1.5 w-100 mb-2 pb-2 border-bottom border-ink-200/70">' +
        '<span class="text-xs fw-semibold text-secondary align-self-center me-1">Jenis busana</span>' +
        pill.join("") +
        "</div>" +
        '<div class="uk-grup-isi w-100">' +
        grup.join("") +
        "</div>" +
        tombolTutup,
    );

    pilihTab($panel, aktif);
    ringkas($panel);
  }

  /** Isi satu kerangka panel; dipakai waktu halaman dimuat dan waktu baris baru ditambah. */
  function tempel(panel) {
    var $panel = $(panel);

    if ($panel.attr("data-rombongan")) {
      isiRombongan($panel);

      return $panel;
    }

    $panel.html(
      pilihan($panel.data("jenis")) + '<div class="uk-isi row gx-2 w-100"></div>',
    );
    isi($panel, $panel.data("jenis") || "");

    return $panel;
  }

  $(function () {
    $(".ukuran-panel").each(function () {
      tempel(this);
    });
  });

  $(document)
    .on("change", ".uk-jenis", function () {
      var $panel = $(this).closest(".ukuran-panel");

      isi($panel, $(this).val());
    })
    .on("input", ".uk-field", function () {
      ringkas($(this).closest(".ukuran-panel"));
    })
    .on("click", ".uk-tab-jenis", function () {
      pilihTab($(this).closest(".ukuran-panel"), $(this).attr("data-tab"));
    })
    .on("click", ".buka-ukuran", function (e) {
      e.preventDefault();

      var $wrapper = $(this).closest(".ukuran-wrapper");
      var $panel = $wrapper.find(".ukuran-panel").first().toggleClass("d-none");
      var buka = !$panel.hasClass("d-none");

      $(this).attr("aria-expanded", buka ? "true" : "false");
      $(this)
        .find(".teks-buka")
        .text(
          buka
            ? "Tutup Ukuran"
            : $wrapper.find(".uk-field").filter(function () {
                  return $.trim($(this).val()) !== "";
                }).length > 0
              ? "Ubah Ukuran"
              : "Atur Ukuran",
        );
      $(this).find(".ikon-panah").toggleClass("rotate-180", buka);
      ringkas($panel);
    })
    .on("click", ".tutup-ukuran-btn", function (e) {
      e.preventDefault();
      var $wrapper = $(this).closest(".ukuran-wrapper");
      var $panel = $wrapper.find(".ukuran-panel").first().addClass("d-none");
      var $btn = $wrapper.find(".buka-ukuran").first();
      $btn.attr("aria-expanded", "false");
      $btn.find(".teks-buka").text(
        $wrapper.find(".uk-field").filter(function () {
          return $.trim($(this).val()) !== "";
        }).length > 0
          ? "Ubah Ukuran"
          : "Atur Ukuran",
      );
      $btn.find(".ikon-panah").removeClass("rotate-180");
      ringkas($panel);
    });

  // dipakai JS bawaan halaman untuk memindahkan angka ke hidden input baris baru
  window.UKURAN = {
    tempel: tempel,
    isi: isi,
    /** Jenis busana yang berlaku untuk satu anggota, urut barangnya masuk orderan. */
    jenisRombongan: function (panel, ids) {
      var $panel = $(panel),
        set = [];

      $.each(ids || [], function (i, v) {
        v = $.trim(String(v === undefined || v === null ? "" : v));

        if (v !== "" && data.jenis[v]) {
          set.push(v);
        }
      });

      $panel.attr("data-jenis-set", set.join(","));
      selamatkan($panel, set);
      isiRombongan($panel);
    },
    nilai: function (panel) {
      var hasil = [];

      $(panel)
        .find(".uk-field")
        .each(function () {
          var v = $.trim($(this).val() || "");

          if (v !== "") {
            hasil.push({
              key: $(this).data("key"),
              label: $(this).data("label"),
              satuan: $(this).data("satuan"),
              nilai: v,
            });
          }
        });

      return hasil;
    },
    ringkas: function (panel) {
      return isiRingkas($(panel)).join(" · ");
    },
    kosongkan: function (panel) {
      var $panel = $(panel);

      $panel.find(".uk-jenis").val("");
      $panel.find(".uk-field").val("");
      $panel.data("nilai", {});

      if ($panel.attr("data-rombongan")) {
        isiRombongan($panel);
      } else {
        isi($panel, "");
      }
    },
  };
})(jQuery);
