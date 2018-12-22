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

//获取俩点间距离

/**
* 求两个已知经纬度之间的距离,单位为米
*
 * @param lng1 $ ,lng2 经度
* @param lat1 $ ,lat2 纬度
* @return float 距离，单位米
* @author www.Alixixi.com
*/
function getdistance($lng1, $lat1, $lng2, $lat2) {
    // 将角度转为狐度
    $radLat1 = deg2rad($lat1); //deg2rad()函数将角度转换为弧度
    $radLat2 = deg2rad($lat2);
    $radLng1 = deg2rad($lng1);
    $radLng2 = deg2rad($lng2);
    $a = $radLat1 - $radLat2;
    $b = $radLng1 - $radLng2;
    $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137 * 1000;
//    var_dump($lng1);
//    var_dump($lat1);
//    var_dump($lng2);
//    var_dump($lat2);
//    dd($s);
    if($s/1000<1){
        return round($s,2).'m';
    }else{
        return round(($s/1000),2).'km';
    }
}

//二维数组 排序
function arr_sort($array,$key,$order="asc"){//asc是升序 desc是降序
    $arr_nums = $arr = array();
    foreach($array as $k => $v){
        $arr_nums[$k] = $v[$key];
    }

    if($order == 'asc'){
        asort($arr_nums);

    }else{
        arsort($arr_nums);
    }

    $i=0;
    foreach($arr_nums as $k=>$v){

        $arr[$i]=$array[$k];
        $i++;
    }
    return $arr;
}

function douhao($num=1500900065,$res=''){
    $a = $num/1000;
    if( $a>1){
        $res = ','.substr($num,strlen($num)-3).$res;

        $b = substr($num,0,strlen($num)-3);
        return douhao($b,$res);
    }else{
        //var_dump($res);
        $res = $num.$res;
        return $res;
    }
}

//根据经纬度获取省市区

function byLtGetCity($longitude,$latitude)
{
    $res = @file_get_contents('http://apis.map.qq.com/ws/geocoder/v1/?location=' . $latitude . ',' . $longitude . '&key=KBUBZ-N2YH6-JZHS6-MF7TF-U4HGT-TLFC7&get_poi=1');
    $result = json_decode($res,true);
    //dd($result['result']);
    //["province"] =&gt; string(12) "黑龙江省"
    //    ["city"] =&gt; string(12) "哈尔滨市"
    //    ["district"] =&gt; string(9) "南岗区"
    //    ["street"] =&gt; string(9) "宣化街"
    $address_component = $result['result']['address_component'];
    return array($result['result']['province'],$result['result']['city'],$result['result']['district']);
}




