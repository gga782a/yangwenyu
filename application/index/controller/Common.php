<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/15
 * Time: 上午9:13
 */

namespace app\index\controller;
use think\Controller;
use think\Cookie;
use think\Request;
use think\Session;

class Common extends Controller
{

    public function __construct(Request $request = null)
    {
        $this->check();
    }

    public function check(){
        $member_id = Session::get('member_id');
        //dd($member_id);
        return $member_id;
    }
}