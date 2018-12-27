<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/8
 * Time: 下午3:57
 */

namespace app\index\controller;

use think\Cache;
use think\Cookie;
use think\Request;
use think\Session;
use app\wechat\controller\Jsapipay;

class Index extends Common
{
    public $member_id;
    private $appId;
    private $appSecret;
    public static $table_shop = 'shop';
    public static $table_goods = 'goods';
    public static $table_member = 'member';
    public static $table_address = 'address';
    public static $table_deputy = 'deputy';
    public static $table_store = 'store';
    public static $table_slyderadventures = 'slyderadventures';
    public static $table_prize = 'prize';
    public static $table_active_order = 'active_order';
    private  $key;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->member_id = $this->check();
        $this->appId = _config('AppId');
        $this->appSecret = _config('AppSecret');
        $this->key = 'c56d0e9a7ccec67b4ea131655038d604';
    }

    public function index()
    {
        //dd($this->member_id);
        if(!$this->member_id){
            return redirecturl('index');
        }
        $shop_id = input('shop_id',3);
        //获取当前门店信息
        $shop = db(self::$table_shop)->where('shop_id',$shop_id)->find();
        //根据门店id 获取代理id
        $dpeuty_id = $this->deputy_id(3);
        if((int)$dpeuty_id > 0) {
            //获取大转盘
            $dzp = $this->deputy_dzp($dpeuty_id);
            //dd($dzp);
            $dzpprize = [];
            $limit_collar = 0;
            $prizekeys = [];
            $alreadynum = 0;
            if($dzp){
                $isTrue = false;
                //查找活动订单表已参加次数
                $whereactive = [
                    'shop_id'   => $shop_id,
                    'type'      => 1,
                    'type_id'   => $dzp['active_id'],
                    'status'    => 1,
                    'member_id' => $this->member_id,
                ];
                $prize        = $dzp['prize']?json_decode($dzp['prize'],true):'';
                $activeperiod = json_decode($dzp['activeperiod'],true);  //大转盘时间段
                //var_dump($activeperiod);
                foreach($activeperiod as $k=>$v){
                    $aviabletime = explode('-',$v);
                    $nyr = date("Y-m-d",time());
                    $start = strtotime($nyr." ".$aviabletime[0]);
                    $end   = strtotime($nyr." ".$aviabletime[1]);
                    if($start <= time()&&$end >= time()){
                        //dd(111);
                        $whereactive['paytime'] = ['between time',[$start,$end]];
                        $alreadynum = db(self::$table_active_order)->where($whereactive)->count();
                        $isTrue = true;
                    }
                }
                if(!$isTrue){
                    $alreadynum = 0;
                }
                //d($alreadynum);
                $limit_collar = $dzp['limit_collar'];
                //根据大转盘奖项ids获取奖项礼品
                $dzpprize = db(self::$table_prize)
                    ->whereIn('prize_id',$prize)
                    ->where(['sum'=>['>',0]])
                    ->order('probability asc')
                    ->field('cover,prize_id')
                    ->select();
                if(!empty($dzpprize)) {
                    foreach ($dzpprize as $ke=>$v) {
                        $prizekeys[$v['prize_id']] = $ke;
                    }
                }
                //dd($dzpprize);
            }else{
                $dzp = [];
            }
        }
        //展示前五条中奖纪录
        $activeorder = db(self::$table_active_order)
            ->where(['shop_id'=>$shop_id,'status'=>1])
            ->order('paytime desc')
            ->limit(0,5)
            ->select();
        if(!empty($activeorder)){
            foreach($activeorder as $kk=>$vv){
                $activeorder[$kk]['member'] = db(self::$table_member)->where('member_id',$vv['member_id'])
                    ->field('name,cover')->find();
            }
        }
        //dd(json_encode($prizekeys));
        $needpay = 2+0.5*$alreadynum;
        return view('index',[
            'dzp'  => $dzp,
            'dzpprize' => $dzpprize,
            'shop_id' => $shop_id,
            'member_id' => $this->member_id,
            'needpay' => $needpay,
            'fee'     => 2,
            'increfee'=> 0.5,
            'limit_collar' => $limit_collar,
            'prizekeys' => json_encode($prizekeys),
            'alreadynum' => $alreadynum,
            'shop'      => $shop,
            'activeorder' => $activeorder,
        ]);
    }

    //ajax获取前五条中奖纪录

    public function activeorder()
    {
        if(Request::instance()->isAjax()) {
            $shop_id = input('shop_id', 3);
            $activeorder = db(self::$table_active_order)
                ->where(['shop_id' => $shop_id, 'status' => 1])
                ->order('paytime desc')
                ->limit(0, 5)
                ->select();
            if (!empty($activeorder)) {
                foreach ($activeorder as $kk => $vv) {
                    $activeorder[$kk]['member'] = db(self::$table_member)->where('member_id', $vv['member_id'])
                        ->field('name,cover')->find();
                }
                return json(array('code'=>200,'data'=>$activeorder));
            }else{
                return json(array('code'=>400,'data'=>'暂无中奖纪录'));
            }
        }
    }

    //获取大转盘信息
    public function getactive(){
        if(Request::instance()->isAjax())
        {
            $id = input('id');
            $shop_id = input('shop_id');
            $member_id = input('member_id');
            $prizekeys = input('prizekeys');
            $needpay   = floatval(input('needpay'));
            $prizekeys = json_decode($prizekeys);
            $dzp = db(self::$table_slyderadventures)->where('active_id',$id)->find();
            //时间段
            $isTrue = false;
            $activeperiod = json_decode($dzp['activeperiod'],true);
            //var_dump($activeperiod);
            foreach($activeperiod as $k=>$v){
                $aviabletime = explode('-',$v);
                $nyr = date("Y-m-d",time());
                $start = strtotime($nyr." ".$aviabletime[0]);
                $end   = strtotime($nyr." ".$aviabletime[1]);
                if($start <= time()&&$end >= time()){
                    $isTrue = true;
                }
            }
            //每人/时间段抽奖次数
            $limit_collar = $dzp['limit_collar'];
            if($isTrue === false){
                return json(array('code'=>400,'msg'=>'当前时间不在抽奖时间段内，请查看活动公告'));
            }else{
                $returnk = '';  //第几个位置中奖
                //抽奖概率计算
                $prizeids = json_decode($dzp['prize']);
                //dd($prizeids);
                $return = $this->probability($prizeids);
                foreach($prizekeys as $k1=>$v1){
                    if($k1 == $return['prize_id']){
                        $returnk = $v1;
                        break;
                    }
                }
                $return['returnk'] = $returnk;
                $return['appId'] = $this->appId;
                $return['nonceStr'] = $this->createNoncestr();
                $return['timeStamp'] = time();

                //写入订单
                $shop = db(self::$table_shop)->where('shop_id',$shop_id)->field('shop_name,kefu_phone')->find();
                $insert = [
                    'shop_id'    => $shop_id,
                    'shop_name'  => $shop['shop_name'],
                    'kefu_phone' => $shop['kefu_phone'],
                    'type'       => 1,
                    'type_id'    => $id,
                    'prize_id'   => $return['prize_id'],
                    'prize_name' => $return['prize_name'],
                    'dcode'      => $return['dcode'],
                    'member_id'  => $member_id,
                    'status'     => 0,
                    'order_num'  => $shop_id.time().rand(100000,999999),
                    'created_at' => time(),
                    'needpay'    => $needpay,
                ];
                //写入订单列表
                $id = db(self::$table_active_order)->insertGetId($insert);
                if($id){
                    $return['id'] = $id;
                    //获取h5调起微信支付
                    $result = $this->jsapipay($insert,$id);
                    if($result['return_code']=='SUCCESS'&&$result['result_code']=='SUCCESS'){
                        $return['package'] = "prepay_id=".$result['prepay_id'];
                        $return['signType'] = 'MD5';
                        //重新进行签名
                        $sign = $this->createsign($return['timeStamp'],$return['nonceStr'],$return['package'],$return['signType']);
                        $return['paySign'] = $sign;
                        return json(array('code'=>200,'msg'=>json_encode($return)));
                    }else{
                        return json(array('code'=>400,'msg'=>$result['return_msg']));
                    }
                }else{
                    return json(array('code'=>400,'msg'=>'操作失败'));
                }
            }
        }
    }

    //不支付就删除订单
    public function delactiveorder()
    {
        if(Request::instance()->isAjax()){
            $order_id = input('order_id');
            $where = [
                'order_id'  => $order_id,
                'status'    => 0,
            ];
            db(self::$table_active_order)->where($where)->delete();
        }
    }
    //重新进行签名

    private function createsign($timeStamp,$nonceStr,$package,$signType)
    {
        //请求参数 appId、timeStamp、nonceStr、package、signType
        $params = [
            'appId'         => $this->appId,  //公众账号ID
            'timeStamp'     => $timeStamp, //时间戳
            'nonceStr'      => $nonceStr, //生成随机字符串
            'package'       => $package, //商品描述
            'signType'      => $signType,
        ];
        $sign = $this->getSign($params);
        return $sign;
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

    //获取jsapi微信支付

    public function jsapipay($insert,$id)
    {
        $notify_url = 'http://www.yilingjiu.cn/wechat/Jsapipay/notify';
        $openid = db(self::$table_member)->where('member_id',$insert['member_id'])->value('openid');//'os-5N1ZgTUrkGgasKpmQHpFc5R5E';
        $attach = $id.':'.$insert['prize_id'];
        $pay = new Jsapipay('',$openid, $mch_id='1514213421', $key='c56d0e9a7ccec67b4ea131655038d604',$insert['order_num'],'大转盘活动',$total_fee=0.01,$attach,$notify_url,'JSAPI');
        $return = $pay->pay();
        return $return;
    }

    private function probability($ids)
    {
        //根据ID获取礼品
        $where = [
            'prize_id'  => ['in',$ids],
            'sum'       => ['>',0],
        ];
        //dd($where);
        $arr = $keys = [];
        $prize_arr = db(self::$table_prize)
            ->where($where)
            ->field('prize_id,name,probability')
            ->select();
        //dd($prize_arr);
        foreach ($prize_arr as $key => $val) {
            $keys[$key] = $val['prize_id'];
            $arr[$val['prize_id']] = $val['probability']*10000;
        }
        //dd($keys);
        //根据概率获取奖品id
        $prize_id = $this->getRand($arr);
        $index = '';
        foreach ($keys as $k=>$v){
            if($prize_id == $v){
                $index = $k;
                break;
            }
        }
        $data['prize_id'] = $prize_id;
        $data['prize_name'] = $prize_arr[$index]['name']; //中奖奖品
        //随机兑奖码
        $data['dcode'] = $this->createNoncestr();
        return $data;

    }
    //全概率
    private function getRand($proArr)
    {
        $rs = ''; //z中奖结果
        $proSum = array_sum($proArr); //概率数组的总概率精度
        //概率数组循环
        foreach ($proArr as $key => $proCur) {
            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur) {
                $rs = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }
        unset($proArr);
        return $rs;
    }
    private function deputy_id($shopid)
    {
        $dpeuty_id = db(self::$table_shop)->where('shop_id',$shopid)->value('deputy_id');
        return $dpeuty_id;
    }
    private function deputy_dzp($deputyid)
    {
        $where = [
            'deputy_id' => $deputyid,
            'status'    => 1,
            'type'      => 1,
        ];
        $dzpid = db(self::$table_deputy)->where($where)->value('type_id');
        if((int)$dzpid>0){
            $dzp = db(self::$table_slyderadventures)->where('active_id',$dzpid)->find();
            return $dzp;
        }else{
            //代理不存在转盘则从平台随机选一个
            $dzps = db(self::$table_slyderadventures)->find();
            if(!empty($dzps)){
                return $dzps;
            }else{
                return false;
            }

        }

    }

    public function more()
    {
        if(!$this->member_id){
            //获取签名信息

            return redirecturl('more');
        }
        //dd(222);
        $getSignPackage = json_decode($this->getSignPackage(),true);
        return view('more',[
            'signPackage' => $getSignPackage,
        ]);
    }
    public function address()
    {
        if(!$this->member_id){
            return redirecturl('address');
        }
        //$app_id = db(self::$table_member)->where('member_id',$this->member_id)->value('app_id');
        $where = [
            //'app_id'    => $app_id,
            'member_id' => $this->member_id,
            'address_id'=> input('address_id'),
        ];
        $flag = input('flag');
        if(Request::instance()->isPost()){
            $insert = [
                'name'      => input('name'),
                'phone'     => input('phone'),
                'position'  => input('position'),
                'address'   => input('address'),
                'updated_at'=> time(),
            ];
            if($flag == 'add'){
                //$insert['app_id']    = $app_id;
                $insert['member_id'] = $this->member_id;
                $insert['isdefault'] = 0;
                $insert['status']    = 1;
                $insert['created_at']= time();
                $id = db(self::$table_address)->insertGetId($insert);
                if($id){
                    return $this->redirect('index/address');
                }else{
                   return $this->error('操作失败');
                }
            }else{
                $res = db(self::$table_address)->where($where)->update($insert);
                if($res !== false){
                    return $this->redirect('index/address');
                }else{
                    return $this->error('操作失败');
                }
            }
        }else{
            if($flag == 'add'){
                return view('addaddress');
            }else if($flag == 'update'){
                $data = db(self::$table_address)
                    ->where($where)
                    ->find();
                return view('updateaddress',[
                    'data' => $data,
                ]);
            }else{
                $address = db(self::$table_address)
                    ->where(['member_id'=>$this->member_id])
                    ->order('created_at desc')
                    ->select();
                return view('address',[
                    'data' => $address,
                ]);
            }
        }
    }

    public function business()
    {
        if(!$this->member_id){
            return redirecturl('business');
        }
        return view('business');
    }
    public function cardDetails()
    {
        if(!$this->member_id){
            return redirecturl('cardDetails');
        }
        return view('cardDetails');
    }

    public function detail()
    {
        if(!$this->member_id){
            return redirecturl('detail');
        }
        return view('detail');
    }
    public function discountTabs()
    {
        if(!$this->member_id){
            return redirecturl('discountTabs');
        }
        return view('discountTabs');
    }

    public function exchange()
    {
        if(!$this->member_id){
            return redirecturl('exchange');
        }
        return view('exchange');
    }
    public function exchangeShop()
    {
        if(!$this->member_id){
            return redirecturl('exchangeShop');
        }
        $data = db(self::$table_goods)->where(['status'=>0])->select();
        return view('exchangeShop',[
            'data' => $data,
        ]);
    }

    public function joinIn()
    {
        if(!$this->member_id){
            return redirecturl('joinIn');
        }
        return view('joinIn');
    }
    public function my()
    {
        if(!$this->member_id){
            return redirecturl('my');
        }
        return view('my');
    }

    public function orderPay()
    {
        if(!$this->member_id){
            return redirecturl('orderPay');
        }
        return view('orderPay');
    }
    public function payment()
    {
        if(!$this->member_id){
            return redirecturl('payment');
        }
        return view('payment');
    }

    public function paythebill()
    {
        if(!$this->member_id){
            return redirecturl('paythebill');
        }
        return view('paythebill');
    }
    public function receiving()
    {
        if(!$this->member_id){
            return redirecturl('receiving');
        }
        return view('receiving');
    }

    public function sharelt()
    {
        if(!$this->member_id){
            return redirecturl('sharelt');
        }
        return view('sharelt');
    }

    public function shopDetail()
    {
        if(!$this->member_id){
            return redirecturl('sharelt');
        }
        //$data = db(self::$table_goods)->where(['goods_id'=>input('goods_id')])->find();
        return view('shopDetail');
    }

    public function shopDetailList()
    {
        if(!$this->member_id){
            return redirecturl('shopDetailList');
        }
        return view('shopDetailList');
    }
    public function shopIndex()
    {
        if(!$this->member_id){
            return redirecturl('shopIndex');
        }
        $data = db(self::$table_goods)->where(['status'=>0])->select();
        return view('shopIndex',[
            'data' => $data,
        ]);
    }

    public function shopList()
    {
        if(!$this->member_id){
            return redirecturl('shopList');
        }
        return view('shopList');
    }
    public function tabs()
    {
        if(!$this->member_id){
            return redirecturl('tabs');
        }
        return view('tabs');
    }

    public function zipCode()
    {
        if(!$this->member_id){
            return redirecturl('zipCode');
        }
        return view('zipCode');
    }

    //获取公众平台的access_token
    public function get_access_token()
    {
        //dd(222);
        //判断缓存是否过期
        if(!Cache::get('accesstoken')) {
            //dd(Cache::get('access_token'));
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appId}&secret={$this->appSecret}";
            $data = json_decode(curl_request($url),true);  //强制转换为数组
            if (!array_key_exists('errcode',$data)) {
                Cache::set('accesstoken', $data['access_token'], 7000); //存入缓存
                return $data['access_token'];
            } else {
                return $this->error($data['errmsg']);
            }
        }else{
            return Cache::get('accesstoken');
        }
    }

    //获取jsapi_ticket
    public function get_ticket()
    {
        //判断缓存是否过期
        if(!Cache::get('ticket')) {
            //dd(111);
            $access_token = $this->get_access_token();
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$access_token}&type=jsapi";
            $data = json_decode(curl_request($url),true);  //强制转换为数组
            if ($data['errcode'] == 0) {
                Cache::set('ticket', $data['ticket'], 7000); //存入缓存
                return $data['ticket'];
            } else {
                return $this->error($data['errmsg']);
            }
        }else{
            //dd(22222);
            return Cache::get('ticket');
        }
    }

    //生成随机数
    private function createNoncestr($length = 32) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }


    //作用：生成签名
    public function getSignPackage() {
        $jsapiTicket = $this->get_ticket();

        // 注意 URL 一定要动态获取，不能 hardcode.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        $timestamp = time();
        $nonceStr = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

        $signature = sha1($string);

        $signPackage = array(
            "appId"     => $this->appId,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "signature" => $signature,
        );
        return json_encode($signPackage);
    }

    //ajax获取门店距离排序

    public function getshop()
    {
        if(Request::instance()->isAjax()){
            $lng = input('lng');
            $lat = input('lat');
            //dd($lng);

            $shop = db(self::$table_shop)->where(['status'=>1])->select();
            if(!empty($shop)){
                foreach($shop as $k=>$v){
                    //获取距离
                    $shop[$k]['distance'] = getdistance($lng,$lat,$v['longitude'],$v['latitude']);
                }
                //根据距离排序
                $shop = arr_sort($shop,'distance',$order="asc");
                return json_encode(['code'=>200,'data'=>$shop]);
            }else{
                return json_encode(['code'=>400,'data'=>'暂无数据']);
            }

        }
    }

    public function douhao()
    {
       dd(douhao());
    }

    //删除地轴

    public function deladdress()
    {
        //dd(222);
        if(Request::instance()->isAjax()) {
            $where = [
                //'app_id'    => $this->id,
                'address_id'  => input('address_id'),
            ];
            $res = db(self::$table_address)->where($where)->delete();
            if ($res) {
                return json(['code'=>200,'msg'=>'操作成功']);
            } else {
                return json(['code'=>400,'msg'=>'操作失败']);
            }
        }
    }

    //定位代理

    public function location()
    {
        if(!$this->member_id){
            //获取签名信息

            return redirecturl('location');
        }
        //dd(222);
        $getSignPackage = json_decode($this->getSignPackage(),true);
        $where = [
            'status'    => 1,
        ];
        $deputy = db(self::$table_deputy)
            ->where($where)
            ->field('deputy_name,deputy_id,parentid,level')
            ->select();
        return view('location',[
            'deputy' => $deputy,
            'signPackage' => $getSignPackage,
        ]);
    }

    //ajax获取门店距离排序

    public function getdeputy()
    {
        if(Request::instance()->isAjax()){
            $lng = input('lng');
            $lat = input('lat');
            //获取省市区信息
            $return = byLtGetCity($lng,$lat);
            $province = $return[0]; //省
            $city = $return[1]; //市
            $county = $return[2];  //区
            //根据省市区反查代理
            $deputy_id = db(self::$table_deputy)
                ->where(['province'=>$province,'city'=>$city,'county'=>$county])
                ->value('deputy_id');
            if(!$deputy_id){
                $deputy_id = db(self::$table_deputy)
                    ->where(['province'=>$province,'city'=>$city,'county'=>''])
                    ->value('deputy_id');
                if(!$deputy_id){
                    return json(['code'=>400,'data'=>'暂无数据']);
                }
            }
            //根据代理获取商户根据商户获取所有门店
            $storeids = db(self::$table_store)
                ->where(['deputy_id'=>$deputy_id,'status'=>1])
                ->column('store_id');
            if(empty($storeids)){
                return json(['code'=>400,'data'=>'暂无数据']);
            }
            $shop = db(self::$table_shop)->where(['status'=>1])->whereIn('store_id',$storeids)->select();
            if(!empty($shop)){
                foreach($shop as $k=>$v){
                    //获取距离
                    $shop[$k]['distance'] = getdistance($lng,$lat,$v['longitude'],$v['latitude']);
                }
                //根据距离排序
                $shop = arr_sort($shop,'distance',$order="asc");
                return json(['code'=>200,'data'=>$shop,'deputy_id'=>$deputy_id]);
            }else{
                return json(['code'=>400,'data'=>'暂无数据']);
            }

        }
    }


    public function test()
    {
        //假设抽奖时间段为 05:00:00--07:00,08:00:00--10:00:00 我在六点抽了俩次
        $choutime = date("Y-m-d",time())." 06:00:00";
        $nowtime  = time();
        //判断当前时间 在哪个时间段  //然后判断抽奖时间是否在这个时间段 如果在则证明次数有效 否则次数清零

        //return view('test');
    }
}




