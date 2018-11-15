<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/15
 * Time: 上午9:33
 */

namespace app\admin\controller;


class Index extends Common
{
    public function index()
    {
        //dd(1);
        return view('index');
    }

    public function more()
    {
        return view('more');
    }
}