<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/12/11
 * Time: 11:34 AM
 */

namespace app\wechat\controller;
use think\Controller;
use think\Db;
use think\Exception;
use think\Request;

class Jsapipay extends Controller
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
    protected $trade_type;

    public static $table_goushui_order = 'goushui_order';
    public static $table_goushui = 'goushui';
    public static $table_shui = 'shui';
    public static $table_deputy = 'deputy';
    public static $table_integral_order = 'integral_order';
    public static $table_store = 'store';
    public static $table_active_order = 'active_order';
    public static $table_prize = 'prize';
    public static $table_shop  = 'shop';
    public static $table_vipcard = 'vipcard';
    public static $table_vipcard_order = 'vipcard_order';
    public static $table_recievr_vipcard = 'recieve_vipcard';
    public static $table_store_member = 'store_member';
    //初始化参数
    public function __construct($product_id='',$openid='', $mch_id='', $key='',$out_trade_no='',$body='',$total_fee=0.01,$attach='',$notify_url='',$trade_type='JSAPI')
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
        $this->trade_type   = $trade_type;
    }

    //供外部调用的微信支付接口

    public function pay(){
        //
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
            'nonce_str'     => self::getNonceStr(), //生成随机字符串
            'body'          => $this->body, //商品描述
            'attach'        => $this->attach,
            'out_trade_no'  => $this->out_trade_no, //商户订单号
            'total_fee'     => floatval($this->total_fee*100), //订单总金额，单位为分
            'spbill_create_ip' => Request::instance()->ip(), //终端IP
            'notify_url'    => $this->notify_url,  //通知地址
            'trade_type'    => $this->trade_type, //saoma
            'openid'        => $this->openid,
        ];
        //dd($params);
        //获取签名
        $params['sign']  =  $this->getSign($params);
        //转换成xml
        $xmlData = $this->arrayToXml($params);
        //dd($xmlData);
        $return = $this->xmlToArray($this->postXmlCurl($xmlData, $url, 60));
        //dd($return);
        return $return;
    }

    //支付回调

    /**
     * <xml><appid><![CDATA[wx762bbeb8757c18b7]]></appid>
    <attach><![CDATA[14]]></attach>
    <bank_type><![CDATA[CFT]]></bank_type>
    <cash_fee><![CDATA[1]]></cash_fee>
    <device_info><![CDATA[WEB]]></device_info>
    <fee_type><![CDATA[CNY]]></fee_type>
    <is_subscribe><![CDATA[Y]]></is_subscribe>
    <mch_id><![CDATA[1514213421]]></mch_id>
    <nonce_str><![CDATA[qnvfxzdgr052tn7uj1kk8mwe43soyiw7]]></nonce_str>
    <openid><![CDATA[os-5N1ZgTUrkGgasKpmQHpFc5R5E]]></openid>
    <out_trade_no><![CDATA[1544580925314777]]></out_trade_no>
    <result_code><![CDATA[SUCCESS]]></result_code>
    <return_code><![CDATA[SUCCESS]]></return_code>
    <sign><![CDATA[80DEF682D282A82F9F13E0C300947C41]]></sign>
    <time_end><![CDATA[20181212101542]]></time_end>
    <total_fee>1</total_fee>
    <trade_type><![CDATA[NATIVE]]></trade_type>
    <transaction_id><![CDATA[4200000230201812121985432559]]></transaction_id>
    </xml>
     */

    public function notify()
    {
        $xml = file_get_contents("php://input");
        //转换成数组
        $data = $this->xmlToArray($xml);
        $attach = explode(':',$data['attach']);
        $order_id = $attach[0];
        $prize_id = $attach[1];
        db('ceshi')->insertGetId(array('text1'=>json_encode($data),'text2'=>4444));
        //db('ceshi')->insertGetId(array('text1'=>$attach,'text2'=>5555));
        $mch_key = db('pay_setting')->where('app_id',1)->value('mch_key');
        $this->key = $mch_key;
        //比较签名
        $data_sign = $data['sign'];
        unset($data['sign']);
        $sign=$this->getSign($data);
        if ( ($sign===$data_sign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS') ){
            //查找订单
            $order = db(self::$table_active_order)->where(['order_id'=>$order_id,'status'=>0])->find();
            if($order){
                Db::startTrans();
                try{
                    //更改状态 减少库存 添加goushui表
                    db(self::$table_active_order)->where(['order_id'=>$order_id,'status'=>0])->update(['status'=>1,'paytime'=>time()]);
                    //减少上级代理水量
                    db(self::$table_prize)
                        ->where(['prize_id' => $prize_id])
                        ->setDec('sum', 1);
                    //给商户表增添金额
                    $store_id = db(self::$table_shop)->where('shop_id',$order['shop_id'])->value('store_id');
                    //db('ceshi')->insertGetId(array('text1'=>$store_id,'text2'=>'store_id'));
                    db(self::$table_store)->where('store_id',$store_id)->setInc('totalmoney',$order['needpay']);
                    db(self::$table_store)->where('store_id',$store_id)->setInc('money',$order['needpay']);
                        Db::commit();
                        $result = true;
                }catch (Exception $exception){
                    Db::rollback();
                    $result = false;
                }
            }else{
                $result = false;
            }
        }else{
            $result = false;
        }
        // 返回状态给微信服务器
        if ($result) {
            $str='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }else{
            $str='<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        return $result;
    }

    public function notify_card()
    {
        $xml = file_get_contents("php://input");
        //转换成数组
        $data = $this->xmlToArray($xml);
        $attach = $data['attach'];
        //db('ceshi')->insertGetId(array('text1'=>json_encode($data),'text2'=>'card'));
        db('ceshi')->insertGetId(array('text1'=>$attach,'text2'=>'ssaaa'));
        $mch_key = db('pay_setting')->where('app_id',1)->value('mch_key');
        $this->key = $mch_key;
        //比较签名
        $data_sign = $data['sign'];
        unset($data['sign']);
        $sign=$this->getSign($data);
        if ( ($sign===$data_sign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS') ){
            //查找订单
            $order = db(self::$table_vipcard_order)->where(['order_id'=>$attach,'status'=>0])->find();
            db('ceshi')->insertGetId(array('text1'=>333,'text2'=>'s2222'));
            if($order){
                Db::startTrans();
                try{
                    //更改状态
                    db(self::$table_vipcard_order)->where(['order_id'=>$attach,'status'=>0])->update(['status'=>1,'paytime'=>time()]);
                    db('ceshi')->insertGetId(array('text1'=>1111,'text2'=>'s2222'));
                    //添加我的会员卡
                    $validity = db(self::$table_vipcard)
                        ->where(['card_id' => $order['card_id']])
                        ->value('validity');
                    db('ceshi')->insertGetId(array('text1'=>2222,'text2'=>'s2222'));
                    $insert['app_id']   = $order['app_id'];
                    $insert['store_id'] = $order['store_id'];
                    $insert['member_id']= $order['member_id'];
                    $insert['card_id']  = $order['card_id'];
                    $insert['money']    = floatval($order['needpay']);
                    if ($validity == 0) {
                        $insert['endtime'] = null;
                    } else {
                        $validitytime = $validity * 24 * 3600;
                        $insert['endtime'] = time() + $validitytime;
                    }
                    $insert['created_at'] = time();
                    db(self::$table_recievr_vipcard)->insertGetId($insert);
                    db('ceshi')->insertGetId(array('text1'=>'recieve','text2'=>'s5555'));
                    //更改商户会员钱或者添加
                    $inserts = [
                        'app_id'        => $order['app_id'],
                        'store_id'      => $order['store_id'],
                        'member_id'     => $order['member_id'],
                        'totalmoney'    => floatval($order['needpay']),
                        'yue'           => floatval($order['needpay']),
                        'created_at'    => time(),
                        'updated_at'    => time(),
                        'status'        => 1,
                    ];
                    $wheres = [
                        'app_id'        => $order['app_id'],
                        'store_id'      => $order['store_id'],
                        'member_id'     => $order['member_id'],
                    ];
                    $len = db(self::$table_store_member)->where($wheres)->count();
                    if($len >0){
                        db(self::$table_store_member)->where($wheres)->setInc('totalmoney',$inserts['totalmoney']);
                        db(self::$table_store_member)->where($wheres)->setInc('yue',$inserts['yue']);
                    }else{
                        db(self::$table_store_member)->insertGetId($inserts);
                    }
                    db('ceshi')->insertGetId(array('text1'=>'recieve222','text2'=>'s55533333'));
                    //给商户表增添金额
                    db(self::$table_store)->where('store_id',$order['store_id'])->setInc('totalmoney',$order['needpay']);
                    db(self::$table_store)->where('store_id',$order['store_id'])->setInc('money',$order['needpay']);
                    db('ceshi')->insertGetId(array('text1'=>'2222222','text2'=>'store_id'));
                    Db::commit();
                    $result = true;
                }catch (Exception $exception){
                    Db::rollback();
                    $result = false;
                }
            }else{
                $result = false;
            }
        }else{
            $result = false;
        }
        // 返回状态给微信服务器
        if ($result) {
            $str='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }else{
            $str='<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        return $result;
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