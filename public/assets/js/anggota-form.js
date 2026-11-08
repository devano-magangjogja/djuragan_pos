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
  "use strict";

  var GANTIAN = "__K__";

  function kunci() {
    return Math.round(new Date().getTime() + Math.random() * 100);
  }

  /**
   * Jenis yang sudah tercatat di server waktu halaman dibuka. Form Sunting tidak
   * menyimpan jenis di baris barang, jadi tanpa dasar ini daftar tab anggota
   * jadi kosong setiap kali halaman dimuat ulang.
   */
  var DASAR = null;

  function jenisDasar() {
    if (DASAR === null) {
      DASAR = [];

      $.each(
        String($(".anggota-isi").first().attr("data-jenis-rombongan") || "").split(","),
        function (i, mentah) {
          var id = $.trim(mentah);

          if (id !== "" && DASAR.indexOf(id) === -1) {
            DASAR.push(id);
          }
        },
      );
    }

    return DASAR;
  }

  /**
   * Jenis busana yang berlaku untuk orderan rombongan, diambil dari baris barang
   * yang sudah masuk daftar (satu pesanan boleh memuat beberapa jenis). Urutannya
   * mengikuti urutan barangnya, jadi tab ukurannya tidak melompat-lompat.
   */
  function jenisOrderan() {
    var $tipe = $("#tipe_orderan");

    if ($tipe.length > 0 && $tipe.val() !== "rombongan") {
      return [];
    }

    var ids = [],
      punya = {};

    $(".list-orderan tr[data-kunci] .ukuran-panel").each(function () {
      var j = $.trim($(this).attr("data-jenis") || "");

      if (j !== "" && !punya[j]) {
        punya[j] = true;
        ids.push(j);
      }
    });

    // jenis yang sudah tercatat di orderan tetap dipakai walau baris barangnya
    // tidak mengenalinya lagi, jadi tab ukuran anggota tidak hilang begitu saja
    $.each(jenisDasar(), function (i, j) {
      if (!punya[j]) {
        punya[j] = true;
        ids.push(j);
      }
    });

    return ids;
  }

  /** Total QTY semua baris barang: sebanyak itulah anggota utama orderan. */
  function qtyOrderan() {
    var total = 0;

    $(".list-orderan tr[data-kunci]").each(function () {
      total +=
        parseInt($(this).find('input[name$="[qty]"]').first().val(), 10) || 0;
    });

    return total;
  }

  /** Baris yang belum diisi apa pun; cuma baris semacam ini yang boleh dibuang otomatis. */
  function kosong($baris) {
    var terisi = false;

    $baris
      .find('.anggota-nama, .uk-field, input[name$="[nomor]"], input[name$="[catatan]"]')
      .each(function () {
        if ($.trim($(this).val() || "") !== "") {
          terisi = true;

          return false;
        }
      });

    return !terisi;
  }

  /**
   * Tandai baris yang lahir dari QTY pesanan: tombol hapusnya disembunyikan dan
   * barisnya diberi kelas supaya klik paksa pun tetap ditolak. Baris di luar
   * jumlah QTY adalah anggota tambahan dan bebas dihapus.
   */
  function tandaiKunci($daftar) {
    var qty = qtyOrderan();

    $daftar.children(".anggota-baris").each(function (i) {
      var terkunci = i < qty,
        $baris = $(this);

      $baris.toggleClass("qty-terkunci", terkunci);
      $baris.find(".hapus-anggota").toggleClass("d-none", terkunci);
      $baris.find(".kunci-qty").toggleClass("d-none", !terkunci);
    });

    return qty;
  }

  function hitung($daftar) {
    var $baris = $daftar.children(".anggota-baris");

    // nomor kartu selalu ulang 1..n tiap ada tambah/hapus baris
    $baris.each(function (i) {
      $(this)
        .find(".anggota-nomor")
        .first()
        .text(i + 1);
    });

    tandaiKunci($daftar);
    $daftar.closest(".card").find(".jumlah-anggota").text($baris.length);
  }

  /**
   * Satu baris anggota baru. $isi boleh string (nama saja, untuk tempelan WA)
   * atau object {nama, nomor, catatan} untuk hasil impor spreadsheet.
   */
  function baris($daftar, isi) {
    var $cadang = $daftar.closest(".card").find(".anggota-cadang").first();

    if ($cadang.length === 0) {
      // dulu mengembalikan null tanpa kabar, jadi tombol "+ Tambah anggota"
      // terlihat rusak tanpa penjelasan apa pun
      if (window.KABAR) {
        KABAR.galat(
          "Baris anggota tidak bisa dibuat: cetakan barisnya tidak ada di halaman. Muat ulang lalu coba lagi.",
        );
      }

      return null;
    }

    var $row = $($cadang.html().split(GANTIAN).join(kunci()));

    $daftar.append($row);

    // Bersihkan filter cari agar baris baru tidak tersembunyi oleh search aktif
    $daftar.closest(".anggota-isi").find(".anggota-cari").val("");
    $daftar.children(".anggota-baris").removeClass("d-none");

    if (window.UKURAN) {
      window.UKURAN.tempel($row.find(".ukuran-panel"));

      // jenis busana tidak dipilih per orang lagi: tab ukurannya mengikuti
      // barang yang sudah masuk orderan
      if (window.UKURAN.jenisRombongan) {
        window.UKURAN.jenisRombongan(
          $row.find(".ukuran-panel").first(),
          jenisOrderan(),
        );
      }
    }

    if (isi) {
      var data = typeof isi === "string" ? { nama: isi } : isi;

      if (data.nama) {
        $row.find(".anggota-nama").val(String(data.nama).slice(0, 60));
      }
      if (data.nomor) {
        $row
          .find('input[name$="[nomor]"]')
          .val(String(data.nomor).slice(0, 30));
      }
      if (data.catatan) {
        $row
          .find('input[name$="[catatan]"]')
          .val(String(data.catatan).slice(0, 255));
      }
    }

    hitung($daftar);

    return $row;
  }

  /** "1. A.N DEVI" -> "A.N DEVI"; nomor urut dari WA jangan ikut jadi nama. */
  function bersihkan(teks) {
    var n = String(teks === undefined ? "" : teks)
      .replace(/^\s*[-*•]\s*/, "")
      .replace(/^\s*\d+[.)]\s*/, "")
      .trim();

    return n.slice(0, 60);
  }

  /**
   * Petakan baris hasil baca spreadsheet menjadi baris anggota. Kolom dikenali
   * dari judul (Nama / No|Tinggi / Catatan) bila baris pertama memang judul;
   * kalau tidak, urutan kolom dianggap [nama, nomor, catatan]. Mengembalikan
   * jumlah anggota yang berhasil dibuat.
   *
   * @param {Array<Array>} rows hasil sheet_to_json berformat header:1
   */
  function imporAnggota($daftar, rows) {
    if (!rows || rows.length === 0) {
      return 0;
    }

    var header = (rows[0] || []).map(function (h) {
      return String(h).trim().toLowerCase();
    });
    var punyaJudul = header.some(function (h) {
      return h.indexOf("nama") !== -1;
    });
    var mulai = punyaJudul ? 1 : 0;
    var iNama = 0,
      iNomor = -1,
      iCatatan = -1;

    if (punyaJudul) {
      var fN = -1,
        fNo = -1,
        fC = -1;

      header.forEach(function (h, idx) {
        if (fN === -1 && h.indexOf("nama") !== -1) {
          fN = idx;
        } else if (
          fNo === -1 &&
          (h === "no" ||
            h === "nomor" ||
            h.indexOf("no.") !== -1 ||
            h.indexOf("tinggi") !== -1)
        ) {
          fNo = idx;
        } else if (
          fC === -1 &&
          (h.indexOf("catatan") !== -1 ||
            h.indexOf("ket") !== -1 ||
            h.indexOf("note") !== -1)
        ) {
          fC = idx;
        }
      });

      iNama = fN !== -1 ? fN : 0;
      iNomor = fNo;
      iCatatan = fC;
    }

    var count = 0;

    for (var r = mulai; r < rows.length; r++) {
      var row = rows[r] || [];
      var nama = bersihkan(row[iNama]);

      if (nama === "") {
        continue;
      }

      var isi = { nama: nama };

      if (iNomor >= 0) {
        isi.nomor = String(
          row[iNomor] === undefined || row[iNomor] === null ? "" : row[iNomor],
        ).trim();
      }
      if (iCatatan >= 0) {
        isi.catatan = String(
          row[iCatatan] === undefined || row[iCatatan] === null
            ? ""
            : row[iCatatan],
        ).trim();
      }

      baris($daftar, isi);
      count++;
    }

    return count;
  }

  /** Deret jenis busana yang sedang diterapkan orderan, di atas daftar anggota. */
  function gambarJenis($isi, ids) {
    var $kotak = $isi.find(".jenis-terpakai").first(),
      nama = (window.UKURAN_FORM && window.UKURAN_FORM.jenis) || {};

    if ($kotak.length === 0) {
      return;
    }

    var $list = $kotak.find(".jenis-terpakai-list").first().empty();

    if (ids.length > 0) {
      $list.append(
        $('<span class="text-xs fw-semibold text-ink-500 me-1">Jenis busana pada orderan:</span>'),
      );
    }

    $.each(ids, function (i, id) {
      if (!nama[id]) {
        return;
      }

      $list.append(
        $(
          '<span class="badge rounded-pill bg-brand-50 text-brand-700 border border-brand-100 px-2 py-1 text-xs fw-semibold"><i class="fal fa-shirt me-1"></i></span>',
        ).append(document.createTextNode(nama[id])),
      );
    });

    $kotak.toggleClass("d-none", ids.length === 0);
  }

  /**
   * Samakan panel anggota dengan keadaan orderan: jenis busana yang tersedia,
   * jumlah anggota utama sebanyak total QTY barang, dan baris mana yang terkunci.
   * Dipanggil halaman tiap daftar barangnya berubah.
   */
  var ROMBONGAN = {
    jenis: jenisOrderan,
    qty: qtyOrderan,
    sinkron: function () {
      var $isi = $(".anggota-isi").first();

      if ($isi.length === 0) {
        return;
      }

      var ids = jenisOrderan(),
        $daftar = $isi.find(".anggota-daftar").first();

      $isi.attr("data-jenis-rombongan", ids.join(","));
      $(".jenis_busana").val(ids.join(","));
      gambarJenis($isi, ids);

      if ($("#tipe_orderan").val() === "rombongan") {
        var qty = qtyOrderan();

        // QTY turun: kelebihan baris yang masih kosong dibuang, yang sudah terisi
        // ditinggal jadi anggota tambahan supaya isian orangnya tidak hilang
        $daftar.children(".anggota-baris").slice(qty).each(function () {
          if (kosong($(this))) {
            $(this).remove();
          }
        });

        while ($daftar.children(".anggota-baris").length < qty) {
          if (baris($daftar) === null) {
            break;
          }
        }
      }

      $daftar.children(".anggota-baris").each(function () {
        if (window.UKURAN && window.UKURAN.jenisRombongan) {
          window.UKURAN.jenisRombongan(
            $(this).find(".ukuran-panel").first(),
            ids,
          );
        }
      });

      hitung($daftar);
    },
  };

  window.ROMBONGAN = ROMBONGAN;

  $(document)
    .on("click", ".buka-anggota", function (e) {
      e.preventDefault();

      var $card = $(this).closest(".card"),
        $isi = $card.find(".anggota-isi").first(),
        $panah = $(this).find(".ikon-panah-anggota"),
        buka = $isi.hasClass("d-none");

      $isi.toggleClass("d-none", !buka);
      $(this).attr("aria-expanded", buka ? "true" : "false");
      $panah.toggleClass("rotate-180", buka);

      // dibuka tanpa satu pun baris: munculkan anggota sebanyak QTY orderan,
      // atau satu baris kalau barangnya memang belum ditambah
      if (buka) {
        ROMBONGAN.sinkron();

        var $daftar = $isi.find(".anggota-daftar").first();

        if ($daftar.children(".anggota-baris").length === 0) {
          baris($daftar);
        }
      }
    })
    .on("click", ".tambah-anggota", function () {
      baris($(this).closest(".anggota-isi").find(".anggota-daftar").first());
    })
    .on("click", ".hapus-anggota", function () {
      var $baris = $(this).closest(".anggota-baris"),
        $daftar = $baris.closest(".anggota-daftar");

      // tombolnya sudah disembunyikan; ini jaring pengaman kalau barisnya
      // terlanjur terkunci sebelum tandanya sempat dipasang
      if ($baris.hasClass("qty-terkunci")) {
        if (window.KABAR) {
          KABAR.galat(
            "Anggota ini terbentuk dari QTY pesanan, jadi tidak bisa dihapus. Kurangi QTY barangnya kalau jumlahnya memang berubah.",
          );
        }

        return;
      }

      $baris.remove();
      hitung($daftar);
    })
    .on("click", ".tambah-tempelan", function () {
      var $daftar = $(".anggota-daftar").first(),
        $kotak = $("#anggota_tempel"),
        nama = $kotak
          .val()
          .split(/\r?\n/)
          .map(bersihkan)
          .filter(function (n) {
            return n !== "";
          });

      if (nama.length === 0) {
        $kotak.focus();

        if (window.KABAR) {
          KABAR.galat("Tempel dulu daftar namanya — satu nama per baris.");
        }

        return;
      }

      $.each(nama, function (i, n) {
        baris($daftar, n);
      });

      $kotak.val("");

      var modalEl = document.getElementById("modalTempelWA");
      if (modalEl && window.bootstrap && bootstrap.Modal) {
        var bsModal = bootstrap.Modal.getInstance(modalEl);
        if (bsModal) {
          bsModal.hide();
        } else {
          $("#modalTempelWA").modal("hide");
        }
      } else {
        $("#modalTempelWA").modal("hide");
      }

      if (window.KABAR) {
        KABAR.sukses(nama.length + " anggota berhasil ditambahkan.");
      }
    })
    .on("change", ".anggota-file", function () {
      var input = this;
      var file = input.files && input.files[0];

      if (!file) {
        return;
      }

      var $daftar = $(".anggota-daftar").first();
      var $info = $("#modalImporExcel .anggota-import-info")
        .removeClass("d-none");

      if (typeof XLSX === "undefined") {
        $info
          .removeClass("text-muted text-success")
          .addClass("text-danger")
          .text(
            "Pustaka Excel belum termuat; muat ulang halaman lalu coba lagi.",
          );
        input.value = "";

        return;
      }

      $info
        .removeClass("text-danger text-success")
        .addClass("text-muted")
        .text("Membaca " + file.name + "…");

      var reader = new FileReader();

      reader.onload = function (e) {
        var n;

        try {
          var wb = XLSX.read(new Uint8Array(e.target.result), {
            type: "array",
          });
          var ws = wb.Sheets[wb.SheetNames[0]];
          var rows = XLSX.utils.sheet_to_json(ws, {
            header: 1,
            blankrows: false,
            defval: "",
          });
          n = imporAnggota($daftar, rows);
        } catch (err) {
          $info
            .removeClass("text-muted text-success")
            .addClass("text-danger")
            .text(
              "Gagal membaca file: " + (err && err.message ? err.message : err),
            );
          input.value = "";

          return;
        }

        if (n === 0) {
          $info
            .removeClass("text-muted text-success")
            .addClass("text-danger")
            .text("Tidak ada baris nama yang terbaca dari file itu.");
        } else {
          $info
            .removeClass("text-muted text-danger")
            .addClass("text-success")
            .text(n + " anggota ditambahkan dari " + file.name + ".");

          setTimeout(function () {
            var modalEl = document.getElementById("modalImporExcel");
            if (modalEl && window.bootstrap && bootstrap.Modal) {
              var bsModal = bootstrap.Modal.getInstance(modalEl);
              if (bsModal) {
                bsModal.hide();
              } else {
                $("#modalImporExcel").modal("hide");
              }
            } else {
              $("#modalImporExcel").modal("hide");
            }
            $info.addClass("d-none").text("");
          }, 1200);

          if (window.KABAR) {
            KABAR.sukses(n + " anggota ditambahkan dari " + file.name + ".");
          }
        }

        input.value = "";
      };

      reader.onerror = function () {
        $info
          .removeClass("text-muted text-success")
          .addClass("text-danger")
          .text("Gagal membaca file.");
        input.value = "";
      };

      reader.readAsArrayBuffer(file);
    })
    .on("click", ".unduh-template-anggota", function () {
      if (typeof XLSX === "undefined") {
        if (window.KABAR) {
          KABAR.galat(
            "Pustaka Excel belum termuat; muat ulang halaman lalu coba lagi.",
          );
        }

        return;
      }

      var ws = XLSX.utils.aoa_to_sheet([
        ["Nama", "No./Tinggi", "Catatan"],
        ["A.N DEVI", "01 / 170cm", "lengan diperpendek"],
        ["BUDI SANTOSO", "02", ""],
      ]);
      var wb = XLSX.utils.book_new();

      XLSX.utils.book_append_sheet(wb, ws, "Anggota");
      XLSX.writeFile(wb, "template-anggota-rombongan.xlsx");
    });

  // baris warisan server di form Sunting: nomornya baru bisa diisi di sini
  $(function () {
    // Pindahkan modal tempel WA & impor Excel ke body agar tidak terpotong kontainer
    $("#modalTempelWA, #modalImporExcel").appendTo("body");

    $(".anggota-daftar").each(function () {
      hitung($(this));
    });

    // dijalankan terakhir: panel ukuran sudah ditempel ukuran-form.js dan draf
    // halaman sudah dipulihkan, jadi jenis + QTY orderan bisa dibaca apa adanya
    ROMBONGAN.sinkron();
  });

  // ---- Search anggota ----
  $(document).on("input", ".anggota-cari", function () {
    var kata = $(this).val().toLowerCase().trim();
    var $daftar = $(this).closest(".anggota-isi").find(".anggota-daftar");
    $daftar.children(".anggota-baris").each(function () {
      var nama = $(this).find(".anggota-nama").val().toLowerCase();
      $(this).toggleClass("d-none", kata !== "" && nama.indexOf(kata) === -1);
    });
  });

})(jQuery);
