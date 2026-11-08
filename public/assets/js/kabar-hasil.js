/*
 * Kabar hasil akhir sebuah pekerjaan (orderan jadi, orderan gagal, error tak
 * terduga di halaman mana pun) dalam dua rupa:
 *
 *   galat  -> modal yang menahan layar, karena pesannya harus dibaca dulu
 *             sebelum orang menekan Simpan lagi;
 *   sukses -> lower third di pojok kanan bawah yang hilang sendiri, karena
 *             konfirmasi tidak boleh meminta satu klik pun.
 *
 * Bedanya dengan KABAR di kabaran.js: KABAR itu koreksi ringan per isian di
 * pojok kanan atas, sedangkan modul ini mengurus hasil akhir. Pesan
 * "orderan sudah tersimpan" yang hilang di balik redirect tidak ada gunanya,
 * dan pesan galat yang tertutup popup lima detik membuat orang menekan Simpan
 * lagi tanpa tahu apa yang salah.
 *
 * Sengaja dibangun sendiri (tanpa new bootstrap.Modal): sama seperti alasan
 * kabaran.js lahir — dependensi plugin pernah membuat pesan hilang diam-diam
 * karena versinya beda, dan CDN bootstrap bisa saja tidak terpasang. Bentuknya
 * lewat class .modal-kabar dan .low-third di tailwind.css.
 */
(function ($) {
  "use strict";

  var KUNCI_CATAT = "kabar_hasil_catat"; // payload sukses yang dibawa ke halaman tujuan
  var UMUR = 5000; // sama dengan popup KABAR dan banner flash di template/main.php
  var PUDAR = 350;
  var MAKS = 3; // lower third sekaligus; sisanya dibuang dari yang paling tua
  var tampilSekarang = null;
  var wadahBawah = null;
  var pernah = {}; // error global: satu pesan cukup sekali per kunjungan halaman

  function teks(v) {
    return String(v === null || v === undefined ? "" : v).trim();
  }

  function buang() {
    if (!tampilSekarang) {
      return;
    }

    $(document).off("keydown.modalKabar");
    tampilSekarang.remove();
    tampilSekarang = null;
    $("body").removeClass("modal-kabar-muncul");
  }

  /*
   * opts = { judul, pesan, catatan, tombol }
   * `pesan` string atau array; array panjang apa pun (mis. semua galat validasi
   * dari server) diringkas sendiri jadi daftar, tidak jadi beberapa modal.
   */
  function buka(opts) {
    var daftar = $.isArray(opts.pesan) ? opts.pesan : [opts.pesan];
    var bersih = [];

    for (var i = 0; i < daftar.length; i++) {
      var t = teks(daftar[i]);

      if (t !== "" && bersih.indexOf(t) === -1) {
        bersih.push(t);
      }
    }

    var $kotak = $('<div class="modal-kabar-kotak"></div>');
    var judul = teks(opts.judul) === "" ? "Ada kesalahan" : teks(opts.judul);

    $('<i class="fal modal-kabar-ikon fa-exclamation-circle"></i>').appendTo($kotak);
    $('<h5 class="modal-kabar-judul"></h5>').text(judul).appendTo($kotak);

    if (bersih.length === 1) {
      $('<p class="modal-kabar-teks"></p>').text(bersih[0]).appendTo($kotak);
    } else if (bersih.length > 1) {
      var $ul = $('<ul class="modal-kabar-daftar"></ul>');

      $.each(bersih, function (i, v) {
        $("<li></li>").text(v).appendTo($ul);
      });

      $ul.appendTo($kotak);
    }

    if (teks(opts.catatan) !== "") {
      $('<p class="modal-kabar-catatan"></p>').text(opts.catatan).appendTo($kotak);
    }

    var $tombol = $('<button type="button" class="modal-kabar-tombol"></button>')
      .text(teks(opts.tombol) === "" ? "Tutup" : teks(opts.tombol))
      .on("click", buang);

    $('<div class="modal-kabar-aksi"></div>').append($tombol).appendTo($kotak);

    var $layer = $('<div class="modal-kabar modal-kabar-galat" role="dialog" aria-modal="true"></div>')
      .attr("aria-label", judul);

    // remang ikut menutup: orang yang memang sudah membaca pesannya tidak boleh
    // terpaksa menekan tombol dulu
    $('<div class="modal-kabar-remang"></div>')
      .on("click", buang)
      .appendTo($layer);

    $kotak.appendTo($layer);

    buang();
    $("body").addClass("modal-kabar-muncul").append($layer);
    tampilSekarang = $layer;

    $(document).on("keydown.modalKabar", function (e) {
      if (e.key === "Escape") {
        buang();
      }
    });

    window.setTimeout(function () {
      $tombol.trigger("focus");
    }, 0);

    return $layer;
  }

  /* ── Lower third: konfirmasi yang tidak meminta klik ──────────────────────
   * Muncul di pojok kanan bawah, menggeser ke atas kalau ada kabar lain, lalu
   * memudar sendiri. Sengaja di bawah (bukan di kiri atas seperti KABAR) supaya
   * kabar hasil akhir tidak menutupi isian yang barusan diisi orang.
   */
  function pastikanBawah() {
    if (wadahBawah && document.body.contains(wadahBawah)) {
      return wadahBawah;
    }

    wadahBawah = $('<div class="kabar-bawah" role="region" aria-label="Kabar orderan" aria-live="polite"></div>')
      .appendTo("body")[0];

    return wadahBawah;
  }

  function bawah(opts) {
    opts = opts || {};

    var $kartu = $('<div class="low-third" role="status"></div>');

    $('<i class="fal low-third-ikon fa-check-circle"></i>').appendTo($kartu);

    var $isi = $('<div class="low-third-isi"></div>');

    $('<div class="low-third-judul"></div>')
      .text(teks(opts.judul) === "" ? "Berhasil" : teks(opts.judul))
      .appendTo($isi);

    var pesan = teks(opts.pesan);

    if (pesan !== "") {
      $('<div class="low-third-teks"></div>').text(pesan).appendTo($isi);
    }

    $kartu.append($isi);

    var $tutup = $('<button type="button" class="low-third-tutup" aria-label="Tutup pesan"></button>')
      .html("&times;")
      .on("click", function () {
        buangKartu();
      });

    $kartu.append($tutup);

    // pastikanBawah() mengembalikan node DOM, jadi pembungkus jQuery wajib:
    // append() bawaan peramban mengubah objek jadi "[object Object]"
    $(pastikanBawah()).append($kartu);

    var jeda = window.setTimeout(buangKartu, UMUR);

    function buangKartu() {
      window.clearTimeout(jeda);
      $kartu.addClass("low-third-pudar");
      window.setTimeout(function () {
        $kartu.remove();
      }, PUDAR);
    }

    var $tumpuk = $(wadahBawah).children(".low-third");

    if ($tumpuk.length > MAKS) {
      $tumpuk.slice(0, $tumpuk.length - MAKS).each(function () {
        $(this).remove();
      });
    }

    return $kartu;
  }

  /* ── Payload yang ikut pindah halaman ─────────────────────────────────────
   * Simpan orderan selalu diakhiri redirect, jadi pesannya tidak bisa ditampilkan
   * di halaman ini. Ditaruh di sessionStorage (bukan flashdata server): antrean
   * upload foto menyusip beberapa permintaan AJAX di antara simpan dan pindah
   * halaman, dan flashdata CodeIgniter cuma bertahan satu permintaan.
   */
  function simpanSukses(badah) {
    try {
      sessionStorage.setItem(KUNCI_CATAT, JSON.stringify(badah || {}));
    } catch (e) {
      // penyimpanan penuh atau mode privat: pesannya memang tidak wajib muncul
    }
  }

  function tuntasSukses() {
    var mentah = null;

    try {
      mentah = sessionStorage.getItem(KUNCI_CATAT);

      if (mentah) {
        sessionStorage.removeItem(KUNCI_CATAT);
      }
    } catch (e) {
      return;
    }

    if (!mentah) {
      return;
    }

    var badah = {};

    try {
      badah = JSON.parse(mentah) || {};
    } catch (e) {
      return;
    }

    var nomor = teks(badah.nomor);

    sukses({
      pesan: nomor === ""
        ? "Orderan sudah masuk daftar transaksi."
        : "Orderan " + nomor + " sudah masuk daftar transaksi."
    });
  }

  function sukses(opts) {
    opts = opts || {};

    return bawah({
      judul: teks(opts.judul) === "" ? "Transaksi berhasil dicatat" : opts.judul,
      pesan: opts.pesan
    });
  }

  /*
   * Halaman cuma tahu "gagal": daftar pesan dari server, kodenya, atau halaman
   * error CodeIgniter yang berupa HTML. Yang terakhir ini biasanya lolos tanpa
   * dibaca siapa-siapa karena jawabannya bukan JSON.
   */
  function dariRequest(jqXHR) {
    var status = Number(jqXHR && jqXHR.status) || 0;
    var dariHtml = dariHalamanError(jqXHR && jqXHR.responseText);

    if (dariHtml.length > 0) {
      return dariHtml;
    }

    if (jqXHR && jqXHR.status === 0) {
      return ["Koneksi ke server putus, jadi permintaan tidak sampai sama sekali."];
    }

    return ["Server menolak permintaan (kode " + status + ")."];
  }

  function galat(opts) {
    opts = typeof opts === "string" || $.isArray(opts) ? { pesan: opts } : (opts || {});

    // penanda kalau pemanggil sudah menangani request-nya sendiri
    if (opts.request) {
      opts.request._modalKabar = true;
    }

    var pesan = opts.pesan;

    if ((pesan === undefined || pesan === null || (pesan.length === 0)) && opts.request) {
      pesan = dariRequest(opts.request);
    }

    return buka({
      judul: teks(opts.judul) === "" ? "Ada kesalahan" : opts.judul,
      pesan: pesan,
      catatan: opts.catatan,
      tombol: opts.tombol
    });
  }

  /* ── Jaring pengaman: error yang tidak diperkirakan halaman ───────────────
   * Halaman boleh memanggil galat() sendiri (dan menandai request-nya supaya
   * tidak dihitung dua kali). Sisanya: server mengembalikan 500, koneksi putus,
   * dan exception JavaScript yang biasanya cuma nongkrong di console.
   */
  function sekali(kunci, opts) {
    if (pernah[kunci]) {
      return;
    }

    pernah[kunci] = true;
    galat(opts);
  }

  function dariHalamanError(html) {
    var keluaran = [];

    try {
      var dokumen = new DOMParser().parseFromString(String(html || ""), "text/html");
      var kepala = dokumen.querySelector(".header h1");
      var isi = dokumen.querySelector(".header p");

      if (kepala) {
        keluaran.push(teks(kepala.textContent));
      }

      if (isi) {
        // buang tautan "search ->" yang menempel di kalimat
        $.each(isi.querySelectorAll("a"), function () {
          $(this).remove();
        });

        keluaran.push(teks(isi.textContent));
      }
    } catch (e) {
      return [];
    }

    return $.grep(keluaran, function (v) {
      return v !== "";
    });
  }

  function pantau() {
    $(document).ajaxError(function (_event, jqXHR) {
      var status = Number(jqXHR && jqXHR.status) || 0;

      if (jqXHR && jqXHR._modalKabar) {
        return;
      }

      // 403 sudah dijaga template/main.php (muat ulang untuk token CSRF baru)
      if (status === 403) {
        return;
      }

      if (status === 0) {
        sekali("0", {
          judul: "Koneksi ke server putus",
          pesan: "Permintaan tidak sampai ke server. Cek jaringan, lalu coba lagi."
        });

        return;
      }

      if (status >= 500) {
        var pesan = dariRequest(jqXHR);

        sekali("s" + status + "|" + pesan.join("|"), {
          judul: "Server bermasalah (" + status + ")",
          pesan: pesan,
          catatan: "Screenshot pesan ini lalu laporkan ke admin, ya."
        });
      }
    });

    window.addEventListener("error", function (e) {
      // error aset (gambar/CDN) bukan sesuatu yang perlu mengganggu layar
      if (!e || !e.message) {
        return;
      }

      sekali("js|" + e.message, {
        judul: "Halaman menemukan error",
        pesan: e.message,
        catatan: "Fitur lain mungkin masih jalan; kalau terasa mengganggu, muat ulang halamannya."
      });
    });

    window.addEventListener("unhandledrejection", function (e) {
      var alasan = e && e.reason ? e.reason.message || e.reason : "";

      if (teks(alasan) === "") {
        return;
      }

      sekali("promise|" + alasan, {
        judul: "Halaman menemukan error",
        pesan: alasan
      });
    });
  }

  $(function () {
    pantau();
    tuntasSukses();
  });

  window.KABAR_HASIL = {
    sukses: sukses,
    galat: galat,
    tutup: buang,

    /**
     * Tandai permintaan yang pesannya sudah diurus halaman ini. Dipanggil
     * begitu request dibuat (bukan di dalam fail) karena jQuery melepas
     * ajaxError global lebih dulu, jadi penanda yang telat tetap sempat
     * membuka modal kedua di atas pesan yang sudah benar.
     */
    jaga: function (request) {
      if (request) {
        request._modalKabar = true;
      }

      return request;
    },

    /** Tabungi pesan sukses yang harus muncul setelah halaman ikut pindah. */
    catatSukses: simpanSukses,

    /** Nama kunci sessionStorage yang dipakai, untuk halaman yang perlu bersih-bersih. */
    KUNCI_CATAT: KUNCI_CATAT
  };
})(jQuery);
