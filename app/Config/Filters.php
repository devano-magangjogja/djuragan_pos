<?php

namespace Config;

use App\Filters\Auth;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseConfig
{
    /**
     * Daftar filter bawaan framework. Dibiarkan kosong supaya CI tetap mengambil
     * nilai bawaannya ('forcehttps', 'pagecache', 'performance'), kecuali untuk
     * posisi yang kita ubah di constructor di bawah.
     *
     * @var array{before?: list<string>, after?: list<string>}
     */
    public array $required = [];

    public function __construct()
    {
        parent::__construct();

        // CI 4.7 menjalankan filter 'toolbar' dari daftar $required bawaan framework,
        // bukan hanya dari $globals['after']. Tiap kali filter ini jalan, satu file
        // JSON ditulis ke writable/debugbar dan tidak pernah dibersihkan.
        if (! config(Toolbar::class)->enabled) {
            $base                    = config(BaseFilters::class);
            $this->aliases           = $this->aliases + $base->aliases;
            $this->globals['after']  = array_values(array_diff($this->globals['after'], ['toolbar']));
            $this->required['after'] = array_values(array_diff($base->required['after'], ['toolbar']));
        }
    }

    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array
     */
    public $aliases = [
        'auth'          => Auth::class,
        'csrf'          => CSRF::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'toolbar'       => DebugToolbar::class,
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array
     */
    public $globals = [
        'before' => [
            // 'honeypot',
            'csrf' => ['except' => ['webhook', 'webhook/*']],
            // 'invalidchars',
        ],
        'after' => [
            'toolbar',
            // 'honeypot',
            'secureheaders',
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'post' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don’t expect could bypass the filter.
     *
     * @var array
     */
    public $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     *
     * @var array
     */
    public $filters = [];
}
