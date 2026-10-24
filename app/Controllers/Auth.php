<?php

namespace App\Controllers;

use App\Libraries\PresensiSso;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class Auth extends BaseController
{
    public function index()
    {
        $validation = \Config\Services::validation();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('signin');
        }

        if (!$validation->withRequest($this->request)->run()) {
            $data = [
                'title' => 'Masuk',
                'validation' => $validation,
            ];

            return view('masuk', $data);
        }

        $username = (string) $this->request->getPost('username');
        $password = (string) $this->request->getPost('password');

        // SSO Presensi didahulukan; kalau SSO tidak aktif, menolak kredensial,
        // atau server tak terjangkau, verifikasi jatuh ke database lokal apa
        // adanya (akun lama admin/superadmin/viewer tetap bisa masuk).
        $arr = $this->verifikasiSso($username, $password)
            ?? $this->verifikasiLokal($username, $password);

        session()->set($arr['sesi']);

        return redirect()->to($arr['redirect'])->with('status', $arr['status']);
    }

    /**
     * Coba login lewat SSO Presensi.
     *
     * Mengembalikan susunan sesi (`$arr`) saat Presensi mengonfirmasi kredensial,
     * atau null saat pemanggil harus lanjut ke verifikasi lokal (SSO mati,
     * kredensial ditolak, atau server tak terjangkau).
     */
    private function verifikasiSso(string $username, string $password): ?array
    {
        $sso = new PresensiSso();

        if (! $sso->aktif()) {
            return null;
        }

        $hasil = $sso->verifikasi($username, $password);

        if (($hasil['state'] ?? '') !== 'valid') {
            // invalid / unavailable -> serahkan ke database lokal
            return null;
        }

        $get = $this->sinkronkanUserSso((array) ($hasil['data'] ?? []), $sso->levelBaru());

        if ($get === null) {
            return null;
        }

        // kata sandi tidak pernah diambil dari input SSO, jadi tidak ada migrasi
        // md5 di jalur ini; _pass cukup membaca status/level dari baris lokal.
        return $this->_pass($get);
    }

    /**
     * Verifikasi memakai database lokal, logika lama (md5 warisan + bcrypt).
     *
     * @return array{sesi:array<string,mixed>,redirect:string,status:string}
     */
    private function verifikasiLokal(string $username, string $password): array
    {
        $userModel = new UserModel();
        $get       = $userModel->where('username', $username)->first();

        if ($get === null || empty($get)) {
            return [
                'sesi'     => ['logged' => false],
                'redirect' => '/auth/index',
                'status'   => '<div class="alert alert-danger"><strong class="d-block">Nay!</strong>Kamu siapa? daftar aja dulu.</div>',
            ];
        }

        // cek length password in db
        if (strlen($get->password) === 32) {
            // jika password gunakan md5() — berguna untuk edit password lewat
            // phpmyadmin; kalau cocok, naikkan simpanan ke bcrypt lewat _pass()
            if (md5($password) === $get->password) {
                return $this->_pass($get, $password);
            }

            return $this->gagalSandi();
        }

        if (password_verify($password, $get->password)) {
            return $this->_pass($get);
        }

        return $this->gagalSandi();
    }

    /** @return array{sesi:array<string,mixed>,redirect:string,status:string} */
    private function gagalSandi(): array
    {
        return [
            'sesi'     => ['logged' => false],
            'redirect' => '/auth/index',
            'status'   => '<div class="alert alert-warning"><strong class="d-block">Nay!</strong>Ingat lagi apa kata sandimu, ku tungguin deh.</div>',
        ];
    }

    /**
     * Samakan satu akun lokal dengan data yang dikirim Presensi.
     *
     * Cocokkan lewat sso_id, lalu username, lalu email. Bila belum ada, akun
     * baru dibuat dengan level bawaan SSO (cs) dan status active tanpa relasi
     * juragan, lalu langsung muncul di daftar pengguna untuk ditata superadmin.
     * Bila sudah ada, hanya jejak SSO yang kosong yang diisi; level & status
     * TIDAK disentuh karena itu hak superadmin.
     *
     * @param array<string, mixed> $data
     */
    private function sinkronkanUserSso(array $data, string $levelBaru): ?object
    {
        $userModel   = new UserModel();
        $ssoUsername = mb_strtolower(trim((string) ($data['username'] ?? '')));
        $ssoEmail    = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $nama        = trim((string) ($data['nama'] ?? ''));
        $nip         = trim((string) ($data['nip'] ?? ''));
        $ssoId       = isset($data['id']) ? (int) $data['id'] : null;

        if ($ssoUsername === '' && $ssoEmail === '') {
            // tanpa pegangan unik apa pun, tidak aman untuk mencocokkan/membuat akun
            return null;
        }

        $get = null;

        if ($ssoId !== null && $ssoId > 0) {
            $get = $userModel->where('sso_id', $ssoId)->first();
        }

        if ($get === null && $ssoUsername !== '') {
            $get = $userModel->where('username', $ssoUsername)->first();
        }

        if ($get === null && $ssoEmail !== '') {
            $get = $userModel->where('email', $ssoEmail)->first();
        }

        if ($get === null) {
            $userModel->insert([
                'username'       => $ssoUsername !== '' ? $ssoUsername : $ssoEmail,
                'password'       => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                'name'           => $nama !== '' ? $nama : ($ssoUsername !== '' ? $ssoUsername : $ssoEmail),
                'email'          => $ssoEmail,
                'level'          => $levelBaru,
                'status'         => 'active',
                'sso_id'         => $ssoId,
                'nip'            => $nip !== '' ? $nip : null,
                'sumber'         => 'sso',
                'login_terakhir' => now(),
            ]);

            return $userModel->where('username', $ssoUsername !== '' ? $ssoUsername : $ssoEmail)->first();
        }

        // sudah ada: isi jejak SSO yang masih kosong, jangan sentuh level/status
        $ubah = ['id' => $get->id, 'login_terakhir' => now()];

        if ($ssoId !== null && $ssoId > 0 && (int) ($get->sso_id ?? 0) === 0) {
            $ubah['sso_id'] = $ssoId;
        }

        if ($nip !== '' && (string) ($get->nip ?? '') === '') {
            $ubah['nip'] = $nip;
        }

        if (($get->sumber ?? '') === '' || $get->sumber === null) {
            $ubah['sumber'] = 'sso';
        }

        $userModel->save($ubah);

        return $userModel->find($get->id);
    }

    protected function _pass($db, $password_to_update = false)
    {
        $userModel = new UserModel();

        switch ($db->status) {
            case 'pending':
                $arr = [
                    'sesi' => [
                        'logged' => false,
                    ],
                    'redirect' => '/auth/index',
                    'status' => '<div class="alert alert-warning"><strong class="d-block">Nay!</strong>Kamu sudah terdaftar kok, tapi cek email dulu ya.</div>',
                ];
                break;

            case 'blocked':
                $arr = [
                    'sesi' => [
                        'logged' => false,
                    ],
                    'redirect' => '/auth/index',
                    'status' => '<div class="alert alert-danger"><strong class="d-block">Grrrr!</strong>Kamu dilarang masuk.</div>',
                ];
                break;

            case 'inactive':
                $arr = [
                    'sesi' => [
                        'logged' => false,
                    ],
                    'redirect' => '/auth/index',
                    'status' => '<div class="alert alert-warning"><strong class="d-block">Nay!</strong>Tunggu validasi dari admin ya, atau hubungi admin segera.</div>',
                ];

                break;

            default:
                // save for update `login_terakhir`
                $data['id'] = $db->id;
                $data['login_terakhir'] = now();

                // jika password dari md5() perlu diupdate
                if ($password_to_update !== false) {
                    $data['password'] = password_hash($password_to_update, PASSWORD_BCRYPT);
                }

                $userModel->save($data);

                $redirect = '/user';
                if ($db->level === 'admin' || $db->level === 'superadmin') {
                    $redirect = '/admin';
                }

                $arr = [
                    'sesi' => [
                        'id' => $db->id,
                        'username' => $db->username,
                        'name' => $db->name,
                        'email' => $db->email,
                        'level' => $db->level,
                        'logged' => true,
                    ],

                    'redirect' => $redirect,
                    'status' => '<div class="alert alert-sucess"><strong class="d-block">Hay!</strong>Jangan lupa bahagia ya.</div>',
                ];
                break;
        }

        return $arr;
    }

    public function daftar()
    {
        $validation = \Config\Services::validation();
        $userModel = new UserModel();

        if ($this->request->getPost()) {
            $validation->setRuleGroup('signup');
        }

        if (!$validation->withRequest($this->request)->run()) {
            $data = [
                'title' => 'Daftar',
                'validation' => $validation,
            ];

            return view('daftar', $data);
        }
        $userModel->insert([
            'username' => $this->request->getPost('username'),
            'password' => password_hash($this->request->getPost('password'), PASSWORD_BCRYPT),
            'name' => $this->request->getPost('nama'),
            'email' => $this->request->getPost('email'),
        ]);

        return redirect()->to('/auth/daftar')
            ->with('status', '<div class="alert alert-info"><strong class="d-block">Yay!</strong>Kamu sudah terdaftar, cek email dulu ya.</div>');
    }

    /**
     * Halaman untuk meminta link token perubahan kata sandi
     *
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function lupa()
    {
        $validation = \Config\Services::validation();
        $userModel = new UserModel();

        if ($this->request->getPost()) {
            // regex: https://regex101.com/r/pZvhFO/2
            $validation->setRules([
                'username' => [
                    'label' => 'nama pengguna',
                    'rules' => 'required|regex_match[/^(?:\w+|\w+([+\.-]?\w+)*@\w+([\.-]?\w+)*(\.[a-zA-z]{2,4})+)$/]',
                ],
            ]);
        }

        if (!$validation->withRequest($this->request)->run()) {
            $data = [
                'title' => 'Lupa Sandi',
                'validation' => $validation,
            ];

            return view('lupa', $data);
        }

        $username = $this->request->getPost('username');

        // cek username atau email
        // jika bernilai false, artinya input username merupakan username yang valid
        // jika bukan bernilai false, artinya input username merupakan email yang valid
        if (filter_var($username, FILTER_VALIDATE_EMAIL) !== false) {
            $get = $userModel->where('email', $username)->first();
        } else {
            $get = $userModel->where('username', $username)->first();
        }

        // kirim email menggunakan PHPMailer jika $get bukan bernilai null
        if ($get !== null) {
            $mail = new PHPMailer(true);

            $config = config('email');

            try {
                //Server settings
                // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->isSMTP();
                $mail->Host = $config->SMTPHost;
                $mail->SMTPAuth = true;
                $mail->Username = $config->SMTPUser;
                $mail->Password = $config->SMTPPass;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $config->SMTPPort;

                //Recipients
                $mail->setFrom($config->fromEmail, $config->fromName);
                $mail->addAddress($get->email, esc($get->name));     //Add a recipient
                $mail->addReplyTo('sevenincdata@gmail.com');

                // TOKEN
                // membuat token untuk reset kata sandi
                // token dibuat dari kombinasi id & time yang ditambah 1jam
                // token dibuat menggunakan encrypter
                // token dibuat on the fly, tidak disimpan di database
                $valid_until = Time::now()->addHours(1);
                $plain_token = 'reset|' . $get->id . '|' . $valid_until->toDateTimeString();
                $encrypter = \Config\Services::encrypter();

                $enkrip_token = $encrypter->encrypt($plain_token);
                $token = base64_encode_url($enkrip_token);

                //Content
                $mail->isHTML(true);
                $mail->Subject = 'Konfirmasi Reset Password';
                $mail->Body = 'Silahkan klik Link untuk reset password ' . site_url('auth/reset') . '?token=' . $token;

                $mail->send();
                // echo 'Message has been sent';
                return redirect()->to('/auth/lupa')
                    ->with('status', '<div class="alert alert-info">Surel berisi link token untuk mengatur ulang kata sandi telah dikirimkan ke email terdaftar.</div>');
            } catch (Exception $e) {
                // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                return redirect()->to('/auth/lupa')
                    ->with('status', '<div class="alert alert-info">Surel gagal dikirim, hubungi admin web.</div>');
            }
        }

        // jika username/email tidak ditemukan
        // kirim notif jika surel telah dikirimkan
        return redirect()->to('/auth/lupa')
            ->with('status', '<div class="alert alert-info">Surel telah dikirimkan ke email terdaftar.</div>');
    }

    /**
     * Halaman untuk mengatur ulang kata sandi
     *
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function reset()
    {
        $validation = \Config\Services::validation();
        $userModel = new UserModel();

        // cek apakah ada token yang dikirimkan
        // jika tidak ada, redirect ke halaman lupa kata sandi
        // jika ada, cek apakah token valid
        // jika tidak valid, redirect ke halaman lupa kata sandi
        $getToken = $this->request->getGet('token');

        if (empty($getToken)) {
            return redirect()->to('/auth/lupa')
                ->with('status', '<div class="alert alert-warning">Token tidak ditemukan.</div>');
        }

        $encrypter = \Config\Services::encrypter();
        $binary_token = base64_decode_url($getToken);
        $dekrip_token = $encrypter->decrypt($binary_token);

        // explode token untuk mendapatkan id user & valid_until
        $explode_token = explode('|', $dekrip_token);

        // jika token merupakan token reset kata sandi
        if ($explode_token[0] === 'reset') {
            // cek apakah token masih berlaku
            // jika tidak berlaku, redirect ke halaman lupa kata sandi
            $valid_until = Time::parse($explode_token[2]);

            $now = Time::now();
            if ($now->isAfter($valid_until)) {
                // token sudah tidak berlaku
                return redirect()->to('/auth/lupa')
                    ->with('status', '<div class="alert alert-warning">Token sudah tidak berlaku.</div>');
            }

            // jika token masih berlaku, tampilkan form reset kata sandi
            // cek terlebih dahulu, apakah ID user ada di database
            $get_user = $userModel->find($explode_token[1]);

            if ($get_user !== null) {
                $data = [
                    'title' => 'Reset Kata Sandi',
                    'id' => $get_user->id,
                    'validation' => $validation,
                ];

                return view('reset', $data);
            }

            return redirect()->to('/auth/lupa')
                ->with('status', '<div class="alert alert-warning">User tidak ditemukan.</div>');
        }

        // token bukan merupakan token reset kata sandi
        return redirect()->to('/auth/lupa')
            ->with('status', '<div class="alert alert-warning">Token tidak valid.</div>');
    }

    /**
     * Proses simpan kata sandi baru
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function simpanSandiBaru()
    {
        $validation = \Config\Services::validation();
        $userModel = new UserModel();

        $validation->setRules([
            'sandi_baru' => 'required|min_length[4]|max_length[255]',
            'ulangi_sandi_baru' => 'required|matches[sandi_baru]',
        ]);

        $validation->withRequest($this->request)->run();

        if ($validation->getErrors()) {
            return redirect()->to('/auth/reset')
                ->withInput()->with('validation', $validation->getErrors());
        }

        $id = $this->request->getPost('id');
        $password = $this->request->getPost('sandi_baru');

        $user = $userModel->find($id);

        if ($user !== null) {
            $userModel->save([
                'id' => $id,
                'password' => password_hash($password, PASSWORD_BCRYPT),
            ]);

            return redirect()->to('/auth')
                ->with('status', '<div class="alert alert-success">Kata sandi berhasil diubah.</div>');
        }

        return redirect()->to('/auth/reset')
            ->with('status', '<div class="alert alert-warning">User tidak ditemukan.</div>');
    }

    public function keluar()
    {
        // hapus sesi
        // tidak menggunakan `session_destroy()` agar flashdata logout keluar
        session()->remove(['id', 'username', 'name', 'email', 'level', 'logged']);

        // redirect dan membawa pesan keluar
        return redirect()->to('/')->with('status', '<div class="alert alert-success"><strong class="d-block">Yay!</strong>Sampai jumpa lagi ya.');
    }
}
