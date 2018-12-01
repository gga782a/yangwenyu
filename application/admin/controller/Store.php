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

    public function qr_code($url='',$level=3,$size=7)
    {
        //dd(ROOT_PATH);
        //引入 qrcode类
        Vendor('phpqrcode.phpqrcode');
        //实例化qrcode类
        //$qrcode = new \QRcode();
        //路径
        $pathname = ROOT_PATH.'/public/qr_uploads/';
        if(!is_dir($pathname)) { //若目录不存在则创建之
            mkdir($pathname,0777,true);
        }
        //图片名
        $ad = 'qrcode_' . rand(10000,99999) . '.png';
        //图片保存路径
        $savepath = $pathname.$ad;
        $errorCorrectionLevel =intval($level) ;//容错级别
        $matrixPointSize = intval($size);//生成图片大小
        $url = 'http://www.ztwlxx.net?a=2';
        //return $url;
        \QRcode::png($url, $savepath, $errorCorrectionLevel, $matrixPointSize, 2);
        return '/yangwenyu/public/qr_uploads/'.$ad;
        //dd($png);
        //$res = file_put_contents($savepath,$png);
//        if($res !== false){
//            return $savepath;
//        }
    }

    public function url()
    {

        $img = $this->qr_code();
        //dd($img);
        echo "<img src = '$img' />";
    }
}