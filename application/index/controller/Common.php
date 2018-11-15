<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/15
 * Time: 上午9:13
 */

namespace app\index\controller;
use think\Controller;
use think\Request;
use think\Session;

class Common extends Controller
{
    public  $id;
    public function __construct(Request $request = null)
    {
        $this->id = Session::get('user_id');
        $this->check();
    }

    public function check(){
        if(empty($this->id)){
           dd(11);
        }else{
            dd(22);
        }
    }
}