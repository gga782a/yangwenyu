<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/12/11
 * Time: 11:34 AM
 */

namespace app\wechat\controller;
use think\Controller;
use think\Request;

class Saomapay extends Controller
{
    public $appId;
    public $appSecret;
    public $config;
    protected $mch_id;
    protected $key;
    protected $openid;
    protected $out_trade_no;
    protected $body;
    protected $total_fee;
    protected $attach;
    protected $notify_url;
    protected $product_id;
    //初始化参数
    public function __construct($product_id,$openid='', $mch_id, $key,$out_trade_no,$body,$total_fee,$attach,$notify_url='')
    {
        //构成微信支付所需的参数
        $this->appId        = _config('AppID');
        $this->appSecret    = _config('AppSecret');
        $this->openid       = $openid;
        $this->mch_id       = $mch_id;
        $this->key          = $key;
        $this->out_trade_no = $out_trade_no;
        $this->body         = $body;
        $this->total_fee    = $total_fee;
        $this->attach       = $attach;
        $this->notify_url   = $notify_url;
        $this->product_id   = $product_id;
    }

    //供外部调用的微信支付接口

    public function pay(){
       return $this->unifiedOrder();
    }

    //统一下单接口

    private function unifiedOrder()
    {
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        //请求参数
        $params = [
            'appid'         => $this->appId,  //公众账号ID
            'mch_id'        => $this->mch_id, //商户号
            'device_info'   => 'WEB', //PC网页或公众号内支付可以传"WEB"
            'nonce_str'     => self::getNonceStr(), //生成随机字符串
            'body'          => $this->body, //商品描述
            'attach'        => $this->attach,
            'out_trade_no'  => $this->out_trade_no, //商户订单号
            'total_fee'     => floatval($this->total_fee*100), //订单总金额，单位为分
            'spbill_create_ip' => Request::instance()->ip(), //终端IP
            'notify_url'    => $this->notify_url,  //通知地址
            'trade_type'    => 'NATIVE', //saoma
            'product_id'    => $this->product_id, //trade_type=NATIVE时，此参数必传
        ];
        //获取签名
        $params['sign']  =  $this->getSign($params);
        //转换成xml
        $xmlData = $this->arrayToXml($params);
        //dd($xmlData);
        $return = $this->xmlToArray($this->postXmlCurl($xmlData, $url, 60));
        //dd($return);
        return $return;
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ )  {
            $str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }

    //作用：生成签名
    private function getSign($Obj) {
        foreach ($Obj as $k => $v) {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEY
        $String = $String . "&key=" . $this->key;
        //签名步骤三：MD5加密
        $String = md5($String);
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        return $result_;
    }


    ///作用：格式化参数，签名过程需要使用
    private function formatBizQueryParaMap($paraMap, $urlencode) {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar = '';
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }

    //数组转换成xml
    private function arrayToXml($arr) {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $xml .= "<" . $key . ">" . arrayToXml($val) . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }


    //xml转换成数组
    private function xmlToArray($xml) {


        //禁止引用外部xml实体


        libxml_disable_entity_loader(true);


        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);


        $val = json_decode(json_encode($xmlstring), true);


        return $val;
    }

    private static function postXmlCurl($xml, $url, $second = 30)
    {
        $ch = curl_init();
        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); //严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);


        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        set_time_limit(0);


        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            return "curl出错，错误码:".$error."";
//            throw new WxPayException("curl出错，错误码:$error");
        }
    }
}