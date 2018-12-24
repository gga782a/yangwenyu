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
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->member_id = $this->check();
        $this->appId = _config('AppId');
        $this->appSecret = _config('AppSecret');
    }

    public function index()
    {
        //dd($this->member_id);
//        if(!$this->member_id){
//            return redirecturl('index');
//        }
        //根据门店id 获取代理id
        $dpeuty_id = $this->deputy_id(3);
        if((int)$dpeuty_id > 0) {
            //获取大转盘
            $dzp = $this->deputy_dzp($dpeuty_id);
            //dd($dzp);
            $dzpprize = [];
            if($dzp){
                $prize = $dzp['prize']?json_decode($dzp['prize'],true):'';
                //根据大转盘奖项ids获取奖项礼品
                $dzpprize = db(self::$table_prize)->whereIn('prize_id',$prize)->where(['sum'=>['>',0]])->column('name');
                //dd($dzpprize);
            }else{
                $dzp = [];
            }
        }

        return view('index',[
            'dzp'  => $dzp,
            'dzpprize' => $dzpprize,
        ]);
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
            $dzps = db(self::$table_slyderadventures)->select();
            if(!empty($dzps)){
                $dzp = $dzps[array_rand($dzps,1)];
                return $dzp;
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


}




