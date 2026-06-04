<?php

namespace app\controller;

use support\Request;
use support\Response;

class IndexController
{
    public function index(Request $request): Response
    {
        return redirect('/h5/index.html');
    }

    public function admin(Request $request): Response
    {
        return redirect('/admin/index.html');
    }

    public function h5(Request $request): Response
    {
        return redirect('/h5/index.html');
    }

    public function view(Request $request)
    {
        return view('index/view', ['name' => 'webman']);
    }

    public function json(Request $request)
    {
        return ok(['msg' => 'ok']);
    }

    public function fail(Request $request)
    {
        return fail('参数错误');
    }
}
