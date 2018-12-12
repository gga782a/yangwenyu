<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/12/3
 * Time: 8:41 AM
 */

namespace app\admin\controller;

use app\DataAnalysis;
use think\Db;
use think\Exception;
use think\Request;
use think\Session;
use think\Validate;
use app\wechat\controller\Saomapay;

class Deputy extends Common
{
    public $id;
    public $app_id;
    public static $table_store = 'store';
    public static $table_deputy = 'deputy';
    public static $table_shui = 'shui';
    public static $table_goushui = 'goushui';
    public static $table_goushui_order = 'goushui_order';
    public static $table_pay_setting = 'pay_setting';
    public static $msg = [];
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->id = Session::get('user_id');
        $this->app_id = Session::get('app_id');
        self::$msg = array_merge(DataAnalysis::$msg,self::$msg);
    }

    public function index()
    {
        return view('deputy/index');
    }
    /***********************************************商户*******************************************************/
    //设置商户
    public function setstore()
    {
        $flag = input('flag');
        $where = [
            'app_id'     => $this->app_id,
            'deputy_id'  => $this->id,
            'store_id'   => $this->parme('store_id'),
        ];
        if(Request::instance()->isPost()){
            $data = [
                'storename'     => $this->parme('storename'),
                'storemobile'   => $this->parme('storemobile'),

                //'type'          => $this->parme('type'), //活动类型
                //'type_id'       => $this->parme('type-id'), //活动ID
                'updated_at'    => time(),
            ];
            if($flag == 'add'){
//                //检测当前账号是否可用
//                $rule = [
//                    'username' => 'require|unique|chsDash|min:4|max:18',
//                    'pwd' => 'require|confirm:repwd|alphaNum|min:4|max:18',
//                ];
//                $field = [
//                    'username' => '账号',
//                    'pwd' => '密码',
//                ];
//                $validate = new Validate($rule, self::$msg, $field);
//                if (!$validate->check($this->parme)) {
//                    $this->error($validate->getError());
//                } else {
                    $res = $this->checkusername($this->parme('username'));
                    if ($res === false) {
                        $this->error('账号已存在');
                    }
                    $data['username'] = $this->parme('username');
                    $data['pwd'] = md5(sha1($this->parme('pwd')));
                    $data['app_id'] = $this->app_id;
                    $data['deputy_id'] = $this->id;
                    $data['created_at'] = time();
                    $data['status'] = 1;
                    $id = db(self::$table_store)->insertGetId($data);
                //}
                if($id){
                    return $this->redirect('deputy/setstore');
                }else{
                    return $this->error('添加失败');
                }
            }else{
                $res = db(self::$table_store)->where($where)->update($data);
                if($res){
                    return $this->redirect('deputy/setstore');
                }else{
                    return $this->error('修改失败');
                }
            }
        }else{
            if($flag == 'add'){
                return view('deputy/addStore');
            }else if($flag=='update'){
                $data = db(self::$table_store)->where($where)->find();
                if($data){
                    return view('deputy/updatestore',[
                        'data'  => $data,
                    ]);
                }else{
                    return $this->redirect('deputy/setstore');
                }
            }else{
                $list = db(self::$table_store)
                    ->where(['app_id'=>$this->app_id,'deputy_id'=>$this->id,'status'=>1])
                    ->order('created_at desc')
                    ->page(input('page',1),input('pageshow,15'))
                    ->select();
                return view('deputy/storelist',[
                    'data' => $list,
                ]);
            }
        }

    }

    //检测当前账号是否可用

    private function checkusername($username)
    {
        $where = [
            'app_id'    => $this->app_id,
            'deputy_id' => $this->id,
            'username'  => $username,
        ];
        $count = db(self::$table_store)
            ->where($where)
            ->count();
        if($count > 0){
            return false;
        }else{
            return true;
        }
    }

    //删除商户

    public function delstore()
    {
        //dd(222);
        if(Request::instance()->isAjax()) {
            $where = [
                'app_id'    => $this->app_id,
                'deputy_id' => $this->id,
                'store_id'  => $this->parme('store_id')
            ];
            $res = db(self::$table_store)->where($where)->delete();
            if ($res) {
                return json(['code'=>200,'msg'=>'操作成功']);
            } else {
                return json(['code'=>400,'msg'=>'操作失败']);
            }
        }
    }

    //重置商户密码

    public function resetpwd()
    {
        $where = [
            'app_id'    => $this->app_id,
            'deputy_id' => $this->id,
            'store_id'  => $this->parme('store_id')
        ];
        if(Request::instance()->isPost()){
            $rule = [
                'pwd'       => 'require|confirm:repwd|alphaNum|min:4|max:18',
                'store_id'  => 'require',
            ];
            $field = [
                'username'  => '账号',
                'pwd'       => '密码',
                'store_id'  => '商户ID',
            ];

            $validate = new Validate($rule, self::$msg, $field);
            if (!$validate->check($this->parme)) {
                $this->error($validate->getError());
            } else {
                $pwd = md5(sha1($this->parme('pwd')));
                $res = db(self::$table_store)
                    ->where($where)
                    ->update(['pwd'=>$pwd,'updated_at'=>time()]);
                if($res){
                    return $this->redirect('deputy/setstore');
                }else{
                    $this->error('操作失败');
                }
            }
        }else{
            $username = db(self::$table_store)
                ->where($where)
                ->value('username');
            //$username = 'aa';
            return view('resetpwd',[
                'username' => $username
            ]);
        }
    }
    //重置密码
    public function resetdeputypwd()
    {
        $where = [
            'app_id'    => $this->app_id,
            'deputy_id' => $this->id,
        ];
        if(Request::instance()->isPost()){
            $rule = [
                'pwd'       => 'require|confirm:repwd|alphaNum|min:4|max:18',
            ];
            $field = [
                'username'  => '账号',
                'pwd'       => '密码',
            ];

            $validate = new Validate($rule, self::$msg, $field);
            if (!$validate->check($this->parme)) {
                $this->error($validate->getError());
            } else {
                $pwd = md5(sha1($this->parme('pwd')));
                $res = db(self::$table_deputy)
                    ->where($where)
                    ->update(['pwd'=>$pwd,'updated_at'=>time()]);
                if($res){
                    return $this->redirect('deputy/resetdeputypwd');
                }else{
                    $this->error('操作失败');
                }
            }
        }else{
            $username = db(self::$table_deputy)
                ->where($where)
                ->value('username');
            //dd($username);
            //$username = 'aa';
            return view('resetpwd',[
                'username' => $username
            ]);
        }
    }
    /********************************************服务订购*************************************************************/
    //服务订购

    public function serviceorder()
    {
        $where = [
            'app_id'    => $this->app_id,
            'deputy_id' => $this->id,
            'store_id'  => 0,
        ];
        $statusarr = [
            '未支付','支付成功,待发货','待收货','订单完成'
        ];
        $data = db(self::$table_goushui_order)
            ->where($where)
            ->page(input('page',1),input('pageshow',15))
            ->select();
        if(!empty($data)){
            foreach($data as $k=>$v){
                $data[$k]['statusname'] = $statusarr[$v['status']];
            }
        }
//        //查询当前代理等级
//        $level = db(self::$table_store)->where($where)->value('level');
        return view('listservice',[
            'data'  => $data,
        ]);
    }
    //购水
    public function goushui()
    {
        $where = [
            'app_id'    => $this->app_id,
            'deputy_id' => $this->id,
        ];
        if(Request::instance()->isPost()){

        }else{
            //查询当前代理等级
            $deputy = db(self::$table_deputy)->where($where)->field('level,parentid')->find();
            if($deputy) {
                $level = $deputy['level'];
                $parentid = $deputy['parentid'];
                $istrue = false;
                $goushui = [];
                if ($level == 0) {
                    $field = 'shui_id,name,stock,deputyprice';
                    //在分公司买水 如果不存在分公司或者分公司没有水（水余量为0） 则可以在总平台购买
                    if($parentid != 0){
                        $goushui = db(self::$table_goushui)
                           ->where(['app_id'=> $this->app_id,'type'=>1,'type_id'=>$parentid])
                           ->select();
                        if(count($goushui)>0){
                            foreach($goushui as $k=>$v){
                                if($v['stock']>0){
                                    break;
                                }else{
                                    $istrue = true;
                                }
                            }
                        }else{
                            $istrue = true;
                        }
                    }else{
                        $istrue = true;
                    }
                } else {
                    $field = 'shui_id,name,stock,companyprice';
                    $istrue = true;
                }
                if($istrue){
                    //水列表
                    $list = db(self::$table_shui)
                        ->where(['app_id' => $this->app_id])
                        ->field($field)
                        ->page(input('page', 1), input('pageshow', 15))
                        ->select();
                    if(!empty($list)){
                        foreach ($list as $key=>$val){
                            if($level == 0){
                                $list[$key]['price'] = $val['deputyprice'];
                            }else{
                                $list[$key]['price'] = $val['companyprice'];
                            }
                        }
                    }
                    //dd($list);
                }else{
                   $list = $goushui;
                }
                //dd($list);
                return view('goushuilist', [
                    'data' => $list,
                    'deputy_id' => $this->id,
                    'app_id'    => $this->app_id,
                ]);
            }
        }
    }

    //生成购水订单

    public function goushuiorder()
    {
        $params = input();
//        if(Request::instance()->isPost()){
//            $insert = [
//                'app_id'        => input('app_id'),
//                'deputy_id'     => input('deputy_id'),
//                'shui_id'       => input('shui_id'),
//                'store_id'      => 0,
//                'name'          => input('name'),
//                'stock'         => (int)input('stock'),
//                'price'         => floatval(input('price')),
//                'receiver'      => input('receiver'),
//                'receiverphone' => input('receiverphone'),
//                'receiveraddress'=> input('receiveraddress'),
//                'status'        => 0,
//                'needpay'       => floatval(input('needpay')),
//                'order_num'     => time().rand(000000,999999),
//                'created_at'    => time(),
//            ];
//            $id = db(self::$table_goushui_order)->insertGetId($insert);
//            if($id){
//                //跳转到扫码支付页
//                return $this->redirect('deputy/saomapay',['id'=>$id]);
//            }else{
//                return $this->error('操作失败');
//            }
//        }else{
            return view("goushuiorder",[
                'params' => $params
            ]);
        //}
    }

    public function goss()
    {
        return view('goss');
    }

    public function ss()
    {
        //dd(111);
        return json(['code'=>400,'msg'=>'操作失败']);
    }
    //ajax生成支付订单

    public function creategoushuiorder()
    {
        //return json(['code'=>400,'msg'=>'操作失败']);
        if(Request::instance()->isAjax()){
            $insert = [
                'app_id'        => input('app_id'),
                'deputy_id'     => input('deputy_id'),
                'shui_id'       => input('shui_id'),
                'store_id'      => 0,
                'name'          => input('name'),
                'stock'         => (int)input('stock'),
                'price'         => floatval(input('price')),
                'receiver'      => input('receiver'),
                'receiverphone' => input('receiverphone'),
                'receiveraddress'=> input('receiveraddress'),
                'status'        => 0,
                'needpay'       => floatval(input('needpay')),
                'order_num'     => time().rand(000000,999999),
                'created_at'    => time(),
            ];
            $id = db(self::$table_goushui_order)->insertGetId($insert);
            if($id){
                //跳转到扫码支付页
                //return json(['code'=>400,'msg'=>$id]);
                $return = $this->saomapay($id);
                if($return === false){
                    return json(['code'=>400,'msg'=>'操作失败']);
                }else{
                    return json(['code'=>200,'msg'=>$return,'id'=>$id]);
                }
            }else{
                return json(['code'=>400,'msg'=>'操作失败']);
            }
        }
    }

    //扫码支付

    public function saomapay($id='')
    {
        //return json(['code'=>400,'msg'=>'扫码']);
        //$id = input('id');
        //$id = 1;
        //查询订单
        $order = db(self::$table_goushui_order)
            ->where(['app_id'=>$this->app_id,'order_id'=>$id])
            ->field('order_num,needpay,name,shui_id')
            ->find();
        //dd($order);
        if($order){
            //return json(['code'=>400,'msg'=>'操作失败']);
            //获取商户信息
            $paysetting  = db(self::$table_pay_setting)
                ->where(['app_id'=>$this->app_id])
                ->find();
            //dd($paysetting);
            if($paysetting){
                //return json(['code'=>400,'msg'=>'操作失败']);
                //调起扫码支付 生成二维码
                $notify_url = "http://www.yilingjiu.cn/wechat/Saomapay/notify";
                //$order['needpay']
                $somapay = new Saomapay($order['shui_id'],$openid='', $paysetting['mch_id'], $paysetting['mch_key'],$order['order_num'],$order['name'],0.01,$id,$notify_url);
                $return  = $somapay->pay();
                //dd($return);
                if($return['return_code'] == 'SUCCESS'&&$return['return_msg'] == 'OK'){
                    //return json(['code'=>400,'msg'=>444]);
                    $code_url = $return['code_url'];

                    //生成二维码
                    return $this->qr_code($code_url);
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function getpaysetting()
    {
        //dd($this->app_id);
        $paysetting  = db(self::$table_pay_setting)
            ->where(['app_id'=>$this->app_id])
            ->find();
        dd($paysetting);
    }

    public function ceshi()
    {
        db('ceshi')->insertGetId(array('text1'=>'saoma','text2'=>1111));
    }
    //生成二维码
    public function qr_code($url='',$level=3,$size=7)
    {
        //return json(['code'=>400,'msg'=>222]);
        //dd(ROOT_PATH);
        //引入 qrcode类
        Vendor('phpqrcode.phpqrcode');
        //实例化qrcode类
        //$qrcode = new \QRcode();
        //路径
        $pathname = ROOT_PATH.'/public/qr_uploads/';
        if(!is_dir($pathname)) { //若目录不存在则创建之
            mkdir($pathname,0777,true);
        }
        //图片名
        $ad = 'qrcode_' . rand(10000,99999) . '.png';
        //图片保存路径
        $savepath = $pathname.$ad;
        $errorCorrectionLevel =intval($level) ;//容错级别
        $matrixPointSize = intval($size);//生成图片大小
        $url = $url?$url:'http://www.ztwlxx.net?a=2';
        //return $url;
        \QRcode::png($url, $savepath, $errorCorrectionLevel, $matrixPointSize, 2);
        //return '/yangwenyu/public/qr_uploads/'.$ad;
        return '/public/qr_uploads/'.$ad;
        //return json(['code'=>400,'msg'=>$return]);
        //dd($png);
        //$res = file_put_contents($savepath,$png);
//        if($res !== false){
//            return $savepath;
//        }
    }

    //检测是否支付成功

    public function check()
    {
        if(Request::instance()->isAjax()){
            $status = db(self::$table_goushui_order)->where(['app_id'=>$this->app_id,'order_id'=>input('order_id')])->value('status');
            if($status == 1){
                return json(['code'=>200]);
            }else{
                return json(['code'=>400]);
            }
        }
    }

    //三天后自动确认收货

    public function autoreceive()
    {
        if(Request::instance()->isAjax()) {
            $where = [
                'app_id' => $this->app_id,
                'deputy_id' => $this->id,
                'status' => 2,
            ];

            $late = 24 * 60 * 60 * 3;

            $order = db(self::$table_goushui_order)
                ->where($where)
                ->select();
            if (!empty($order)) {
                //选出满足自动收获条件的数据
                foreach ($order as $k => $v) {
                    if (time() > ($v['shouhuotime'] + $late)) {
                        $this->shouhuo($v['order_id']);
                    }
                }
            }
        }
    }

    //shuohuo
    private function shouhuo($id)
    {
        $where = [
            'app_id'    => $this->app_id,
            'deputy_id' => $this->id,
            'order_id'  => $id
        ];
        //查找订单
        $order = db(self::$table_goushui_order)->where($where)->find();
        if($order) {
            Db::startTrans();
            try {
                db(self::$table_goushui_order)->where($where)->update(['status' => 3, 'updated_at' => time()]);
                //查找是否已购买过该水，如果购买过 只增加水余量
                //$count = db()
                $insert = [
                    'app_id'     => $this->app_id,
                    'shui_id'    => $order['shui_id'],
                    'name'       => $order['name'],
                    'stock'      => $order['stock'],
                    'price'      => $order['price'],
                    'created_at' => time(),
                    //'status'     => 1,
                ];
                if($order['store_id'] == 0) {
                    //根据代理ID 获取代理等级
                    $level = db(self::$table_deputy)->where(['app_id' => $this->app_id, 'deputy_id' => $order['deputy_id']])->value('level');
                    if ($level == 1) {
                        //增加数据到goushui表
                        $insert['type'] = 1; //分公司
                    }else {
                        $insert['type'] = 2; //普通代理
                    }
                    $insert['type_id'] = $order['deputy_id'];
                } else {
                    $insert['type'] = 3;  //商户
                    $insert['type_id'] = $order['store_id'];
                }
                db(self::$table_goushui)->insertGetId($insert);
                Db::commit();
            } catch (Exception $exception) {
                Db::rollback();
            }
        }

    }
    //删除购水订单
    public function delgoushuiorder()
    {
        //dd(222);
        if(Request::instance()->isAjax()) {
            $where = [
                'app_id'    => $this->app_id,
                'deputy_id' => $this->id,
                'order_id'  => $this->parme('order_id')
            ];
            $res = db(self::$table_goushui_order)->where($where)->delete();
            if ($res) {
                return json(['code'=>200,'msg'=>'操作成功']);
            } else {
                return json(['code'=>400,'msg'=>'操作失败']);
            }
        }
    }

    //确认收获
    public function receive()
    {
        //dd(222);
        if(Request::instance()->isAjax()) {
            $where = [
                'app_id'    => $this->app_id,
                'deputy_id' => $this->id,
                'order_id'  => $this->parme('order_id')
            ];
            //查找订单
            $order = db(self::$table_goushui_order)->where($where)->find();
            if($order) {
                Db::startTrans();
                try {
                    db(self::$table_goushui_order)->where($where)->update(['status' => 3, 'updated_at' => time()]);
                    $insert = [
                        'app_id'     => $this->app_id,
                        'shui_id'    => $order['shui_id'],
                        'name'       => $order['name'],
                        'stock'      => $order['stock'],
                        'price'      => $order['price'],
                        'created_at' => time(),
                        //'status'     => 1,
                    ];
                    if($order['store_id'] == 0) {
                        //根据代理ID 获取代理等级
                        $level = db(self::$table_deputy)->where(['app_id' => $this->app_id, 'deputy_id' => $order['deputy_id']])->value('level');
                        if ($level == 1) {
                            //增加数据到goushui表
                            $insert['type'] = 1; //分公司
                        }else {
                            $insert['type'] = 2; //普通代理
                        }
                        $insert['type_id'] = $order['deputy_id'];
                    } else {
                        $insert['type'] = 3;  //商户
                        $insert['type_id'] = $order['store_id'];
                    }
                    db(self::$table_goushui)->insertGetId($insert);
                    Db::commit();
                    return json(['code'=>200,'msg'=>'操作成功']);
                } catch (Exception $exception) {
                    Db::rollback();
                    return json(['code'=>400,'msg'=>'操作失败']);
                }
            }
//            $res = db(self::$table_goushui_order)->where($where)->update(['status'=>3,'updated_at'=>time()]);
//            if ($res) {
//                return json(['code'=>200,'msg'=>'操作成功']);
//            } else {
//                return json(['code'=>400,'msg'=>'操作失败']);
//            }
        }
    }

    //生成签名测试用

    //作用：生成签名
    public function getSign() {
        $json = '{"appid":"wx762bbeb8757c18b7","attach":"18","bank_type":"CFT","cash_fee":"1","device_info":"WEB","fee_type":"CNY","is_subscribe":"Y","mch_id":"1514213421","nonce_str":"ma8jmi5kw671gghz6tepi2j3gmwlox1w","openid":"os-5N1ZgTUrkGgasKpmQHpFc5R5E","out_trade_no":"154458270292671","result_code":"SUCCESS","return_code":"SUCCESS","time_end":"20181212104516","total_fee":"1","trade_type":"NATIVE","transaction_id":"4200000235201812123971197823"}';
        $Obj = json_decode($json,true);
        $key = 'c56d0e9a7ccec67b4ea131655038d604';
        foreach ($Obj as $k => $v) {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEY
        $String = $String . "&key=" . $key;
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

    /******************************************已购买到货的水**********************************************************/
    public function myshui()
    {
        //查找当前代理级别
        $level = db(self::$table_deputy)
            ->where(['app_id'=>$this->app_id,'deputy_id'=>$this->id])
            ->value('level');
        //根据级别获取总平台配置
        if($level == 1){ //分公司卖给普通代理的价格区间
            $pricespace = db(self::$table_shui)->where('app_id',$this->app_id)->value('betweenprice');
        }else{ //普通代理卖给商户的价格区间
            $pricespace = db(self::$table_shui)->where('app_id',$this->app_id)->value('storeprice');
        }
        if(!empty($pricespace)){
            $pricespace = explode(',',$pricespace);
        }
        $where = [
            'app_id'    => $this->app_id,
            'deputy_id' => $this->id,
            'status'    => 3,
        ];

        $data = db(self::$table_goushui)
            ->where($where)
            ->select();
        return view('myshui',[
            'data'  => $data,
            'pricespace' => $pricespace,
        ]);

    }
}













