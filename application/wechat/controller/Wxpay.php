<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/12/11
 * Time: 11:36 AM
 */

namespace app\wechat\controller;


use think\Controller;
use think\Request;

class Wxpay extends Controller
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
            'trade_type'    => 'Native', //saoma
            'product_id'    => $this->product_id, //trade_type=NATIVE时，此参数必传
        ];
        //获取签名
        $params['sign']  =  $this->getSign($params);
        //转换成xml
        $xmlData = $this->arrayToXml($params);
        $return = $this->xmlToArray($this->postXmlCurl($xmlData, $url, 60));
        dd($return);
        return $return;
    }

//    /**
//     *
//     * 统一下单，WxPayUnifiedOrder中out_trade_no、body、total_fee、trade_type必填
//     * appid、mchid、spbill_create_ip、nonce_str不需要填入
//     * @param WxPayConfigInterface $config  配置对象
//     * @param WxPayUnifiedOrder $inputObj
//     * @param int $timeOut
//     * @throws WxPayException
//     * @return 成功时返回，其他抛异常
//     */
//    public static function unifiedOrder($config, $inputObj, $timeOut = 6)
//    {
//        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
//        //检测必填参数
//        if(!$inputObj->IsOut_trade_noSet()) {
//            throw new WxPayException("缺少统一支付接口必填参数out_trade_no！");
//        }else if(!$inputObj->IsBodySet()){
//            throw new WxPayException("缺少统一支付接口必填参数body！");
//        }else if(!$inputObj->IsTotal_feeSet()) {
//            throw new WxPayException("缺少统一支付接口必填参数total_fee！");
//        }else if(!$inputObj->IsTrade_typeSet()) {
//            throw new WxPayException("缺少统一支付接口必填参数trade_type！");
//        }
//
//        //关联参数
//        if($inputObj->GetTrade_type() == "JSAPI" && !$inputObj->IsOpenidSet()){
//            throw new WxPayException("统一支付接口中，缺少必填参数openid！trade_type为JSAPI时，openid为必填参数！");
//        }
//        if($inputObj->GetTrade_type() == "NATIVE" && !$inputObj->IsProduct_idSet()){
//            throw new WxPayException("统一支付接口中，缺少必填参数product_id！trade_type为JSAPI时，product_id为必填参数！");
//        }
//
//        //异步通知url未设置，则使用配置文件中的url
//        if(!$inputObj->IsNotify_urlSet() && $config->GetNotifyUrl() != ""){
//            $inputObj->SetNotify_url($config->GetNotifyUrl());//异步通知url
//        }
//
//        $inputObj->SetAppid($config->GetAppId());//公众账号ID
//        $inputObj->SetMch_id($config->GetMerchantId());//商户号
//        $inputObj->SetSpbill_create_ip($_SERVER['REMOTE_ADDR']);//终端ip
//        $inputObj->SetNonce_str(self::getNonceStr());//随机字符串
//
//        //签名
//        $inputObj->SetSign($config);
//        $xml = $inputObj->ToXml();
//
//        $startTimeStamp = self::getMillisecond();//请求开始时间
//        $response = self::postXmlCurl($config, $xml, $url, false, $timeOut);
//        $result = WxPayResults::Init($config, $response);
//        self::reportCostTime($config, $url, $startTimeStamp, $result);//上报请求花费时间
//
//        return $result;
//    }
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

}