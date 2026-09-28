<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Auth implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     * By default it should not return anything during
     * normal execution. However, when an abnormal state
     * is found, it should return an instance of
     * CodeIgniter\HTTP\Response. If it does, script
     * execution will end and that Response will be
     * sent back to the client, allowing for error pages,
     * redirects, etc.
     *
     * @param array|null $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $isLogged = false;
        if (session()->has('logged') && session()->get('logged')) {
            $isLogged = true;
        }

        // users logged in & trying to access login page
        if ($isLogged && $arguments === null) {
            if (in_array(session('level'), ['admin', 'superadmin'], true)) {
                return redirect()->to('/admin');
            }

            return redirect()->to('/user');
        }

        // users logged in
        if ($isLogged && $arguments !== null) {
            $adminRoles = ['admin', 'superadmin'];
            $allRoles   = ['admin', 'superadmin', 'user'];

            sort($arguments);
            sort($adminRoles);
            sort($allRoles);

            // trying to access admin page
            if ($arguments === $adminRoles) { // admin & superadmin
                if (in_array(session()->get('level'), ['admin', 'superadmin'], true)) {
                    return;
                }
            }

            if ($arguments === ['admin']) { // admin only
                if (session()->get('level') === 'admin') {
                    return;
                }
            }

            if ($arguments === ['superadmin']) { // superadmin only
                if (session()->get('level') === 'superadmin') {
                    return;
                }
            }

            // trying to access user page
            if ($arguments === ['user']) { // user only
                if (session()->get('level') === 'cs') {
                    return;
                }
            }

            // used for logout page
            if ($arguments === $allRoles) { // admin, superadmin & user
                if (in_array(session()->get('level'), ['admin', 'superadmin', 'cs'], true)) {
                    return;
                }
            }
        }

        // users not logged in
        // used for `auth` middleware
        if (! $isLogged && $arguments === null) {
            return;
        }

        return redirect()->to('/auth');
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param array|null $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
