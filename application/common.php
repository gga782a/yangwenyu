<?php

function _config($config_var='')
{
    $val = db('config')->where(['config_var'=>$config_var])->value('config_val');
    return $val;
}

//调用接口
function curl_request($url, $post = [])
{
//    dump($post);
    //dd($url);
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
    //curl_setopt($curl, CURLOPT_REFERER, "http://yilingjiu.cn");
    if ($post) {
        //dd(11);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    }
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
//dd($data);
    if (curl_errno($curl)) {
        //dd(22);
        return curl_error($curl);
    }
    curl_close($curl);
    //echo "<pre />";
    //var_dump($data);
    return $data;
}

//跳转url

function redirecturl($var){
    return redirect('wechat/authorize/get_url',['redirecturl'=>$var]);
}



