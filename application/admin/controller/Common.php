<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/15
 * Time: 上午9:33
 */

namespace app\admin\controller;
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
            return $this->redirect('part/login/checklogin');
        }
    }
}