<?php
require 'application/common.php';
ini_set('default_socket_timeout', -1);
set_time_limit(0);
// $redis = new Redis();
// $redis->connect('127.0.0.1',6379,0);
//        $this->redis->setOption();
//        $redis->setOption();
//        $redis->setOption(redis::OPT_PREFIX,'tp_');
$redis=redis();
$redis->psubscribe(array('__keyevent@0__:expired'), 'keyCallback' );

function keyCallback($redis, $pattern, $chan, $msg)
{
    $res=explode(':',$msg);
    if($res[0]=='out_time'){
        $json['out_time']=$msg;
        $res=curl_request('http://api.ztwlxx.club/wechat/payment/pintuan_refund',$json);
        var_dump($res);
        echo "$msg\n\n";
    }else{
        echo 'error'.$msg;
    }
}

//
//function curl_request_s($url, $post = [])
//{
//    $curl = curl_init();
//    curl_setopt($curl, CURLOPT_URL, $url);
//    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
//    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
//    curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
//    curl_setopt($curl, CURLOPT_REFERER, "http://www.ztwlxx.club");
//    if ($post) {
//        curl_setopt($curl, CURLOPT_POST, 1);
//        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
//    }
//    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
//    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
//    $data = curl_exec($curl);
//    if (curl_errno($curl)) {
//        return curl_error($curl);
//    }
//    curl_close($curl);
//    return $data;
//}
//?>