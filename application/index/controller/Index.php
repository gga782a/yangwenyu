<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/8
 * Time: 下午3:57
 */

namespace app\index\controller;

use app\Qiniu;
use think\Cache;
use think\Cookie;
use think\Db;
use think\Exception;
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
    public static $table_goods_type = 'goods_type';
    public static $table_goods_order = 'goods_order';
    public static $table_member = 'member';
    public static $table_address = 'address';
    public static $table_deputy = 'deputy';
    public static $table_store = 'store';
    public static $table_slyderadventures = 'slyderadventures';
    public static $table_prize = 'prize';
    public static $table_active_order = 'active_order';
    public static $table_vipcard = 'vipcard';
    public static $table_recieve_vipcard = 'recieve_vipcard';
    public static $table_vipcard_order = 'vipcard_order';
    public static $table_storedcard = 'storedcard';
    //商户会员列表
    public static $table_store_member = 'store_member';
    public static $table_express_templete = 'express_templete';
    public static $table_active = 'active';
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
        $data['dcode'] = $prize_id.date("Ymd").$this->createNoncestr(6).rand(10000,99999);
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
        $shopid = input('shop_id');
        //dd($shopid);
        //dd(222);
        $getSignPackage = json_decode($this->getSignPackage(),true);
        return view('more',[
            'signPackage' => $getSignPackage,
            'shop_id'     => $shopid,
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
        $url  = input('url');
        $orderid = input('order_id');
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
                    return $this->redirect('index/address',['url'=>$url,'order_id'=>$orderid]);
                }else{
                   return $this->error('操作失败');
                }
            }else{
                $res = db(self::$table_address)->where($where)->update($insert);
                if($res !== false){
                    return $this->redirect('index/address',['url'=>$url,'order_id'=>$orderid]);
                }else{
                    return $this->error('操作失败');
                }
            }
        }else{
            if($flag == 'add'){
                return view('addaddress',[
                    'url' => $url,
                    'orderid'=>$orderid
                ]);
            }else if($flag == 'update'){
                $data = db(self::$table_address)
                    ->where($where)
                    ->find();
                return view('updateaddress',[
                    'data' => $data,
                    'url'  => $url,
                    'orderid'=>$orderid
                ]);
            }else{
                $address = db(self::$table_address)
                    ->where(['member_id'=>$this->member_id])
                    ->order('created_at desc')
                    ->select();
                return view('address',[
                    'data' => $address,
                    'url'  => $url,
                    'orderid'=>$orderid
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
        $getSignPackage = json_decode($this->getSignPackage(),true);
        //获取储值商家
        $storedshops = $this->arrytotwo($this->czshop());
        //多维数组变二维
        //dd($storedshops);
        return view('discountTabs',[
            'signPackage'=> $getSignPackage,
            'storedshops'=> $storedshops,
        ]);
    }

    //三维数组变二维

    private function arrytotwo($arr)
    {
        $newarr = [];
        foreach($arr as $k=>$v){
            foreach($v as $kk=>$vv){
                $newarr[] = $vv;
            }
        }
        return $newarr;
    }

    //获取储值商家

    private function czshop()
    {
        //获取所有储值商户
        $storedids  = $this->getstoredids();
        $arr = [];
        if(!empty($storedids)){
            foreach($storedids as $k=>$v){
                if($v['applyshop'] == 0){ //全门店通用
                    $applyshop = $this->getshopids($v['store_id']);
                }else{
                    $applyshop = explode(',',$v['applyshop']);
                }
                //dd($applyshop);
                //获取所有储值商家
                $shop = $this->getshops($applyshop);
                //dd($shop);
                if($shop !== false){
                    foreach ($shop as $kk=>$vv){
                        $shop[$kk]['card_id'] = $v['card_id'];
                    }
                    $arr[] = $shop;
                }
            }
        }
        return $arr;
    }

    //获取所有储值商户

    private function getstoredids()
    {
        $where = [
            'status' => 1,
        ];

        $storedids = db(self::$table_storedcard)->where($where)->field('store_id,card_id,applyshop')->select();
        //dd($storedids);
        return $storedids;
    }

    //获取所有储值商家

    private function getshops($ids)
    {
        $where = [
            'shop_id'   => ['in',$ids],
            'status'     => 1,
        ];

        $shops = db(self::$table_shop)->where($where)->field('shop_id,shop_name,pic_arr,position,kefu_phone')->select();
        $pic = '';
        if(!empty($shops)){
            foreach($shops as $k=>$shop){
                if(!empty($shop['pic_arr'])){
                    $pic = trim($shop['pic_arr'],',');
                    $pic = explode(',',$pic);
                    $pic = $pic[0];
                }
                $shops[$k]['pic'] = $pic;
            }
            return $shops;
        }else{
            return false;
        }

    }

    //商家优惠活动

    public function activelist()
    {
        $where = [
            'store_id'  => input('store_id'),
            'status'    => 1,
        ];

        $active = db(self::$table_active)->where($where)->select();
        $data = [];
        if(!empty($active)){
            foreach ($active as $k=>$v) {
                if($v['applyshop'] == 0){
                    $data[] = $v;
                }else{
                    $applyshop = explode(',',trim($v['applyshop'],','));
                    if(in_array(input('shop_id'),$applyshop)){
                        $data[] = $v;
                    }else{
                        continue;
                    }
                }
            }
        }
        return view('activelist',[
            'data' => $data,
        ]);
    }

    //获取优惠活动

    public function getyhactive()
    {
        if(Request::instance()->isAjax()){
            $lng = input('lng');
            $lag = input('lag');
            //获取所有代理
            $deputyids = $this->getdeputyids();
            //获取所有商户
            $storeids  = $this->getstoreids($deputyids);
            //获取所有优惠活动
            $active = $this->getallactive($storeids,$lng,$lag);
            if(!empty($active)){
                return json(array('code'=>200,'msg'=>$active));
            }else{
                return json(array('code'=>400,'msg'=>'暂无数据'));
            }
        }
    }
    //获取所有代理

    private function getdeputyids()
    {
        $where = [
            'status'  => 1,
        ];
        $deputyids = db(self::$table_deputy)->where($where)->column('deputy_id');
        return $deputyids;
    }

    //获取所有商户

    private function getstoreids($ids)
    {
        $where = [
            'deputy_id'  => ['in',$ids],
            'status'     => 1,
        ];

        $storeids = db(self::$table_store)->where($where)->column('store_id');

        return $storeids;
    }

    //获取所有门店

    private function getshopids($ids)
    {
        $where = [
            'store_id'  => ['in',$ids],
            'status'     => 1,
        ];

        $shopids = db(self::$table_shop)->where($where)->column('shop_id');

        return $shopids;
    }

    //获取所有优惠活动

    private function getallactive($ids,$lng,$lag)
    {
        $where = [
            'store_id'  => ['in',$ids],
            'status'    => 1,
            'type'      => 1,
        ];

        $data = db(self::$table_active)->where($where)->select();
        $newarr = [];
        if(count($data) >0){
//            $arr    = [];
            //根据门店排序
            foreach($data as $k=>$v){
                $v['stime'] = date("Y-m-d",$v['stime']);
                $v['etime'] = date("Y-m-d",$v['etime']);
                if($v['applyshop'] == 0){ //全门店通用 则获取该商户下所有门店
                    $shopids = $this->getshopids($ids);
                }else{
                    $applyshop = trim($v['applyshop'],',');
                    $shopids = explode(',',$applyshop);
                }
                //获取门店
                foreach ($shopids as $kk=>$vv){
                    $getshopdistance = $this->getshopdistance($vv,$lng,$lag);
                    if($getshopdistance !== false){
                        $newarrlen = count($newarr);
                        $newarr[$newarrlen] = $v;
                        foreach ($getshopdistance['shop'] as $kkk=>$vvv){
                            $newarr[$newarrlen][$kkk] = $vvv;
                        }
//                        $data[$k]['distances'][$kk] = $getshopdistance['distances'];
                    }else{
                        unset($shopids[$kk]);
                    }
                }
            }
            //dd(11);
            $newarr = arr_sort($newarr,'distances','asc');
        }
        return $newarr;
    }

    //获取门店距离

    private function getshopdistance($shopid,$lng,$lag)
    {
        //获取所有门店按距离排序
        $shop = db(self::$table_shop)
            ->where('shop_id',$shopid)
            ->field('position,longitude,latitude,kefu_phone,shop_id')
            ->find();
        if($shop) {
            //获取当前距离
            if($lng&&$lag){
                //dd(111);
                $return = getdistance($shop['longitude'],$shop['latitude'],$lng,$lag);
                if($return/1000 > 1){
                    $distance = round($return/1000,2).'km';
                }else{
                    $distance = round($return,2).'m';
                }
                $shop['distance'] = $distance;
            }else{
                $shop['distance'] = '未知';
                $return = 0;
            }
            $shop['distances'] = $return;
            return array('shop'=>$shop);
        }else{
            return false;
        }
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
        //获取我的可用积分
        $integral = db(self::$table_member)
            ->where('member_id',$this->member_id)
            ->value('integral');
        $data = db(self::$table_goods)->where(['status'=>0])->order('sort desc')->limit(4)->select();
        return view('exchangeShop',[
            'data' => $data,
            'integral' => $integral,
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
        //获取我的信息
        $member = $this->getmember($this->member_id);
        if($member === false){
            return $this->error('没有此会员');
        }
        return view('my',[
            'member'  => $member,
        ]);
    }

    public function orderPay()
    {
        if(!$this->member_id){
            return redirecturl('orderPay');
        }
        $address_id = input('address_id',0);
        //获取已有订单
        $data = db(self::$table_goods_order)
            ->where(['order_id'=>input('order_id'),'status'=>0])
            ->find();
        //获取收货地址
        $address = [];
        if($address_id){
            $address = db(self::$table_address)->where(['address_id'=>$address_id])->find();
        }
        return view('orderPay',[
            'data' => $data,
            'address' => $address,
            'address_id' => $address_id,
        ]);
    }

    //qrdh确定兑换运费为0

    public function qrdh()
    {
        if(Request::instance()->isAjax()){
            $order_id        = input('order_id');
            $address_id      = input('address_id');
            $reciever        = '';
            $recieverphone   = '';
            $recieveraddress = '';
            $address         = db(self::$table_address)->where(['address_id'=>$address_id])->find();
            if($address){
                $reciever        = $address['name'];
                $recieverphone   = $address['phone'];
                $recieveraddress = $address['position'].$address['address'];
            }
            $order = db(self::$table_goods_order)
                ->where(['order_id'=>$order_id,'status'=>0])
                ->find();
            if($order){
                Db::startTrans();
                try{
                    //更改订单
                    db(self::$table_goods_order)
                        ->where('order_id',$order_id)
                        ->update([
                            'address_id'=>$address_id,
                            'updated_at'=>time(),
                            'status'=>1,
                            'paytime'=>time(),
                            'reciever' => $reciever,
                            'recieverphone' =>$recieverphone,
                            'recieveraddress' =>$recieveraddress,
                            ]
                        );
                    //减少物品库存
                    db(self::$table_goods)->where('goods_id',$order['goods_id'])->setDec('stock',$order['stock']);
                    //减少会员可用积分
                    db(self::$table_member)->where('member_id',$order['member_id'])->setDec('integral',$order['totalintegral']);
                    Db::commit();
                    return json(array('code'=>200,'msg'=>'兑换成功'));
                }catch(Exception $exception){
                    Db::rollback();
                    return json(array('code'=>400,'msg'=>'兑换失败'));
                }
            }else{
                return json(array('code'=>400,'msg'=>'兑换失败'));
            }
        }
    }

    //付费确认兑换

    public function payqrdh()
    {
        $order_id = input('order_id');
        $address_id = input('address_id');
        $reciever        = '';
        $recieverphone   = '';
        $recieveraddress = '';
        $address         = db(self::$table_address)->where(['address_id'=>$address_id])->find();
        if($address){
            $reciever        = $address['name'];
            $recieverphone   = $address['phone'];
            $recieveraddress = $address['position'].$address['address'];
        }
        $freight  = floatval(input('freight'));
        $order = db(self::$table_goods_order)
            ->where(['order_id'=>$order_id,'status'=>0])
            ->find();
        if($order){
            //更改订单
            $res = db(self::$table_goods_order)
                ->where('order_id',$order_id)
                ->update([
                    'address_id'=>$address_id,
                    'updated_at'=>time(),
                    'freight'   => $freight,
                    'needpay'   => $freight,
                    'reciever' => $reciever,
                    'recieverphone' =>$recieverphone,
                    'recieveraddress' =>$recieveraddress,
                ]);
            if($res !== false){
                $return['id'] = $order_id;
                $return['appId'] = $this->appId;
                $return['nonceStr'] = $this->createNoncestr();
                $return['timeStamp'] = time();
                //获取h5调起微信支付
                $result = $this->qrdhjsapipay($order,$order_id);
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
                return json(array('code'=>400,'msg'=>'兑换失败'));
            }

            //付费
//            Db::startTrans();
//            try{
//                //更改订单
//                db(self::$table_goods_order)
//                    ->where('order_id',$order_id)
//                    ->update(['address_id'=>$address_id,'updated_at'=>time(),'status'=>1,'paytime'=>time()]);
//                //减少物品库存
//                db(self::$table_goods)->where('goods_id',$order['goods_id'])->setDec('stock',$order['stock']);
//                //减少会员可用积分
//                db(self::$table_member)->where('member_id',$order['member_id'])->setDec('integral',$order['totalintegral']);
//                Db::commit();
//                return json(array('code'=>200,'msg'=>'兑换成功'));
//            }catch(Exception $exception){
//                Db::rollback();
//                return json(array('code'=>400,'msg'=>'兑换失败'));
//            }
        }else{
            return json(array('code'=>400,'msg'=>'兑换失败'));
        }
    }

    //不支付就删除订单
    public function delgoodsorder()
    {
        if(Request::instance()->isAjax()){
            $order_id = input('order_id');
            $where = [
                'order_id'  => $order_id,
                'status'    => 0,
            ];
            db(self::$table_goods_order)->where($where)->delete();
        }
    }

    //获取jsapi微信支付

    public function qrdhjsapipay($insert,$id)
    {
        $notify_url = 'http://www.yilingjiu.cn/wechat/Jsapipay/notify_qrdh';
        $openid = db(self::$table_member)->where('member_id',$insert['member_id'])->value('openid');//'os-5N1ZgTUrkGgasKpmQHpFc5R5E';
        $attach = $id;
        $pay = new Jsapipay('',$openid, $mch_id='1514213421', $key='c56d0e9a7ccec67b4ea131655038d604',$insert['order_num'],'积分兑换',$total_fee=0.01,$attach,$notify_url,'JSAPI');
        $return = $pay->pay();
        //dd($return);
        return $return;
    }

    //检测用户是否可以购买商品
    public function checkgoods()
    {
        if(Request::instance()->isAjax()) {
            $goods_id = input('goods_id');
            $stock = (int)input('stock',1);
            //获取用户总积分
            $integral = db(self::$table_member)
                ->where('member_id',$this->member_id)
                ->value('integral');
            //获取商品信息
            $goods = db(self::$table_goods)->where('goods_id',$goods_id)->find();
            if($goods){
                $appid      = $goods['app_id'];
                $goodsname  = $goods['goods_name'];
                $expressid  = $goods['express_id'];
                $goodsprice = $goods['price'];
                $goodsstock = $goods['stock'];
                if($stock>$goodsstock){
                    return json(array('code'=>400,'msg'=>'商品库存不足，商品剩余'.$goodsstock));
                }
                $payintegral = (int)($goodsprice*$stock);
                if($payintegral>$integral){
                    return json(array('code'=>400,'msg'=>'会员可用积分不足'));
                }
                $typeid = db(self::$table_goods)->where('goods_id',$goods_id)->value('type_id');
                $typename = db(self::$table_goods_type)->where('type_id',$typeid)->value('type_name');
                //生成订单
                //运费
                $freight = 0;
                if($expressid>0) {
                    $express = db(self::$table_express_templete)
                        ->where(['app_id' => $appid, 'express_id' => $expressid])
                        ->find();
                    if ($express) {
                        switch ($express['ismail']) {
                            case 1: //包邮
                                $freight = 0;
                                break;
                            case 2: //买家承担运费
                                $freight = $express['basefee']+($stock-1)*$express['increfee'];
                                break;
                            case 3: //满足件数包邮
                                if((int)$express['usecondition']>$stock){
                                    $freight = $express['basefee']+($stock-1)*$express['increfee'];
                                }
                                break;
                            case 4: //满足金额包邮
                                $freight = $express['basefee']+($stock-1)*$express['increfee'];
                                break;
                            default: //默认包邮哟
                                $freight = 0;
                                break;
                        }
                    }
                }
                $insert = [
                    'app_id'    => $appid,
                    'goods_id'  => $goods_id,
                    'goods_name'=> $goodsname,
                    'integral'  => $goodsprice,
                    'member_id' => $this->member_id,
                    'order_num' => date("Ymd").time().rand(10000,99999),
                    'status'    => 0,
                    'created_at'=> time(),
                    'stock'     => $stock,
                    'totalintegral'=> $payintegral,
                    'freight'   => $freight,
                    'needpay'   => $freight,
                    'type_id'   => $typeid,
                    'type_name' => $typename,
                ];
                $id = db(self::$table_goods_order)->insertGetId($insert);
                if($id){
                    return json(array('code'=>200,'msg'=>$id));
                }else{
                    return json(array('code'=>400,'msg'=>'兑换失败'));
                }
            }else{
                return json(array('code'=>400,'msg'=>'系统错误'));
            }
        }
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
        $data = db(self::$table_goods)->where(['goods_id'=>input('goods_id')])->find();
        //dd($data);
        //兑换记录 取最新三条
        $duihuan = db(self::$table_goods_order)
                ->where(['goods_id'=>input('goods_id'),'status'=>1])
                ->order('paytime desc')
                ->limit(3)
                ->select();
        if(!empty($duihuan)){
            foreach ($duihuan as $k=>$v){
                $member = db(self::$table_member)
                    ->where('member_id',$v['member_id'])
                    ->field('cover,name')
                    ->find();
                $duihuan[$k]['name'] = $member['name'];
                $duihuan[$k]['cover']= $member['cover'];
            }
        }
        return view('shopDetail',[
            'data' => $data,
            'duihuan'=> $duihuan,
        ]);
    }

    public function shopDetailList()
    {
        if(!$this->member_id){
            return redirecturl('shopDetailList');
        }
        //dd($data);
        //兑换记录
        $duihuan = db(self::$table_goods_order)
            ->where(['goods_id'=>input('goods_id'),'status'=>1])
            ->order('paytime desc')
            ->select();
        if(!empty($duihuan)){
            foreach ($duihuan as $k=>$v){
                $member = db(self::$table_member)
                    ->where('member_id',$v['member_id'])
                    ->field('cover,name')
                    ->find();
                $duihuan[$k]['name'] = $member['name'];
                $duihuan[$k]['cover']= $member['cover'];
            }
        }
        return view('shopDetailList',[
            'duihuan'=> $duihuan,
        ]);
    }
    public function shopIndex()
    {
        if(!$this->member_id){
            return redirecturl('shopIndex');
        }
        //获取商品分类
        $data = db(self::$table_goods_type)->where(['status'=>1])->order('sort asc')->select();
        if(!empty($data)){
            foreach ($data as $k=>$v){
                $data[$k]['goods'] = db(self::$table_goods)->where(['type_id'=>$v['type_id']])->select();
            }
        }
        //dd($data);
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
        $getSignPackage = json_decode($this->getSignPackage(),true);
        $vipcard = $this->getvipcard();
        $memeber = $this->getmember($this->member_id);
        return view('tabs',[
            'member'     => $memeber,
            'signPackage'=> $getSignPackage,
            'vipcard'    => $vipcard,
        ]);
    }

    //获取会员卡信息

    public function getstoredcard()
    {
        if(Request::instance()->isAjax()){
            $lng = input('lng');
            $lag = input('lag');
            //dd($lng.$lag);
            $where = [
                'member_id'  => $this->member_id,
                'type'       => 2,
            ];

            $vipcard = db(self::$table_recieve_vipcard)->where($where)->select();
            //dd($vipcard);
            if(count($vipcard)>0){
                foreach ($vipcard as $k=>$v){
                    //获取所有门店按距离排序
                    $shop = db(self::$table_shop)
                        ->where('shop_id',$v['shop_id'])
                        ->find();
                    if($shop) {
                        $pic = trim($shop['pic_arr'],',');
                        //echo $pic;
                        if($pic){
                            $pic = explode(',',$pic);
                            $str = '';
                            foreach($pic as $vv){
                                $str = $vv;
                            }
                        }else{
                            $str = "__IMG__/rule_popups.png";
                        }
                        $shop['pic'] = $str;
                        //获取当前距离
                        if($lng&&$lag){
                            //dd(111);
                            $return = getdistance($shop['longitude'],$shop['latitude'],$lng,$lag);
                            if($return/1000 > 1){
                                $distance = round($return/1000,2).'km';
                            }else{
                                $distance = $return.'m';
                            }
                            $shop['distance'] = $distance;
                            //dd($shop);
                            //dd($shop);
                        }else{
                            $shop['distance'] = '未知';
                            $return = 0;
                        }
                    }
                    $vipcard[$k]['distances'] = $return;
                    $vipcard[$k]['shop'] = $shop;
                    $vipcard[$k]['cardname'] = db(self::$table_storedcard)
                        ->where('card_id',$v['card_id'])
                        ->value('cardname');
                }
                $vipcard = arr_sort($vipcard,'distances','asc');
                return json(array('code'=>200,'msg'=>$vipcard));
            }else{
                return json(array('code'=>400,'msg'=>'还没有领取储值卡呦，要赶快领取嘞'));
            }
        }
    }

    //获取会员卡信息

    private function getvipcard()
    {
        $where = [
            'member_id'  => $this->member_id,
            'type'       => 1,
        ];
        $vipcard = db(self::$table_recieve_vipcard)->where($where)->select();
        if(count($vipcard)>0){
            foreach ($vipcard as $k=>$v){
                $vipcard[$k]['shop'] = db(self::$table_shop)
                    ->where('shop_id',$v['shop_id'])
                    ->find();
                $vipcard[$k]['cardname'] = db(self::$table_vipcard)
                    ->where('card_id',$v['card_id'])
                    ->value('cardname');
            }
            return $vipcard;
        }else{
            return [];
        }
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
            $shopid = input('shop_id');
            //dd($shopid);
            $where = [
                'status'    => 1,
            ];
            if(!empty($shopid)){
                $deputy_id = $this->deputy_id($shopid);
                if($deputy_id){
                    //根据代理获取商户根据商户获取所有门店
                    $storeids = db(self::$table_store)
                        ->where(['deputy_id' => $deputy_id, 'status' => 1])
                        ->column('store_id');
                    if (!empty($storeids)) {
                        $where['store_id'] = ['in',$storeids];
                    }
                }
            }
            //dd($lng);
            $shop = db(self::$table_shop)->where($where)->select();
            if(!empty($shop)){
                foreach($shop as $k=>$v){
                    //获取距离
                    $s = getdistance($lng,$lat,$v['longitude'],$v['latitude']);
                    $shop[$k]['distances'] = $s;
                    if($s/1000<1){
                        $shop[$k]['distance'] =  round($s,2).'m';
                    }else{
                        $shop[$k]['distance'] =  round(($s/1000),2).'km';
                    }
                }
                //根据距离排序
                $shop = arr_sort($shop,'distances',$order="asc");
                return json_encode(['code'=>200,'data'=>$shop]);
            }else{
                return json_encode(['code'=>400,'data'=>'暂无数据']);
            }

        }
    }

    public function aa()
    {
        dd(11);
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
            $deputy_id = input('deputy_id');
            //dd(11111);
            if($lng&&$lat&&(int)$deputy_id == 0) {
                //获取省市区信息
                $return = byLtGetCity($lng, $lat);
                //dd($return);
                //dd($return);
                $province = mb_substr($return[0], 0, mb_strlen($return[0]) - 1); //省
                $city = mb_substr($return[1], 0, mb_strlen($return[1]) - 1); //市
                $county = $return[2];  //区
                //根据省市区反查代理
                $deputy_id = db(self::$table_deputy)
                    ->where(['province' => $province, 'city' => $city, 'county' => $county])
                    ->value('deputy_id');
                if (!$deputy_id) {
                    $deputy_id = db(self::$table_deputy)
                        ->where(['province' => $province, 'city' => $city])
                        ->value('deputy_id');
                    if (!$deputy_id) {
                        return json(['code' => 400, 'data' => '暂无数据']);
                    }
                }
            }
            //根据代理获取商户根据商户获取所有门店
            $storeids = db(self::$table_store)
                ->where(['deputy_id' => $deputy_id, 'status' => 1])
                ->column('store_id');
            if (empty($storeids)) {
                return json(['code' => 400, 'data' => '暂无数据']);
            }
            $shop = db(self::$table_shop)->where(['status' => 1])->whereIn('store_id', $storeids)->select();
            if (!empty($shop)) {
                foreach ($shop as $k => $v) {
                    //获取距离
                    $s = getdistance($lng, $lat, $v['longitude'], $v['latitude']);
                    $shop[$k]['distances'] = $s;
                    if ($s / 1000 < 1) {
                        $shop[$k]['distance'] = round($s, 2) . 'm';
                    } else {
                        $shop[$k]['distance'] = round(($s / 1000), 2) . 'km';
                    }

                }
                //dd(arr_sort($shop,'distances',$order="asc"));
                //根据距离排序
                $shop = arr_sort($shop, 'distance', $order = "asc");
                return json(['code' => 200, 'data' => $shop, 'deputy_id' => $deputy_id]);
            } else {
                return json(['code' => 400, 'data' => '暂无数据']);
            }
        }
    }


    public function test()
    {
        //假设抽奖时间段为 05:00:00--07:00,08:00:00--10:00:00 我在六点抽了俩次
        $choutime = date("Y-m-d",time())." 06:00:00";
        $nowtime  = time();
        //判断当前时间 在哪个时间段  //然后判断抽奖时间是否在这个时间段 如果在则证明次数有效 否则次数清零
        $base64 = 'data:image/jpeg;base64,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';
        preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64, $result);
        $base64img = base64_decode(str_replace($result[1], '', $base64));
        //dd($base64img);
        $type = $result[2];//获取图片的类型jpg png等
        $pathname = ROOT_PATH.'public/qr_uploads/';
        if(!is_dir($pathname)) { //若目录不存在则创建之
            mkdir($pathname,0777,true);
        }
        //图片名
        $ad = 'qrcode_' . rand(10000,99999) . '.'.$type;
        //图片保存路径
        $savepath = $pathname.$ad;
        $res = file_put_contents($savepath, base64_decode(str_replace($result[1], '', $base64)));//对图片进行解析并保存;
        if($res){
            //上传骑牛
            //$file = Qiniu::qiniu($savepath, $type);
            //return $file;

        }
        //dd($res);
        return view('test',[
            'base64' => $base64,
            'base64img' => $base64img,
            'savepath' => '/yangwenyu/public/qr_uploads/'.$ad,
        ]);
    }

    /**************************************门店详情****************************************************/
    public function shopdes()
    {
        if(!$this->member_id){
            //获取签名信息

            return redirecturl('shopdes');
        }
        $shop_id = input('shop_id');
        $store_id = input('store_id');
        //获取门店详情
        $shop = db(self::$table_shop)
            ->where('shop_id',$shop_id)
            ->find();
//        //获取门店会员卡
//        $vipcards = $this->getcards($shop_id,$store_id);
//        //获取门店大转盘活动
//        $deputy_id = $shop['deputy_id'];
//        $dzp = $this->deputy_dzp($deputy_id);
//        //dd($dzp);
//        if($dzp===false){
//            $dzp = [];
//        }

        return view('shopdes',[
            'shop'      => $shop,
            //'vipcards'  => $vipcards,
            //'dzp'       => $dzp,
        ]);
    }

    //获取门店会员卡

    private function getcards($shopid,$storeid)
    {
        $cards = db(self::$table_vipcard)
            ->where(['status'=>1,'store_id'=>$storeid])
            ->select();
        //dd($cards);
        $applycards = [];
        if(!empty($cards)){
            foreach($cards as $k=>$v){
                if($v['applyshop'] == 0){
                    $applycards[$k] = $v;
                }else{
                    $v['applyshop'] = explode(',',$v['applyshop']);
                    if(in_array($shopid,$v['applyshop'])){
                        $applycards[$k] = $v;
                    }
                }
            }
        }
        $vipcards = [];
        //dd($applycards);
        if(!empty($applycards)){
            foreach($applycards as $val){
                $vipcards[] = $val;
            }
            return $vipcards;
        }else{
            return [];
        }
    }

    //会员卡列表

    public function vipcardlist()
    {
        if(!$this->member_id){
            //获取签名信息

            return redirecturl('vipcardlist');
        }
        $shop_id = input('shop_id');
        $store_id = input('store_id');
        //获取门店会员卡
        $vipcards = $this->getcards($shop_id,$store_id);
        //dd($vipcards);
        return view('vipcardlist',[
            'data'      => $vipcards,
            'member_id' => $this->member_id,
            'store_id'  => $store_id,
            'shop_id'   => $shop_id,
        ]);
    }

    //领取会员卡

    public function recievecard()
    {
        if(Request::instance()->isAjax()) {
            //dd(1);
            //判断是否领取过会员卡
            $where = [
                'app_id'        => input('app_id'),
                'store_id'      => input('store_id'),
                'member_id'     => input('member_id'),
                'card_id'       => input('card_id'),
                'type'          => 1,
            ];
            $count = db(self::$table_recieve_vipcard)->where($where)->count();
            if ($count > 0) {
                return json(array('code' => 400, 'msg'=>'已经领取过了'));
            }
            $validity = (int)input('validity');
            $insert['app_id']   = input('app_id');
            $insert['store_id'] = input('store_id');
            $insert['shop_id']  = input('shop_id');
            $insert['member_id']= input('member_id');
            $insert['card_id']  = input('card_id');
            $insert['money']    = input('needpay');
            if ($validity == 0) {
                $insert['endtime'] = null;
            } else {
                $validitytime = $validity * 24 * 3600;
                $insert['endtime'] = time() + $validitytime;
            }
            $insert['created_at'] = time();
            $inserts = [
                'app_id'        => input('app_id'),
                'store_id'      => input('store_id'),
                'member_id'     => input('member_id'),
                'totalmoney'    => floatval(input('totalmoney')),
                'yue'           => floatval(input('yue')),
                'created_at'    => time(),
                'updated_at'    => time(),
                'status'        => 1,
            ];
            //存入我的会员卡 存入商户会员列表
            Db::startTrans();
            try {
                db(self::$table_recieve_vipcard)->insertGetId($insert);
                //判断是否已是商户会员
                $wheres = [
                    'app_id'        => input('app_id'),
                    'store_id'      => input('store_id'),
                    'member_id'     => input('member_id'),
                ];
                $len = db(self::$table_store_member)->where($wheres)->count();
//                if($len >0){
//                    db(self::$table_store_member)->where($wheres)->setInc('totalmoney',$inserts['totalmoney']);
//                    db(self::$table_store_member)->where($wheres)->setInc('yue',$inserts['yue']);
//                }
                if($len == 0){
                    db(self::$table_store_member)->insertGetId($inserts);
                }
                Db::commit();
                return json(array('code' => 200, 'msg'=>'领取成功'));
            } catch (Exception $e) {
                Db::rollback();
                return json(array('code' => 400, 'msg'=>'领取失败'));
            }
        }
    }

    //付费领取会员卡订单
    public function recievecardorder(){
        if(Request::instance()->isAjax())
        {
            //判断是否领取过会员卡
            $where = [
                'app_id'        => input('app_id'),
                'store_id'      => input('store_id'),
                'member_id'     => input('member_id'),
                'card_id'       => input('card_id'),
                'type'          => 1,
            ];
            $count = db(self::$table_recieve_vipcard)->where($where)->count();
            if ($count > 0) {
                return json(array('code' => 400, 'msg'=>'已经领取过了'));
            }
            $insert['app_id']   = input('app_id');
            $insert['store_id'] = input('store_id');
            $insert['shop_id']  = input('shop_id');
            $insert['member_id']= input('member_id');
            $insert['card_id']  = input('card_id');
            $insert['needpay']  = floatval(input('needpay'));
            $insert['type']     = 1;
            $insert['status']   = 0;
            $insert['order_num']= input('store_id').time().rand(100000,999999);
            $insert['created_at']= time();
            //dd($insert);
            $id = db(self::$table_vipcard_order)->insertGetId($insert);
            if($id){
                $return['id'] = $id;
                $return['appId'] = $this->appId;
                $return['nonceStr'] = $this->createNoncestr();
                $return['timeStamp'] = time();
                //获取h5调起微信支付
                $result = $this->cardjsapipay($insert,$id);
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

    //获取jsapi微信支付

    public function cardjsapipay($insert,$id)
    {
        $notify_url = 'http://www.yilingjiu.cn/wechat/Jsapipay/notify_card';
        $openid = db(self::$table_member)->where('member_id',$insert['member_id'])->value('openid');//'os-5N1ZgTUrkGgasKpmQHpFc5R5E';
        $attach = $id;
        $pay = new Jsapipay('',$openid, $mch_id='1514213421', $key='c56d0e9a7ccec67b4ea131655038d604',$insert['order_num'],'领取会员卡',$total_fee=0.01,$attach,$notify_url,'JSAPI');
        $return = $pay->pay();
        //dd($return);
        return $return;
    }

    //不支付就删除订单
    public function delvipcardorder()
    {
        if(Request::instance()->isAjax()){
            $order_id = input('order_id');
            $where = [
                'order_id'  => $order_id,
                'status'    => 0,
            ];
            db(self::$table_vipcard_order)->where($where)->delete();
        }
    }

    //储值卡列表

    public function storedcardlist()
    {
        if(!$this->member_id){
            //获取签名信息

            return redirecturl('storedcardlist');
        }
        $shop_id = input('shop_id');
        $store_id = input('store_id');
        //获取门店会员卡
        $vipcards = $this->getstoredcards($shop_id,$store_id);
        //dd($vipcards);
        return view('storedcardlist',[
            'data'      => $vipcards,
            'member_id' => $this->member_id,
            'store_id'  => $store_id,
            'shop_id'   => $shop_id,
        ]);
    }

    //获取门店会员卡

    private function getstoredcards($shopid,$storeid)
    {
        $cards = db(self::$table_storedcard)
            ->where(['status'=>1,'store_id'=>$storeid])
            ->select();
        //dd($cards);
        $applycards = [];
        if(!empty($cards)){
            foreach($cards as $k=>$v){
                if($v['applyshop'] == 0){
                    $applycards[$k] = $v;
                }else{
                    $v['applyshop'] = explode(',',$v['applyshop']);
                    if(in_array($shopid,$v['applyshop'])){
                        $applycards[$k] = $v;
                    }
                }
            }
        }
        $vipcards = [];
        //dd($applycards);
        if(!empty($applycards)){
            foreach($applycards as $val){
                $vipcards[] = $val;
            }
            return $vipcards;
        }else{
            return [];
        }
    }

    //领取会员卡

    public function recievestoredcard()
    {
        if(Request::instance()->isAjax()) {
            //dd(1);
            //判断是否领取过会员卡
            $where = [
                'app_id'        => input('app_id'),
                'store_id'      => input('store_id'),
                'member_id'     => input('member_id'),
                'card_id'       => input('card_id'),
                'type'          => 2,
            ];
            $count = db(self::$table_recieve_vipcard)->where($where)->count();
            if ($count > 0) {
                return json(array('code' => 400, 'msg'=>'已经领取过了'));
            }
            $insert['app_id']   = input('app_id');
            $insert['store_id'] = input('store_id');
            $insert['shop_id']  = input('shop_id');
            $insert['member_id']= input('member_id');
            $insert['card_id']  = input('card_id');
            $insert['money']    = input('givemoney')+input('storedmoney');
            $insert['cashmoney']= input('givemoney')+input('storedmoney');
            $insert['type']     = 2;
            $insert['endtime']  = null;
            $insert['created_at'] = time();

            $totalmoney   = input('givemoney')+input('storedmoney');
            $storedmoney   = input('givemoney')+input('storedmoney');


            //存入我的会员卡 存入会员列表
            Db::startTrans();
            try {
                db(self::$table_recieve_vipcard)->insertGetId($insert);
                //判断是否已是商户会员
                $wheres = [
                    'member_id'     => input('member_id'),
                ];
                db(self::$table_member)->where($wheres)->setInc('totalmoney',$totalmoney);
                db(self::$table_member)->where($wheres)->setInc('storedmoney',$storedmoney);
                Db::commit();
                return json(array('code' => 200, 'msg'=>'领取成功'));
            } catch (Exception $e) {
                Db::rollback();
                return json(array('code' => 400, 'msg'=>'领取失败'));
            }
        }
    }

    //recievestoredcardorder

    //付费领取储值卡订单
    public function recievestoredcardorder(){
        if(Request::instance()->isAjax())
        {
            //判断是否领取过会员卡
            $where = [
                'app_id'        => input('app_id'),
                'store_id'      => input('store_id'),
                'member_id'     => input('member_id'),
                'card_id'       => input('card_id'),
                'type'          => 2,
            ];
            $count = db(self::$table_recieve_vipcard)->where($where)->count();
            if ($count > 0) {
                return json(array('code' => 400, 'msg'=>'已经领取过了'));
            }
            $insert['app_id']   = input('app_id');
            $insert['store_id'] = input('store_id');
            $insert['shop_id']  = input('shop_id');
            $insert['member_id']= input('member_id');
            $insert['card_id']  = input('card_id');
            $insert['needpay']  = floatval(input('needpay'));
            $insert['givemoney']  = floatval(input('givemoney'));
            $insert['storedmoney']  = floatval(input('storedmoney'));
            $insert['type']     = 4;
            $insert['status']   = 0;
            $insert['order_num']= input('store_id').time().rand(100000,999999);
            $insert['created_at']= time();
            //dd($insert);
            $id = db(self::$table_vipcard_order)->insertGetId($insert);
            if($id){
                $return['id'] = $id;
                $return['appId'] = $this->appId;
                $return['nonceStr'] = $this->createNoncestr();
                $return['timeStamp'] = time();
                //获取h5调起微信支付
                $result = $this->storedcardjsapipay($insert,$id);
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

    //获取jsapi微信支付

    public function storedcardjsapipay($insert,$id)
    {
        $notify_url = 'http://www.yilingjiu.cn/wechat/Jsapipay/notify_storedcard';
        $openid = db(self::$table_member)->where('member_id',$insert['member_id'])->value('openid');//'os-5N1ZgTUrkGgasKpmQHpFc5R5E';
        $attach = $id;
        $pay = new Jsapipay('',$openid, $mch_id='1514213421', $key='c56d0e9a7ccec67b4ea131655038d604',$insert['order_num'],'领取会员卡',$total_fee=0.01,$attach,$notify_url,'JSAPI');
        $return = $pay->pay();
        //dd($return);
        return $return;
    }

    //不支付就删除订单
    public function delstoredcardorder()
    {
        if(Request::instance()->isAjax()){
            $order_id = input('order_id');
            $where = [
                'order_id'  => $order_id,
                'status'    => 0,
            ];
            db(self::$table_vipcard_order)->where($where)->delete();
        }
    }
    /*****************************************************个人中心*********************************************************/

    //我的

    private function getmember($memberid)
    {
        $member = db(self::$table_member)->where(['member_id'=>$memberid])->find();
        if($member){
            return $member;
        }else{
            return false;
        }
    }
}




