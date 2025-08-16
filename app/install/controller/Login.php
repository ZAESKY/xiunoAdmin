<?php

namespace app\install\controller;

class Login
{
    public function index(){
        return message('success', true, ['url' => '/'.(session('login_address')??'admin').'.php']);
    }
}