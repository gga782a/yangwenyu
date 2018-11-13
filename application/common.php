<?php
//加解密函数
function encrypts($data)
{
    $key    =    config('Asc_Key');
    $key    =    md5($key);
    $x      =    0;
    $len    =    strlen($data);
    $l      =    strlen($key);
    //dd($l);
    $char   =    '';
    for ($i = 0; $i < $len; $i++)
    {
        if ($x == $l)
        {
            $x = 0;
        }
        $char .= $key{$x};
        $x++;
    }
    $str='';
    for ($i = 0; $i < $len; $i++)
    {
        $str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1)))%256);
    }
    return urlencode(base64_encode($str));
}
function object_array($array) {
    if(is_object($array)) {
        $array = (array)$array;
    }
    if(is_array($array)) {
        foreach($array as $key=>$value) {
            $array[$key]= object_array($value);
        }
    }
    return $array;
}
function getIP()
{
    static $realip;
    if (isset($_SERVER)){
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
            $realip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $realip = $_SERVER["HTTP_CLIENT_IP"];
        } else {
            $realip = $_SERVER["REMOTE_ADDR"];
        }
    } else {
        if (getenv("HTTP_X_FORWARDED_FOR")){
            $realip = getenv("HTTP_X_FORWARDED_FOR");
        } else if (getenv("HTTP_CLIENT_IP")) {
            $realip = getenv("HTTP_CLIENT_IP");
        } else {
            $realip = getenv("REMOTE_ADDR");
        }
    }
    return $realip;
}

/**
 * 获取 IP  地理位置
 * 淘宝IP接口
 * @Return: array
 */
function getCity($ip = '')
{
    if(!$ip)
    {
        $ip     =   getIP();
    }

    if($ip == ''){
        $url = "http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json";
        $ip=json_decode(file_get_contents($url),true);
        $data = $ip;
    }else{
        $url="http://ip.taobao.com/service/getIpInfo.php?ip=".$ip;
        $ip=json_decode(file_get_contents($url));
        if((string)$ip->code=='1'){
            return false;
        }
        $data = (array)$ip->data;
    }

    return $data;
}
function decrypt($data)
{
    $data   =   urldecode($data);
    $key = config('Asc_Key');
    $key = md5($key);
    $x = 0;
    $data = base64_decode($data);
    $len = strlen($data);
    $l = strlen($key);
    $char = '';
    for ($i = 0; $i < $len; $i++)
    {
        if ($x == $l)
        {
            $x = 0;
        }
        $char .= substr($key, $x, 1);
        $x++;
    }
    $str = '';
        for ($i = 0; $i < $len; $i++)
    {
        if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1)))
        {
            $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
        }
        else
        {
            $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
        }
    }
    return $str;
}

//function authcode($string, $operation = 'ENCODE', $key = '', $expiry = 0)
//{
//    // 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
//    $ckey_length = 4;
//    // 密匙
//    $key = md5($key ? $key : config('auth.code'));
//    // 密匙a会参与加解密
//    $keya = md5(substr($key, 0, 16));
//    // 密匙b会用来做数据完整性验证
//    $keyb = md5(substr($key, 16, 16));
//    // 密匙c用于变化生成的密文
//    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) :
//        substr(md5(microtime()), -$ckey_length)) : '';
//    // 参与运算的密匙
//    $cryptkey = $keya . md5($keya . $keyc);
//    $key_length = strlen($cryptkey);
//    // 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，
//    //解密时会通过这个密匙验证数据完整性
//    // 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
//    $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) :
//        sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
//    $string_length = strlen($string);
//    $result = '';
//    $box = range(0, 255);
//    $rndkey = array();
//    // 产生密匙簿
//    for ($i = 0; $i <= 255; $i++) {
//        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
//    }
//    // 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
//    for ($j = $i = 0; $i < 256; $i++) {
//        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
//        $tmp = $box[$i];
//        $box[$i] = $box[$j];
//        $box[$j] = $tmp;
//    }
//    // 核心加解密部分
//    for ($a = $j = $i = 0; $i < $string_length; $i++) {
//        $a = ($a + 1) % 256;
//        $j = ($j + $box[$a]) % 256;
//        $tmp = $box[$a];
//        $box[$a] = $box[$j];
//        $box[$j] = $tmp;
//        // 从密匙簿得出密匙进行异或，再转成字符
//        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
//    }
//    if ($operation == 'DECODE') {
//        // 验证数据有效性，请看未加密明文的格式
//        if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) &&
//            substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)
//        ) {
//            return substr($result, 26);
//        } else {
//            return '';
//        }
//    } else {
//        // 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
//        // 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
//        return $keyc . str_replace('=', '', base64_encode($result));
//    }
//}
//获取数据库内的配置信息
function _config($var)
{
    return db('config')->where('config_var', $var)->value('config_value');
}
//不可逆加密
function encrypt($data, $key = 'mdzzkey')
{
    return md5(sha1(md5($data)));
}

//短信验证码发送
function sendTemplateSMS($to, $datas, $tempId)
{
    include_once(ROOT_PATH . "ronglianyun/CCPRestSmsSDK.php");
    $accountSid = _config('ronglian_accountSid');
    $accountToken = _config('ronglian_accountToken');
    $appId = _config('ronglian_appId');
    $serverIP = 'app.cloopen.com';
    $serverPort = '8883';
    $softVersion = '2013-12-26';
    $rest = new REST($serverIP, $serverPort, $softVersion);
    $rest->setAccount($accountSid, $accountToken);
    $rest->setAppId($appId);

    $result = $rest->sendTemplateSMS($to, $datas, $tempId);

    if ($result == NULL) {
        return ['code' => false, 'msg' => '验证码发送失败'];
    } else {
        if ($result->statusCode != 0) {
            return ['code' => false, 'msg' => $result->statusMsg . '[' . $result->statusCode . ']'];
        } else {
            $smsmessage = $result->TemplateSMS;
            return ['code' => true, 'msg' => '验证码发送成功', 'smsmessage' => $result->TemplateSMS, 'dateCreated' => $smsmessage->dateCreated, "smsMessageSid" => $smsmessage->smsMessageSid];
        }
    }
}
//redis链接方法
function redis()
{
    $redis = new redis();
    $redis->connect('127.0.0.1', 6379);
    return $redis;
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
    curl_setopt($curl, CURLOPT_REFERER, "http://www.ztwlxx.club");
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

//对象转数组
function ToArray($object) {
    if (is_object($object)) {
        foreach ($object as $key => $value) {
            $array[$key] = $value;
        }
    }
    else {
        $array = $object;
    }
    return $array;
}

//数据通用解密
function decode($data = [])
{
    if (empty($data)) {
        return $data;
    }
    $newData = [];
    $decodeKey =    config('encode_key');
    if (is_array($data)) {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $newData[$k] = decode($v);
            } else {
                if (in_array($k, $decodeKey)) {
                    $decode = decrypt($v);
                    $v = $decode;
                }
                $newData[$k] = $v;
            }
        }
    } else {
        $newData = decrypt($data);
    }
    return $newData;

}

//对象转数组
function simplest_xml_to_array($xmlstring)
{
    $res = @simplexml_load_string($xmlstring, NULL, LIBXML_NOCDATA);
    $res = json_decode(json_encode($res), true);
    return $res;
}


//数据通用加密
function encode($data = [])
{
//    dd($data['area_info'])
    if (empty($data)) {
        return $data;
    }
    $newData = [];
    $encodeKey = config('encode_key');
    if(is_array($data))
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $newData[$k] = encode($v);
            } else {
                if($k!==0)
                {
                    if (in_array($k, $encodeKey)) {

                        $decode = encrypts($v);
                        $v = $decode;
                    }
                }

                $newData[$k] = $v;
            }
        }
    }else{
        return encrypts($data);
    }

    return $newData;
}

//redis 存储
function redis_set($key,$value,$expire=7000)
{
    $redis  =   redis();
    $data   =   true;
    while ($data)
    {
        if($redis->set($key,$value))
        {
            if($expire!='all')
            {
                $redis->expire($key,$expire);
            }
            $data   =   false;

        }
    }
}

//多条删除
function arrdecode($array)
{
    $idarr  =  array_filter(explode(',',$array));

    foreach ($idarr as $key=>$value)
    {
        $idarr[$key]    =   decode($value);
    }
    return $idarr;
}

//接口调用
function CurlApi($uri,  $data = [], $method = 'get', $ApiReturnFormat = 'json')
{

    if (empty($uri)) {
        return FALSE;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $uri);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if ($method == 'post'){
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    $output = curl_exec($ch);
    curl_close($ch);

    if ($ApiReturnFormat == 'json'){
        return json_decode($output,TRUE);
    }

    return $output;

}
//点击门店，信息，福利 存redis
//member_id 用户id $typeid 门店 信息，福利id type info shop writeoff
//哈希存
function redisinfo($member, $typeid,$type){
    $redis  =   redis();
    if(!$redis){
        return false;
    }
    $hx = $type.':'.$typeid;

    while (true) {
        if($redis->hSet($hx,$member,time())){
            $data = true;
        }else{
            $data = false;
        }
    }


    return $data;

}


