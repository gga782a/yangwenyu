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

class Common extends Controller
{

    public function __construct(Request $request = null)
    {
        $this->check();
    }

    public function check(){
//        var_dump(input('member_id'));
//        if(input('member_id')<=0){
//            dd(333333);
//           return $this->redirect('wechat/authorize/get_url');
//        }else{
            //Cookie::set('member_id',input('member_id'),365*86400);
            //dd(Cookie::get('member_id'));
        $url = 'http://www.yilingjiu.cn/index/index/index';
        header("location:".$url);
        exit();
        //}
    }
}