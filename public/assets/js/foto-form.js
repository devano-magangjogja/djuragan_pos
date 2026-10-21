/*
 * Panel "Foto Orderan" di form Tulis dan form Sunting, dan panel "Foto Produk"
 * di modal katalog barang.
 *
 * Foto sengaja TIDAK ikut form.serialize(). Orderan baru belum punya id saat
 * form dibuka, jadi berkasnya ditahan dulu di peramban lalu diunggah sendiri ke
 * api/foto/unggah/<id> setelah orderannya tersimpan. Di form Sunting dan di
 * katalog id-nya sudah ada (data-pemilik), jadi berkas langsung naik.
 *
 * Peramban memperkecil dan menuliskan ulang gambar lewat canvas: kiriman jadi
 * ringan dan EXIF (termasuk koordinat HP) hilang sebelum meninggalkan mesin.
 * Server tetap memvalidasi ulang, jadi script yang gagal dimuat pun tidak
 * membuka celah.
 */
(function ($) {
  "use strict";

  var SISI = 1600;
  var MUTU = 0.85;
  var ANTRE = [];

  /* Mode produk: foto tidak langsung naik/hapus, tapi ditahan sampai tombol Simpan. */
  function produk_mode() {
    return $panel().attr("data-sumber") === "produk";
  }
  var ANTRE_P = []; // blob menunggu diunggah
  var HAPUS_P = []; // id_foto menunggu dihapus

  function $panel() {
    return $(".foto-panel").first();
  }

  function kunci() {
    return Math.round(new Date().getTime() + Math.random() * 100);
  }

  /** Semua kartu yang menempati jatah: tersimpan, antrean, dan yang masih diolah. */
  function jumlah() {
    var $k = $panel().find(".foto-kartu");
    if (produk_mode()) {
      return $k.not(".foto-dibuang").length;
    }
    return $k.length;
  }

  function hitung() {
    $panel().find(".jumlah-foto").text(jumlah());
  }

  function sisa() {
    return Math.max(0, Number($panel().attr("data-maks")) - jumlah());
  }

  function kartu(atribut) {
    return $('<div class="col-4 col-sm-3 col-md-2 foto-kartu proses"></div>')
      .attr(atribut || {})
      .append(
        $('<div class="foto-kotak"></div>').append(
          $('<a class="foto-buka" target="_blank" rel="noopener"></a>').append(
            $('<img class="foto-gambar" alt="Foto orderan">'),
          ),
          $(
            '<button type="button" class="foto-buang" aria-label="Hapus foto" title="Hapus foto"><i class="fal fa-trash-alt"></i></button>',
          ),
          $(
            '<span class="foto-tunggu" aria-hidden="true"><i class="fal fa-spinner"></i></span>',
          ),
        ),
        $('<div class="foto-nama small"></div>'),
      );
  }

  /** Gambar siap tampil + nama aslinya; kelas proses dibuang. */
  function isi($k, alamat, nama, penuh) {
    $k.find(".foto-buka").attr("href", penuh || alamat);
    $k.find(".foto-gambar").attr("src", alamat);
    $k.find(".foto-nama")
      .text(nama || "")
      .attr("title", nama || "");
    $k.removeClass("proses");

    hitung();
  }

  function pesanGalat(pesan) {
    var $kotak = $panel().find(".foto-galat");

    $kotak.empty();

    if (pesan.length === 0) {
      $kotak.addClass("d-none");

      return;
    }

    $kotak
      .append('<i class="fal fa-info-circle me-1"></i>')
      .append(
        pesan.map(function (p) {
          return $("<div></div>").text(p);
        }),
      )
      .removeClass("d-none");
  }

  /** Pesan dari server; jawaban bisa berupa teks biasa kalau bukan JSON. */
  function teks(xhr) {
    var j = xhr.responseJSON;

    if (typeof j === "string") {
      return j;
    }

    if (j && typeof j.status === "string") {
      return j.status;
    }

    if (xhr.status === 0) {
      return "Koneksi terputus, fotonya tidak terkirim.";
    }

    return "Foto gagal diunggah, coba lagi ya.";
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
      var skala =
        Math.max(lebar, tinggi) > SISI ? SISI / Math.max(lebar, tinggi) : 1;

      lebar = Math.max(1, Math.round(lebar * skala));
      tinggi = Math.max(1, Math.round(tinggi * skala));

      var kanvas = document.createElement("canvas");
      kanvas.width = lebar;
      kanvas.height = tinggi;

      var ctx = kanvas.getContext("2d");
      var jpg = file.type !== "image/png" && file.type !== "image/webp";

      if (jpg) {
        // JPEG tidak punya saluran alpha, bagian transparan jadi hitam tanpa latar
        ctx.fillStyle = "#fff";
        ctx.fillRect(0, 0, lebar, tinggi);
      }

      ctx.drawImage(img, 0, 0, lebar, tinggi);

      try {
        kanvas.toBlob(
          function (b) {
            dfd.resolve(b || file);
          },
          jpg ? "image/jpeg" : file.type,
          MUTU,
        );
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
    var n = String(nama || "").trim();

    return n === "" ? "foto" : n.slice(0, 60);
  }

  function pilih(files) {
    var $p = $panel();
    var datang = [].slice.call(files || []).filter(function (f) {
      return /^image\/(jpeg|pjpeg|png|webp)$/.test(f.type);
    });

    // berkas yang sama harus bisa dipilih lagi tanpa memuat ulang halaman
    $p.find(".foto-input").val("");

    if (datang.length === 0) {
      pesanGalat(["Yang dipilih bukan JPG, PNG, atau WebP."]);

      return;
    }

    if (datang.length > sisa()) {
      pesanGalat([
        "Sisa jatah cuma " +
          sisa() +
          " foto, yang kelebihan belum ikut dipilih.",
      ]);
      datang = datang.slice(0, sisa());
    } else {
      pesanGalat([]);
    }

    datang.forEach(function (f) {
      var k = kunci();
      var $k = kartu({ "data-kunci": k });

      $p.find(".foto-daftar").append($k);

      kecil(f).always(function (blob) {
        // kartunya bisa saja sudah dibuang pengguna selama gambar diolah
        if (!document.body.contains($k[0])) {
          return;
        }

        var satu = { kunci: k, blob: blob, nama: namai(f.name) };

        if (produk_mode()) {
          ANTRE_P.push(satu);
          isi($k, URL.createObjectURL(blob), satu.nama);
          tandai_perubahan();

          return;
        }

        if (Number($p.attr("data-pemilik")) > 0) {
          kirim($p.attr("data-pemilik"), [satu], $k);

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
      fd.append("foto[]", s.blob, s.nama);
    });

    return $.ajax({
      url: $panel().attr("data-unggah") + "/" + id,
      type: "post",
      data: fd,
      processData: false,
      contentType: false,
    })
      .done(function (res) {
        if ($k) {
          $k.remove();
        } else {
          daftar.forEach(function (s) {
            $('.foto-kartu[data-kunci="' + s.kunci + '"]').remove();
          });
          ANTRE = [];
        }

        (res.foto || []).forEach(function (f) {
          var $baru = kartu({ "data-id": f.id_foto });

          $panel().find(".foto-daftar").append($baru);
          isi($baru, f.alamat_kecil || f.alamat, f.nama_asli, f.alamat);
        });

        pesanGalat(res.galat || []);

        return res;
      })
      .fail(function (xhr) {
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

    $p.attr("data-pemilik", id);

    var $b = $("#iForm").find('button[type="submit"]').first();

    if ($b.length > 0) {
      $b.prop("disabled", true).html(
        '<i class="fal fa-check"></i> Orderan Tersimpan',
      );

      if ($("#foto-lanjut").length === 0) {
        $("<a></a>", {
          id: "foto-lanjut",
          href: url,
          class:
            "btn btn-outline-primary w-100 mt-2 rounded-[0.75rem] py-2.5 text-uppercase",
          text: "Lewati foto, lihat orderan",
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

    return kirim(id, daftar)
      .done(function (res) {
        if (res && (res.galat || []).length > 0) {
          tinggal(id, url);

          return;
        }

        document.location.href = url;
      })
      .fail(function () {
        tinggal(id, url);
      });
  }

  $(document)
    .on("click", ".buka-foto", function (e) {
      e.preventDefault();

      var $card = $(this).closest(".card"),
        $isi = $card.find(".foto-isi").first(),
        buka = $isi.hasClass("d-none");

      $isi.toggleClass("d-none", !buka);
      $(this).attr("aria-expanded", buka ? "true" : "false");
      $card.find(".ikon-panah-foto").toggleClass("rotate-180", buka);
    })
    .on("change", ".foto-input", function () {
      pilih(this.files);
    })
    .on("dragover", ".foto-pilih", function (e) {
      e.preventDefault();
      $(this).addClass("foto-seret");
    })
    .on("dragleave", ".foto-pilih", function (e) {
      e.preventDefault();
      $(this).removeClass("foto-seret");
    })
    .on("drop", ".foto-pilih", function (e) {
      e.preventDefault();
      $(this).removeClass("foto-seret");

      if (e.originalEvent.dataTransfer) {
        pilih(e.originalEvent.dataTransfer.files);
      }
    })
    .on("click", ".foto-buang", function () {
      var $k = $(this).closest(".foto-kartu"),
        id = $k.attr("data-id"),
        k = $k.attr("data-kunci");

      /* Mode produk: tandai untuk dihapus, jangan langsung kirim. */
      if (produk_mode() && id) {
        if ($k.hasClass("foto-dibuang")) {
          // batal tandai
          $k.removeClass("foto-dibuang");
          HAPUS_P = HAPUS_P.filter(function (x) {
            return x !== id;
          });
        } else {
          $k.addClass("foto-dibuang");
          HAPUS_P.push(id);
        }
        tandai_perubahan();

        return;
      }

      /* Mode produk: buang dari antrean lokal kalau belum tersimpan. */
      if (produk_mode() && !id) {
        ANTRE_P = ANTRE_P.filter(function (s) {
          return s.kunci !== k;
        });
        $k.remove();
        hitung();
        tandai_perubahan();

        return;
      }

      if (id) {
        $.post($panel().attr("data-hapus") + "/" + id)
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

  /* --- Mode produk: tombol Simpan dan penanda perubahan --- */

  function tandai_perubahan() {
    var ada = ANTRE_P.length > 0 || HAPUS_P.length > 0;
    $("#btnSimpanFoto").toggleClass("d-none", !ada);
    $(".foto-status").toggleClass("d-none", !ada);
  }

  function simpan_produk() {
    var $btn = $("#btnSimpanFoto");

    $btn
      .prop("disabled", true)
      .html('<i class="fal fa-spinner fa-spin me-1"></i>Menyimpan...');

    var pekerjaan = [];

    // hapus foto yang ditandai
    HAPUS_P.forEach(function (id) {
      pekerjaan.push($.post($panel().attr("data-hapus") + "/" + id));
    });

    // unggah foto baru sekaligus
    if (ANTRE_P.length > 0) {
      var fd = new FormData();
      ANTRE_P.forEach(function (s) {
        fd.append("foto[]", s.blob, s.nama);
      });
      pekerjaan.push(
        $.ajax({
          url:
            $panel().attr("data-unggah") + "/" + $panel().attr("data-pemilik"),
          type: "post",
          data: fd,
          processData: false,
          contentType: false,
        }),
      );
    }

    $.when
      .apply($, pekerjaan)
      .done(function () {
        // refresh panel: buang kartu ditandai + kartu antrean, lalu muat ulang
        $panel().find(".foto-dibuang").remove();
        $panel().find(".foto-kartu[data-kunci]").remove();
        ANTRE_P = [];
        HAPUS_P = [];
        hitung();
        pesanGalat([]);

        // muat ulang dari server agar id_foto baru ikut terisi
        var id = $panel().attr("data-pemilik");
        var alamatKelola = $panel()
          .attr("data-unggah")
          .replace("/unggah", "/kelola");
        $.get(alamatKelola + "/" + id).done(function (html) {
          $panel().replaceWith(html);
          hitung();
        });

        $btn
          .prop("disabled", false)
          .html('<i class="fal fa-check me-1"></i>Tersimpan');
        setTimeout(function () {
          $btn
            .addClass("d-none")
            .html('<i class="fal fa-save me-1"></i>Simpan');
        }, 1500);
      })
      .fail(function (xhr) {
        $btn
          .prop("disabled", false)
          .html('<i class="fal fa-save me-1"></i>Simpan');
        pesanGalat([teks(xhr)]);
      });
  }

  $(document).on("click", "#btnSimpanFoto", simpan_produk);

  /** Tile foto produk: ikut memakan jatah karena memang ikut tersalin ke nota. */
  function kartu_bawaan(f, kode) {
    var nama = namai(f.nama);

    return $(
      '<div class="col-4 col-sm-3 col-md-2 foto-kartu foto-bawaan"></div>',
    )
      .attr("data-kode", kode)
      .append(
        $('<div class="foto-kotak"></div>').append(
          $('<a class="foto-buka" target="_blank" rel="noopener"></a>')
            .attr("href", f.alamat)
            .append(
              $(
                '<img class="foto-gambar" alt="Foto produk" loading="lazy">',
              ).attr("src", f.alamat_kecil || f.alamat),
            ),
          $('<span class="foto-bawaan-label">dari produk</span>'),
        ),
        $('<div class="foto-nama small"></div>').text(nama).attr("title", nama),
      );
  }

  function panel_buka() {
    var $card = $panel(),
      $isi = $card.find(".foto-isi").first();

    if ($isi.length === 0) {
      return;
    }

    $isi.removeClass("d-none");
    $card.find(".buka-foto").attr("aria-expanded", "true");
    $card.find(".ikon-panah-foto").addClass("rotate-180");
  }

  window.FOTO = {
    ada: function () {
      return $panel().length > 0 && ANTRE.length > 0;
    },
    kirim: selesai,

    /** Reset antrean produk (dipanggil saat modal ditutup). */
    reset: function () {
      ANTRE_P = [];
      HAPUS_P = [];
    },

    /**
     * Tempel foto produk ke panel Foto Orderan untuk tiap baris yang diambil
     * dari stok. `peta` = { kode barang: [foto] } kiriman ambil-stok.js.
     *
     * Server yang menyalin berkasnya waktu orderan disimpan, jadi tile ini
     * hanya janji: tidak bisa dihapus dari sini dan tidak ikut diunggah.
     * Form Sunting dilewati - di sana nota sudah punya id dan penyalinan hanya
     * berjalan untuk orderan baru.
     */
    bawaan_sedia: function (peta) {
      var $p = $panel();

      if ($p.length === 0 || Number($p.attr("data-pemilik")) > 0) {
        return;
      }

      var $daftar = $p.find(".foto-daftar"),
        jatah = Number($p.attr("data-maks"));

      $daftar
        .children(".foto-bawaan")
        .filter(function () {
          return !peta[$(this).attr("data-kode")];
        })
        .remove();

      var punya = {};

      $daftar.children(".foto-bawaan").each(function () {
        punya[$(this).attr("data-kode")] = true;
      });

      $.each(peta, function (kode, daftar) {
        if (punya[kode]) {
          return;
        }

        (daftar || []).forEach(function (f) {
          if (jatah <= 0) {
            return;
          }

          jatah--;
          $daftar.append(kartu_bawaan(f, kode));
        });
      });

      hitung();

      if ($daftar.children(".foto-bawaan").length > 0) {
        panel_buka();
      }
    },
  };
})(jQuery);
