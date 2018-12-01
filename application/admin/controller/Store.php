<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/12/1
 * Time: 3:26 PM
 */

namespace app\admin\controller;


class Store
{
   //生成门店二维码

    public function qr_code($url='')
    {
        //引入 qrcode类
        Vendor('phpqrcode.phpqrcode');
        $qrurl = $url?$url:$_SERVER['SCRIPT_NAME'].'/admin/store/url?a=2';
        dd(\QRcode::png($qrurl, $outfile = false, $level = 2, $size = 3, $margin = 4, $saveandprint=false) );
    }

    public function url()
    {
        dd(333);
    }
}