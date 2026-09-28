<?php

use CodeIgniter\I18n\Time;

if (! function_exists('formatRibuan')) {
    /**
     * Format angka pendek
     * misal: 10K, 20M
     *
     * see: https://code.recuweb.com/2018/php-format-numbers-to-nearest-thousands/
     *
     * @param int $num angka yang akan diformat
     *
     * @return string hasil angka pendek berupa 10K, 20M
     */
    function formatRibuan(int $num): string
    {
        if ($num > 1000) {
            $x               = round($num);
            $x_number_format = number_format($x);
            $x_array         = explode(',', $x_number_format);
            $x_parts         = ['k', 'm', 'b', 't'];
            $x_count_parts   = count($x_array) - 1;
            $x_display       = $x;
            $x_display       = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
            $x_display .= $x_parts[$x_count_parts - 1];

            return strtoupper($x_display);
        }

        return $num;
    }
}

if (! function_exists('base_user')) {
    function base_user()
    {
        $session = \Config\Services::session();

        switch ($session->get('level')) {
            case 'superadmin':
            case 'admin':
                $base = 'admin';
                break;

            default:
                $base = 'user'; //$session->get('level');
                break;
        }

        return $base;
    }
}

if (! function_exists('random_element')) {
    /**
     * Random Element - Takes an array as input and returns a random element
     *
     * @param	array
     * @param mixed $array
     *
     * @return mixed depends on what the array contains
     */
    function random_element($array)
    {
        return is_array($array) ? $array[array_rand($array)] : $array;
    }
}

if (! function_exists('label_asal')) {
    /**
     * Menampilkan label asal order
     */
    function label_asal(?int $id, ?string $label = null): ?string
    {
        $labelModel = new \App\Models\LabelAsal();
        $get        = $labelModel->find($id);

        return strtoupper($get->label) . ($label !== null ? '&nbsp;<span class="d-block text-muted">' . $label . '</span>' : '');
    }
}

if (! function_exists('logo_bank')) {
    function logo_bank($nama_bank)
    {
        switch ($nama_bank) {
            case 'bca':
                return '<svg style="width: 100px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 500"><defs/><defs><clipPath id="a"><path d="M0 0h1000v500H0z"/></clipPath></defs><g clip-path="url(#a)"><rect width="1000" height="500" fill="#0c377f" rx="50"/><path fill="#fff" d="M170 337l-2-2c-14-30-20-60-20-89v-4c0-30 7-60 20-89l2-2c30-15 61-23 94-23h1v8h-1q-46 0-89 22a209 209 0 000 173q45 19 90 19v8c-32 0-63-7-95-21zm95 21v-8h2c31 0 62-6 93-20q20-62 11-125a261 261 0 00-11-48q-49-21-95-21v-8q48 0 100 23l1 1 1 1v1l7 25 5 25a278 278 0 013 33v13a290 290 0 01-15 84l-1 2-1 1c-33 14-65 21-98 21zm-9-17v-17h7v3h-3v5h3v3h-3v6zm22-17l7-1v3h-3v4h2a3 3 0 001 0v3h-3l1 5h2v3h-6zm7 17v-3h1a15 15 0 002 0 2 2 0 002-1 3 3 0 000-2 3 3 0 000-1 2 2 0 00-2-1 18 18 0 00-3 0v-3h2a3 3 0 001-1 1 1 0 001-1 2 2 0 00-1-2 2 2 0 00-1 0 13 13 0 00-2 0v-3a15 15 0 013 0 4 4 0 012 1 4 4 0 012 1 5 5 0 010 2 4 4 0 010 2 4 4 0 01-2 2 5 5 0 014 4 8 8 0 01-1 2 6 6 0 01-1 2 5 5 0 01-2 1l-5 1zm-40 0a10 10 0 01-4-1 8 8 0 01-2-2 6 6 0 010-2 40 40 0 010-5l1-9h3l-1 10v3a3 3 0 001 2 3 3 0 002 1 3 3 0 003-1 3 3 0 001-1v-3l1-10h4l-1 9a15 15 0 01-1 5 5 5 0 01-1 2 5 5 0 01-2 1 6 6 0 01-2 1h-2zm-13-2l-2-5a4 4 0 00-1-1v-2a9 9 0 00-1-1 5 5 0 00-2 0h-1l-1 7-4-1 3-17 6 1h2a12 12 0 014 1 4 4 0 012 3 5 5 0 010 2 5 5 0 01-2 3 6 6 0 01-4 1 8 8 0 012 2 15 15 0 011 4l2 3zm-3-6v-5 5zm2-5a2 2 0 002-1 3 3 0 000-1 2 2 0 000-2h-1l-3-1v-3 3h-3v4h2l1 1a8 8 0 002 0zm67 9a10 10 0 01-3-6 10 10 0 011-7 8 8 0 015-4 8 8 0 016 1 6 6 0 012 3l-3 2a3 3 0 00-2-2 4 4 0 00-2-1 5 5 0 00-3 2 7 7 0 00-1 5l3 5a4 4 0 003 1 4 4 0 002-2 6 6 0 001-3l4 1a9 9 0 01-2 4 8 8 0 01-5 3 10 10 0 01-1 0 7 7 0 01-5-2zm17-19l4-1 1 1v6l-2-3-1 7h3v3h-3v5l-4 1zm-52 14a3 3 0 001-1 2 2 0 002 0 3 3 0 000-2 2 2 0 00-1-1 2 2 0 00-1-1h-1v-3a10 10 0 013 0 4 4 0 013 2 5 5 0 011 3 7 7 0 01-1 3 10 10 0 01-2 2 14 14 0 01-2 1 21 21 0 01-2 0zm-53 2a10 10 0 01-5-3 8 8 0 01-2-4 11 11 0 011-4 11 11 0 012-5 7 7 0 014-2 10 10 0 019 3 6 6 0 011 4h-3a4 4 0 00-1-3 5 5 0 00-3-1 5 5 0 00-3 0 6 6 0 00-3 4 7 7 0 000 5 5 5 0 003 3 7 7 0 003 0 8 8 0 002-1v-2l-4-1 1-3 8 2-2 7a7 7 0 01-4 1h-1a13 13 0 01-3 0zm114-4l-4 1v-3l2-1-2-3v-6l10 14-4 1zm245-51a84 84 0 010-18 99 99 0 016-25c29-71 73-70 99-68a108 108 0 0013 0l3-9 18 10 18 10-20 47c-35-25-86-2-89 24-3 27 18 49 71 32l-18 49a204 204 0 01-23 1c-53 0-74-25-78-53zm227 51v-32h-42l-11 31h-52l17-38 39-84h-8l22-42h77l14 165zm0-64v-58l-15 29-14 29h29zm-380 63l28-122-10 1 23-45 49 1 29 1c20 0 31 11 34 26a38 38 0 010 13 49 49 0 01-25 36c36 35 0 80-38 88v-41c11-7 17-31 0-34v-25h7c16 0 18-32 5-32h-24l-8 32h20v25a19 19 0 00-4 0h-21l-10 37h29a11 11 0 006-3v41a53 53 0 01-10 1zm-187-6zm31-82c-6-19-30-31-30-52-1-17 12-37 36-37 23 0 37 17 38 35 2 21-23 35-30 55-7 21-5 44-7 65-2-22-1-45-7-66zm20 7c6-18 18-34 37-39 13-4 32 12 29 27-3 12-15 27-35 29-9 0-13 8-19 12 26-2 43-13 54-23 2 9 3 24-4 29-8 6-16 6-24 5-6 0-12 0-19 2-9 2-15 8-23 12-1-19-1-37 4-54zm-45 42c-7-2-13-2-19-2-8 1-16 1-24-5-7-5-6-20-5-29 12 10 29 21 55 23-6-4-10-12-19-12-20-2-32-17-35-29-3-15 16-31 29-27 19 5 31 21 37 39 5 17 5 35 4 54-9-4-14-10-23-12z"/></g></svg>';
                break;

            case 'bri':
                return '<svg style="width:100px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 500"><defs><clipPath id="a"><path d="M0 0h1000v500H0z"/></clipPath></defs><g clip-path="url(#a)"><rect width="1000" height="500" fill="#17479e" rx="50"/><path fill="#fff" fill-rule="evenodd" d="M119 182a27 27 0 0127-27h136a27 27 0 0127 27v136a27 27 0 01-27 27H146a28 28 0 01-27-27zm33 144h14c18 0 38-22 21-39l-35-37 41-40c14-14-2-36-24-36h-17a15 15 0 00-14 14v124a15 15 0 0014 14zm53-152c12 3 24 25 7 43l-32 33 28 29c18 19 7 47-10 47h79l-75-76 41-40c14-14-3-36-24-36h-14zm85 138V188a15 15 0 00-14-14h-21c12 3 25 25 7 43l-32 33 60 62zm74-55v19h8c4 0 8-1 10-3a8 8 0 003-7 8 8 0 00-3-7l-9-2h-9zm0-32v18h6c5 0 8-1 10-3 2-1 4-3 4-7a8 8 0 00-4-6c-2-2-5-3-9-3h-7v1zm-21-15h33c9 0 17 2 22 6 5 3 7 8 7 15a18 18 0 01-4 12c-2 3-6 5-11 7a22 22 0 0113 6c3 3 4 8 4 13 0 7-3 13-8 16-5 4-14 6-25 6h-31v-81zm110 18l-10 30h20l-10-30zm-13-18h26l31 81h-23l-6-17h-31l-6 17h-21l30-81zm62 0h27l23 49 3 7a102 102 0 013 10l-1-11a79 79 0 01-1-9v-46h20v81h-27l-24-50a67 67 0 01-3-8l-3-8 2 13v53h-19v-81zm89 0h22v35l25-35h24l-30 37 33 44h-27l-25-37v37h-22v-81zm136 47v19h9c4 0 7-1 10-3a8 8 0 003-7 8 8 0 00-3-7l-9-2h-10zm0-32v18h7c4 0 8-1 10-3 2-1 3-3 3-7a8 8 0 00-3-6c-2-2-5-3-9-3h-8v1zm-21-15h33c10 0 17 2 22 6 5 3 7 8 7 15a18 18 0 01-3 12c-3 3-7 5-12 7a22 22 0 0113 6c3 3 5 8 5 13 0 7-3 13-9 16-5 4-13 6-25 6h-31v-81zm98 14v21h8c5 0 8-1 11-3s3-4 3-7c0-4-1-6-4-8s-7-3-12-3zm-21-14h32c11 0 19 2 24 5s8 9 8 16a21 21 0 01-4 13c-3 4-7 6-12 7q7 2 11 13l9 27h-22l-7-22q-2-6-5-8l-9-2h-4v32h-21v-81zm77 0h21v81h-21v-81z"/></g></svg>';
                break;

            case 'bni':
                return '<svg style="width:100px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 500"><defs><clipPath id="a"><path d="M0 0h1000v500H0z"/></clipPath></defs><g clip-path="url(#a)"><rect width="1000" height="500" fill="#f15a23" rx="50"/><path fill="#fff" d="M277 343l15-13s-8 5-28-8c-11-8-29-32-29-32l21-18s20 27 44 33c30 9 44-12 44-12v50zm-107 0l45-38 31 38zm-24-125l60 75-60 50zm146 46c-10-9-20-21-20-41 0-14 12-33 35-33 17 0 37 17 37 17v74a31 31 0 01-15 5c-15 0-27-11-37-22zm-130-67l-16-19v-32h65s-16 28 7 72c11 23 29 43 29 43l-21 17zm73-19c-5-23 2-32 2-32h107v28s-36-19-67 13c-15 14-17 40-17 40s-20-25-25-49z"/><path fill="#fff" fill-rule="evenodd" d="M572 148h47s42 63 62 90l43 55V162c0-5-15-14-15-14h55s-18 7-18 14v191s-17-9-37-32c-22-25-99-128-99-128v133c0 7 15 17 15 17h-53s14-10 14-17V162c0-6-14-14-14-14zm222 0h61s-17 8-17 14v164c0 7 17 17 17 17h-61s15-10 15-17V162c0-6-15-14-15-14zm-400 0s15 8 15 14v164c0 7-15 17-15 17h89c6 0 69-10 69-59s-49-53-49-53 30-8 30-41c0-36-44-42-50-42h-89zm47 80v-61h37c5 0 25 7 25 31 0 20-20 30-25 30h-37zm0 16h42c6 0 39 9 39 37 0 30-33 40-39 40h-42v-77z"/></g></svg>';
                break;

            case 'mandiri':
                return '<svg style="width:100px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 500"><defs><linearGradient id="b" x2="1" y1="4.7" y2="-3.6" gradientUnits="objectBoundingBox"><stop offset="0" stop-color="#f6ab00"/><stop offset=".2" stop-color="#fff100"/><stop offset=".4" stop-color="#f6ab00"/><stop offset=".7" stop-color="#fff100"/><stop offset=".8" stop-color="#f6ab00"/><stop offset="1" stop-color="#fff100"/></linearGradient><clipPath id="a"><path d="M0 0h1000v500H0z"/></clipPath></defs><g clip-path="url(#a)"><rect width="1000" height="500" fill="#005bac" rx="50"/><path fill="#fff" fill-rule="evenodd" d="M679.7 264.7h25.8v89h-25.9v-89zm-63.7 89v-61.4a377 377 0 00-1-27.6h23.7l1 16a24.4 24.4 0 0110.7-12.5c5-2.8 11-4.2 18.3-4.2h.8v23.4l-3.6-.3a18.5 18.5 0 00-2 0c-6.9 0-12.2 1.6-16 4.9a16.9 16.9 0 00-5.7 13.6v48.1zm-39.2-89h26v89h-26v-89zm-42 89L534 341a33 33 0 01-11.5 10.9 32.2 32.2 0 01-15.3 3.6 32.1 32.1 0 01-26.3-12.7 52.5 52.5 0 01-10-33.5c0-14 3.2-25.2 9.9-33.7A31.8 31.8 0 01507 263a33 33 0 0115.4 3.5 31 31 0 0111.4 10.4v-51h25.7v109.9l.2 11 .6 7h-25.4zm-37.7-44.4c0 9.2 1.6 16.4 4.7 21.4s7.6 7.6 13.4 7.6c5.8 0 10.5-2.6 13.8-7.8a38 38 0 005.1-21.2c0-9-1.7-16.1-5-21.2-3.5-5.2-8.1-7.8-14-7.8-5.7 0-10 2.6-13.3 7.7a40 40 0 00-4.7 21.3zm-122.4 44.4v-72c0-2.5 0-5.2-.2-8l-.6-9h24.6l.6 13.3c3.3-5.4 7.3-9.3 11.9-11.8s10.2-3.7 16.8-3.7c9.8 0 17.1 2.8 21.9 8.4s7.1 14 7.1 25.5v57.3h-25.5V300q0-10-3.4-14.3c-2.3-3-6-4.3-11.1-4.3-5 0-9 1.6-12 4.8-3.1 3.2-4.6 7.4-4.6 12.8v54.8zm-245.4 0v-72l-.2-9-.5-8H153l.7 13.2c3.4-5.4 7.3-9.3 12-11.8s10.2-3.7 16.8-3.7c6.7 0 12.3 1.5 17 4.5a26.4 26.4 0 0110.1 13q4.4-9 11.4-13.3t17.8-4.2c9.8 0 17 2.8 21.8 8.4s7.2 14 7.2 25.5v57.3H242v-53.8q0-10-3.4-14.3c-2.3-2.9-6-4.3-11.1-4.3-5 0-9 1.6-12 4.8-3 3.2-4.4 7.5-4.4 12.8v54.8h-25.7v-53.8c0-6.6-1.2-11.3-3.4-14.3-2.2-2.9-5.9-4.4-10.9-4.4s-9 1.6-11.8 4.8-4.4 7.4-4.4 12.8v54.7h-25.7v.3zm208.1-9.3a61.3 61.3 0 01-22.3 10.9 38.5 38.5 0 01-8.8.9 25.1 25.1 0 01-18.7-7.9 28 28 0 01-7.6-20c0-6.6 1.3-12 3.8-16.1a26.3 26.3 0 0111-9.8 57.9 57.9 0 0117.4-5.1 269 269 0 0121.5-2.3v-.5c0-21.2-30.4-18.6-43.3-9.5l-4.5-16.8a72.9 72.9 0 0132.5-7.1c21.8 0 40.2 10.4 40.2 35.4v57.4h-21.1v-9.5h-.1zm-2-17.4v-17.2c-4 .3-8.2.9-12.8 1.5a50.7 50.7 0 00-10.4 2.3 13.7 13.7 0 00-6.7 4.6 12.2 12.2 0 00-2.2 7.8c-.1 22.6 26.2 15.3 32 1z"/><path fill="url(#b)" fill-rule="evenodd" d="M325.8 64.8c8 8.9 22.8 32.9 40.2 31.4 41-3.5 64.3-47.8 115-44.6 20.8 1.3 30.6 36.2 51.1 39.2 32.5 4.7 63.7-45.3 116.7-42.4 21.8 1.2 30.8 37.8 52.5 40 20.2 1.8 52-26.8 79.7-41.6-7-12-20.3-30.7-34.3-31.6-27.5-2-49.7 33.3-74 27.1-14-3.6-30.2-42.4-53.5-42.3-43.1.2-72.9 42.9-111 46.2-21.6 1.9-32-46.2-61-41.8-39.6 6.1-80.1 38.7-121.4 60.4z" transform="translate(90.2 143.2)"/></g></svg>';
                break;

            default:
                return 'NOLOGO';
                break;
        }
    }
}

if (! function_exists('first_letter')) {
    /**
     * First Letter - Get the first letter of string
     *
     * @param	string
     * @param mixed $words
     * @param mixed $length
     *
     * @return string
     */
    function first_letter($words, $length = 2)
    {
        $r = array_slice(explode('-', url_title($words . ' ' . random_string('alpha', 6))), 0, $length);

        if (count($r) > 1) {
            $c = '';

            foreach ($r as $f) {
                $c .= mb_substr($f, 0, 1);
            }

            return $c;
        }

        return mb_substr($words, 0, $length);
    }
}

if (! function_exists('status_orderan')) {
    function status_orderan($status, $mulai, $selesai, $keterangan_mulai, $keterangan_selesai)
    {
        $class = '';

        switch ($status) {
            case 1:
                $ico = 'file-alt';
                if ($mulai !== null && $selesai !== null) {
                    $class = 'full';
                    $title = 'Data orderan sudah lengkap';
                    $time  = Time::createFromTimestamp($selesai);
                    $title .= ($keterangan_selesai !== 'null' ? ': ' . $keterangan_selesai : '');
                } elseif ($mulai !== null && $selesai === null) {
                    $title = 'Data orderan belum lengkap';
                    $time  = Time::createFromTimestamp($mulai);
                    $title .= ($keterangan_mulai !== 'null' ? ': ' . $keterangan_mulai : '');
                }
                break;

            case 2:
                $ico = 'layer-group';
                if ($mulai !== null && $selesai !== null) {
                    $class = 'full';
                    $title = 'Bahan sudah ada';
                    $time  = Time::createFromTimestamp($selesai);
                    $title .= ($keterangan_selesai !== 'null' ? ': ' . $keterangan_selesai : '');
                } elseif ($mulai !== null && $selesai === null) {
                    $title = 'Bahan belum ada';
                    $time  = Time::createFromTimestamp($mulai);
                    $title .= ($keterangan_mulai !== 'null' ? ': ' . $keterangan_mulai : '');
                }
                break;

            case 3:
                $ico = 'print';
                if ($mulai !== null && $selesai !== null) {
                    $class = 'full';
                    $title = 'Sudah selesai sablon';
                    $time  = Time::createFromTimestamp($selesai);
                    $title .= ($keterangan_selesai !== 'null' ? ': ' . $keterangan_selesai : '');
                } elseif ($mulai !== null && $selesai === null) {
                    $title = 'Mulai di-sablon';
                    $time  = Time::createFromTimestamp($mulai);
                    $title .= ($keterangan_mulai !== 'null' ? ': ' . $keterangan_mulai : '');
                }
                break;

            case 4:
                $ico = 'waveform-path fa-rotate-90';
                if ($mulai !== null && $selesai !== null) {
                    $class = 'full';
                    $title = 'Sudah selesai bordir';
                    $time  = Time::createFromTimestamp($selesai);
                    $title .= ($keterangan_selesai !== 'null' ? ': ' . $keterangan_selesai : '');
                } elseif ($mulai !== null && $selesai === null) {
                    $title = 'Mulai di-bordir';
                    $time  = Time::createFromTimestamp($mulai);
                    $title .= ($keterangan_mulai !== 'null' ? ': ' . $keterangan_mulai : '');
                }
                break;

            case 5:
                $ico = 'cut fa-rotate-270';
                if ($mulai !== null && $selesai !== null) {
                    $class = 'full';
                    $title = 'Selesai dari penjahit';
                    $time  = Time::createFromTimestamp($selesai);
                    $title .= ($keterangan_selesai !== 'null' ? ': ' . $keterangan_selesai : '');
                } elseif ($mulai !== null && $selesai === null) {
                    $title = 'Masuk ke penjahit';
                    $time  = Time::createFromTimestamp($mulai);
                    $title .= ($keterangan_mulai !== 'null' ? ': ' . $keterangan_mulai : '');
                }
                break;

            case 6:
                $ico = 'tasks';
                if ($mulai !== null && $selesai !== null) {
                    $class = 'full';
                    $title = 'QC selesai';
                    $time  = Time::createFromTimestamp($selesai);
                    $title .= ($keterangan_selesai !== 'null' ? ': ' . $keterangan_selesai : '');
                } elseif ($mulai !== null && $selesai === null) {
                    $title = 'Masuk QC';
                    $time  = Time::createFromTimestamp($mulai);
                    $title .= ($keterangan_mulai !== 'null' ? ': ' . $keterangan_mulai : '');
                }
                break;

            case 7:
                $ico = 'box-alt';
                if ($mulai !== null && $selesai !== null) {
                    $class = 'full';
                    $title = 'Selesai dipacking';
                    $time  = Time::createFromTimestamp($selesai);
                    $title .= ($keterangan_selesai !== 'null' ? ': ' . $keterangan_selesai : '');
                } elseif ($mulai !== null && $selesai === null) {
                    $title = 'Sedang dipacking';
                    $time  = Time::createFromTimestamp($mulai);
                    $title .= ($keterangan_mulai !== 'null' ? ': ' . $keterangan_mulai : '');
                }
                break;
        }

        $html = '<li class="list-inline-item me-0 position-relative ' . $class . '" data-toggle="tooltip" data-placement="top" title="' . $title . '"><div class="d-flex justify-content-center">';
        $html .= '<div class="text-center"><i class="fal fa-' . $ico . ' icon d-block"></i>';
        $html .= '<span><abbr title="' . $time->humanize() . '">' . $time->day . '/' . $time->month . '</abbr></span></div></div>';
        $html .= '</li>';

        return $html;
    }
}

if (! function_exists('status_pembayaran')) {
    function status_pembayaran($pembayaran, $status, $return = 'html')
    {
        switch ($status) {
            case 2: // tunggu konfirmasi (belum bayar)
                $class   = '';
                $l_class = 'danger';
                $title   = 'Belum ada pembayaran (cek)';
                $pendek  = 'Menunggu pembayaran';
                break;

            case 3: // tunggu konfirmasi (kredit)
                $class   = 'half';
                $l_class = 'warning';
                $title   = 'Sebagian sudah dibayar (cek)';
                $pendek  = 'DP menunggu konfirmasi';
                break;

            case 4: // kredit
                $class   = 'half';
                $l_class = 'warning';
                $title   = 'Sebagian sudah dibayar';
                $pendek  = 'Pembayaran dicicil';
                break;

            case 5: // kelebihan
                $class   = 'full';
                $l_class = 'primary';
                $title   = 'Pembayaran lunas, ada kelebihan';
                $pendek  = 'Lunas, ada kelebihan';
                break;

            case 6: // lunas
                $class   = 'full';
                $l_class = 'success';
                $title   = 'Pembayaran sudah Lunas';
                $pendek  = 'Lunas';
                break;

            default: // belum bayar
                $class   = '';
                $l_class = 'danger';
                $title   = 'Belum ada pembayaran';
                $pendek  = 'Belum dibayar';
                break;
        }

        $sudah_bayar   = 0;
        $tanggal_bayar = '';

        foreach ($pembayaran as $pay) {
            if ($pay->status === '3') {
                $sudah_bayar += $pay->nominal;
                $tanggal_bayar = Time::createFromTimestamp($pay->tanggal_bayar);
            }
        }

        $html = '<li class="list-inline-item me-0 position-relative ' . $class . '" data-toggle="tooltip" data-placement="top" title="' . $title . '"><div class="d-flex justify-content-center">';
        $html .= '<div class="text-center"><i class="fal fa-wallet icon d-block"></i>';
        if ($tanggal_bayar !== '') {
            $html .= '<span><abbr title="' . $tanggal_bayar->humanize() . '">' . $tanggal_bayar->day . '/' . $tanggal_bayar->month . '</abbr></span></div></div>';
        } else {
            $html .= '<span>??/??</span>';
        }
        $html .= '</li>';

        if ($return === 'html') {
            return $html;
        }
        if ($return === 'sudah_bayar') {
            return $sudah_bayar;
        }
        if ($return === 'l_class') {
            return $l_class;
        }
        if ($return === 'label') {
            return $pendek;
        }
    }
}

if (! function_exists('status_pengiriman')) {
    function status_pengiriman($pengiriman, $status)
    {
        switch ($status) {
            case '2': // dikirim sebagian
                $class = 'half';
                $title = 'Sebagian sudah dikirim';
                break;

            case '3': // dikirim semua
                $class = 'full';
                $title = 'Semua pesanan sudah dikirim';
                break;

            default: // belum kirim
                $class = '';
                $title = 'Masih menunggu';
                break;
        }

        $tanggal_kirim = '';

        foreach ($pengiriman as $kirim) {
            $tanggal_kirim = Time::createFromTimestamp($kirim->tanggal_kirim);
        }

        $html = '<li class="list-inline-item me-0 position-relative ' . $class . ' end" data-toggle="tooltip" data-placement="top" title="' . $title . '"><div class="d-flex justify-content-center">';
        $html .= '<div class="text-center"><i class="fal fa-shipping-fast icon d-block"></i>';
        if ($tanggal_kirim !== '') {
            $html .= '<span><abbr title="' . $tanggal_kirim->humanize() . '">' . $tanggal_kirim->day . '/' . $tanggal_kirim->month . '</abbr></span></div></div>';
        } else {
            $html .= '<span>??/??</span>';
        }
        $html .= '</li>';

        return $html;
    }
}

if (! function_exists('replacer')) {
    // source : https://stackoverflow.com/a/48981341/2094645
    function replacer($template, $data)
    {
        if (preg_match_all('/{(.*?)}/', $template, $m)) {
            foreach ($m[1] as $i => $varname) {
                $template = str_replace($m[0][$i], sprintf('%s', $data[$varname]), $template);
            }
        }

        return $template;
    }
}

if (! function_exists('simpan_notif')) {
    function simpan_notif($type, $juragan_id, $invoice_id)
    {
        $juragan    = new \App\Models\JuraganModel();
        $notifikasi = new \App\Models\NotifikasiModel();
        $session    = \Config\Services::session();

        $user_id = $session->get('id');

        $r_users = $juragan->getUsersByJuragan($juragan_id)->getResult();

        foreach ($r_users as $v) {
            if ($v->id !== $user_id) {
                $notifikasi->save([
                    'juragan_id' => $juragan_id,
                    'from'       => $user_id,
                    'for'        => $v->id,
                    'invoice_id' => $invoice_id,
                    'type'       => $type,
                    'created_at' => Time::now(config('App')->appTimezone)->timestamp,
                ]);
            }
        }
    }
}

if (! function_exists('tanggalDefault')) {
    function tanggalDefault($akhir_mulai)
    {
        $time = new Time('now');

        $default_tanggal_mulai = 26;
        $default_tanggal_akhir = 25;
        $setting               = '1';

        $hari_ini      = $time->toLocalizedString('yyyy-MM-dd');
        $bulan_ini     = $time->toLocalizedString('yyyy-MM');
        $datestring    = $hari_ini . ' first day of last month';
        $dt            = date_create($datestring);
        $datestring2   = $hari_ini . ' first day of next month';
        $dt2           = date_create($datestring2);
        $bulan_kemarin = $dt->format('Y-m');
        $bulan_besok   = $dt2->format('Y-m');

        $sekarang        = strtotime($hari_ini);
        $mulai_bulan_ini = strtotime($bulan_ini . '-' . $default_tanggal_mulai);
        // $mulai_bulan_kemarin = strtotime($bulan_kemarin . '-' . $default_tanggal_mulai);

        $akhir_bulan_ini = strtotime($bulan_ini . '-' . $default_tanggal_akhir);
        // $akhir_bulan_besok = strtotime($bulan_besok . '-' . $default_tanggal_akhir);
        if ($setting === '1') {
            if ($sekarang >= $mulai_bulan_ini) {
                $tanggal_mulai = $bulan_ini . '-' . $default_tanggal_mulai;
            } elseif ($sekarang <= $mulai_bulan_ini) {
                $tanggal_mulai = $bulan_kemarin . '-' . $default_tanggal_mulai;
            }

            if ($sekarang <= $akhir_bulan_ini) {
                $tanggal_akhir = $bulan_ini . '-' . $default_tanggal_akhir;
            } elseif ($sekarang >= $akhir_bulan_ini) {
                $tanggal_akhir = $bulan_besok . '-' . $default_tanggal_akhir;
            }
        } else {
            $tanggal_mulai = $time->toLocalizedString('yyyy-MM-01');
            $tanggal_akhir = date('Y-m-t', now());
        }
        if ($akhir_mulai === 'mulai') {
            return $tanggal_mulai;
        }
        if ($akhir_mulai === 'akhir') {
            return $tanggal_akhir;
        }
    }
}

if (! function_exists('satuBulanSebelumnya')) {
    function satuBulanSebelumnya($tanggal)
    {
        return date('Y-m-d', strtotime('-1 month', strtotime($tanggal)));
    }
}

if (! function_exists('satuBulanSelanjutnya')) {
    function satuBulanSelanjutnya($tanggal)
    {
        return date('Y-m-d', strtotime('+1 month', strtotime($tanggal)));
    }
}

if (! function_exists('base64_encode_url')) {
    /**
     * Membuat string agar binary data dapat ditampilkan di url.
     *
     * @param string $string
     *
     * @see https://www.php.net/manual/en/function.base64-encode.php#123098
     */
    function base64_encode_url($string): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($string));
    }
}

if (! function_exists('base64_decode_url')) {
    /**
     * Mengembalikan binary data dari string yang telah dibuat oleh base64_encode_url.
     *
     * @param string $string
     *
     * @see https://www.php.net/manual/en/function.base64-encode.php#123098
     */
    function base64_decode_url($string): string
    {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $string), true);
    }
}

if (! function_exists('str_starts_with')) {
    /**
     * Hanya untuk menyesuaikan fungsi pada PHP8
     */
    function str_starts_with(string $haystack, string $needle): bool
    {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (! function_exists('vite')) {
    /**
     * Get the path to a versioned vite file.
     *
     * @param string $path
     * @param string $manifestDirectory
     *
     * @throws \Exception
     *
     * @return string
     */
    function vite($path, $manifestDirectory = 'assets')
    {
        static $manifest;
        $publicFolder = '';
        $rootPath     = FCPATH;
        $publicPath   = $rootPath . $publicFolder;
        if ($manifestDirectory && ! str_starts_with($manifestDirectory, '/')) {
            $manifestDirectory = "/{$manifestDirectory}";
        }

        if (! $manifest) {
            if (! file_exists($manifestPath = ($rootPath . $manifestDirectory . '/manifest.json'))) {
                throw new Exception('The Mix manifest does not exist.');
            }
            $manifest = json_decode(file_get_contents($manifestPath), true);
        }

        $path = $publicFolder . $path;

        if (! array_key_exists($path, $manifest)) {
            throw new Exception(
                "Unable to locate Mix file: {$path}. Please check your " .
                'webpack.mix.js output paths and try again.'
            );
        }

        return file_exists($publicPath . $manifestDirectory)
                   ? base_url($manifestDirectory . '/' . $manifest[$path]['file'])
                   : $manifestDirectory . $manifest[$path]['file'];
    }
}

if (! function_exists('urai_keterangan')) {
    /**
     * Pecah teks keterangan bebas menjadi kelompok berlabel (item, bahan,
     * ukuran, pembayaran, dll) supaya tidak ditampilkan sebagai satu paragraf
     * panjang. Baris yang tidak cocok dengan aturan apa pun tetap ditampilkan
     * di kelompok "Catatan lain" sehingga tidak ada informasi yang hilang.
     *
     * @return array<int, array{key: string, label: string, ikon: string, isi: array<int, string>}>
     */
    function urai_keterangan($teks)
    {
        if (trim((string) $teks) === '') {
            return [];
        }

        $label = [
            'item'    => ['Item & ukuran jadi', 'fa-box-open'],
            'bayar'   => ['Pembayaran', 'fa-wallet'],
            'sewa'    => ['Jadwal sewa', 'fa-briefcase'],
            'waktu'   => ['Deadline & instruksi waktu', 'fa-calendar-check'],
            'jaminan' => ['Jaminan', 'fa-shield-halved'],
            'bahan'   => ['Bahan', 'fa-tshirt'],
            'ukuran'  => ['Tabel ukuran detail', 'fa-ruler-combined'],
            'model'   => ['Spesifikasi model & jahitan', 'fa-scissors'],
            'pemilik' => ['Nama pemilik ukuran', 'fa-user'],
            'lain'    => ['Catatan lain', 'fa-note-sticky'],
        ];

        // dicocokkan dari awal baris, urut = prioritas
        $awalan = [
            'item'    => '^(SEWA\s+)?(TOTAL\s(?!HARGA|BAYAR|BIAYA|ONGKIR)\w+|JUMLAH\s(?!HARGA|BAYAR|BIAYA|ONGKIR)\w+|JAS|CELANA|CELANAN|VEST|KEMEJA|SEPATU|PANTOFEL|BLENZER|BLAZER|HODIE|HOODIE|JASKET|JAKET|ROK|TAS|PINGGIR|SLIMFIT|BK\s?-?\d+|TZ\s?-?\d+|SK\s?-?\d+|KS\s?-?\d+|SL\s?-?\d+|PESAN|PESANAN|BELI|STOK|STOCK|ORDER|PRODUK|BARANG)\b',
            'bayar'   => '^(SUDAH\s+)?(DP|DOW?N\s?PAYMENT|PELUNASAN|POLONG|LUNAS|BAYAR|BAY|PEMBAYARAN|SISA|CICILAN|CICIL|KREDIT|TRANSFER|REFUND|TAGIHAN|INVOICE|TOKO|ONLINE|DISKON|CASH|TUNAI|ONGKIR|HARGA|BIAYA|TF|(TOTAL|JUMLAH)\s?(HARGA|BAYAR|BIAYA|ONGKIR))\b',
            'sewa'    => '^(DIAMBIL|DI\s?AMBIL|TERAMBIL|TERAMBILE|AMBIL|DIKEMBALIKAN|KEMBALI|PENGEMBALIAN|MAK\s?KEMBALI|DIPAKAI|DIPAKE|SEWANYA|TANGGAL\s?SEWA|SEWA)\b',
            'waktu'   => '^(DEADLINE|DATELINE|DETLINE|DIKERJAKAN|URGENT|PALING|SEBELUM|JADWAL|LEWAT|HURRY|SPEED|TARGET|MAKSIMAL|MUNDUR)\b',
            'jaminan' => '^(JAMINAN|SIM|SIMC|KTP|NOTE|NB|CATATAN|REVISI|ERROR|SALAH|TITIP|BATAL|PULANG)\b',
            'bahan'   => '^(BAHAN|KAIN|FABRIC|WARNA|MOTIF|BORDIR|BORDIRAN|SABLON|EMBROIDERY|FURING|TISSU|CUSIN|CUSHION)\b',
            'ukuran'  => '^(UKURAN|SIZE|LING|LINGKAR|KETIAK|PANGGUL|PJ|P\.?\s?JAS|P\.?\s?PANJANG|P\.?\s?BK|L\.?\s?DADA|L\.?\s?PINGGUL|L\.?\s?PINGGANG|L\.?\s?BAHU|L\.?\s?TANGAN|PANJANG|PANJAN|LEBAR|BAHU|PAHA|LUTUT|SIKU|VASAK|DADA|PINGGUL|PINGGANG|LENGAN|TANGAN|PINGGIR|DEPAN|BELAKANG|PERUT|PANTAT|LEHER|KERONGKONG)\b',
            'model'   => '^(MODEL|KANCING|SAKU|SACKU|KANTONG|BELAHAN|RESLETING|RITSLING|TANPA|ADA|TIDAK|LIST|KERAH|JAHIT|LIPIT|KELIM|LAPEL|VARIASI|KOMBINASI|TAMBAH|KURANG|PENDEK|PANJANGKAN|TAHUN|GAYA|DESAIN|TANGAN|PAKAI|DASI|BELT|RETUR|TUKAR)\b',
            'pemilik' => '^(A\.?\s?N\.?|AN|PUNYA|MILIK|SAMPEL|SAMPLE|NAMA|PEMILIK|COWOK|CEWEK|CUSTOMER|CUSTOM|SISWA)\b',
        ];

        // jaring pengaman: kata kunci di tengah baris, urut = prioritas
        $isi = [
            'bayar'   => '\b(VIA|BY|TF|TUNAI|QRIS|BCA|MANDIRI|BNI|BRI|CIMB|OVO|GOPAY)\b|\bDP\b|\d+\s?(RB|JT)\b',
            'ukuran'  => ':\s*-?\s*\d+([.,]\d+)?\s?(CM|KG|PCS)?$|\b\d{2,3}([.,]\d)?\s?(CM|KG)\b',
            'sewa'    => '\b(DIAMBIL|DI\s?AMBIL|KEMBALI|TERAMBILE|TERAMBIL)\b',
            'waktu'   => '\b(DEADLINE|DATELINE|URGENT|PALING\s?(LAMBAT|AWAL))\b',
            'jaminan' => '\b(JAMINAN|SIM\s?[CM]\b|KTP|NOTE)\b',
            'bahan'   => '\b(BAHAN|KAIN|FABRIC|BORDIR|SABLON)\b',
            'pemilik' => '\bA\.?\s?N\.?\b',
        ];

        $hasil = array_fill_keys(array_keys($label), []);

        foreach (preg_split('/\r\n|\r|\n/', (string) $teks) as $baris) {
            $bersih = bersihkan_baris_keterangan($baris);

            if ($bersih === '') {
                continue;
            }

            $cek = strtoupper(preg_replace('/\s+/u', ' ', $bersih));

            foreach ($awalan as $key => $regex) {
                if (preg_match('/' . $regex . '/u', $cek)) {
                    $hasil[$key][] = $bersih;
                    continue 2;
                }
            }

            foreach ($isi as $key => $regex) {
                if (preg_match('/' . $regex . '/iu', $bersih)) {
                    $hasil[$key][] = $bersih;
                    continue 2;
                }
            }

            $hasil['lain'][] = $bersih;
        }

        $keluaran = [];

        foreach ($label as $key => $meta) {
            if ($hasil[$key] === []) {
                continue;
            }

            $keluaran[] = [
                'key'   => $key,
                'label' => $meta[0],
                'ikon'  => $meta[1],
                'isi'   => $hasil[$key],
            ];
        }

        return $keluaran;
    }
}

if (! function_exists('bersihkan_baris_keterangan')) {
    /**
     * Buang penanda daftar (#, *, -, 1.) di awal baris keterangan.
     * Data lama ada yang berisi byte UTF-8 tidak valid, jadi siapkan fallback.
     */
    function bersihkan_baris_keterangan($baris)
    {
        $bersih = preg_replace('/^[\s#*\->\[\]()·•]+|[\s:]+$/u', '', $baris);

        if ($bersih === null) {
            $bersih = preg_replace('/^[\s#*\->\[\]()]+|[\s:]+$/', '', (string) iconv('UTF-8', 'UTF-8//IGNORE', (string) $baris));
        }

        $bersih = (string) preg_replace('/^\d{1,2}[.)]{1,2}\s+/u', '', (string) $bersih);

        return trim((string) $bersih);
    }
}
