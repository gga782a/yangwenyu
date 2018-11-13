<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/10/20
 * Time: 上午11:18
 */

namespace app\pub\controller;

use think\Controller;

class Serversocket extends Controller
{
    //socket链接测试

    public function createsocket()
    {
        //创建服务端的socket套接流,net协议为IPv4，protocol协议为TCP
        $socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
    }
}