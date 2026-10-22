<?php

namespace App\Libraries;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\GDLibRenderer;

/**
 * QR Code untuk dokumen cetak (Dompdf) dan keperluan scanning.
 *
 * Isi QR sengaja disimpan apa adanya — nomor nota, tanpa awalan — supaya hasil
 * scan kamera HP maupun scanner dokumen bisa langsung ditempel ke kolom cari
 * Transaksi yang mencocokkan invoice.seri secara persis.
 *
 * Keluarannya data-URI PNG lewat GD (bukan SVG/Imagick): Dompdf 2.0 hanya
 * mengenali raster, dan server ini punya gd tanpa imagick.
 */
class Barcode
{
    /** Margin modul kosong mengelilingi QR, satuan jumlah modul. */
    private const ZONA_DIAM = 4;

    /**
     * PNG data-URI berisi QR untuk satu teks. Teks kosong mengembalikan ''.
     *
     * @param string $teks isi barcode (nomor nota, dst.)
     * @param int    $size sisi gambar dalam piksel
     */
    public static function qrDataUri(string $teks, int $size = 320): string
    {
        $png = self::qrPng($teks, $size);

        if ($png === '') {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Byte mentah PNG untuk satu QR ("" kalau teks kosong). Dipakai pemanggil
     * yang butuh berkas, dan oleh uji agar tidak perlu mengurai data-URI.
     */
    public static function qrPng(string $teks, int $size = 320): string
    {
        $teks = trim($teks);

        if ($teks === '') {
            return '';
        }

        // prefixEci=false: banyak aplikasi scanner kamera gagal membaca QR yang
        // pesannya diberi awalan ECI UTF-8, padahal isi kita murni ASCII
        $qr = Encoder::encode($teks, ErrorCorrectionLevel::M(), 'ISO-8859-1', null, false);

        // Fill::default() = hitam di atas putih, kontras maksimal untuk discan.
        // ZONA_DIAM 4 mengikuti ISO/IEC 18004: scanner (dan jsQR) menolak kode
        // yang tepi diamnya kurang dari itu
        $canvas = new GDLibRenderer($size, self::ZONA_DIAM);

        return $canvas->render($qr);
    }
}
