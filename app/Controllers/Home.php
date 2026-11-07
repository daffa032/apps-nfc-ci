<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        $data['page'] = 'login/pages/login/login';

        return view('login/layouts/login-home', $data);
    }
}
