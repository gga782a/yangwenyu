<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/8
 * Time: 下午3:57
 */

namespace app\index\controller;


class Index
{
    public function index()
    {
        return view('index');
    }

    public function more()
    {
        return view('more');
    }
}




