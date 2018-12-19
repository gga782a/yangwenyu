<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/12/1
 * Time: 3:26 PM
 */

namespace app\admin\controller;


use app\DataAnalysis;
use think\Request;
use think\Session;
use think\Db;
use think\Exception;
use think\Validate;
use app\wechat\controller\Saomapay;

class Store extends Common
{
    public $deputy_id;
    public $app_id;
    public $store_id;
    public static $msg = [];
    public static $table_shop = 'shop';
    public static $table_store = 'store';
    public static $table_deputy = 'deputy';
    public static $table_shui = 'shui';
    public static $table_goushui = 'goushui';
    public static $table_goushui_order = 'goushui_order';
    public static $table_pay_setting = 'pay_setting';
    public static $table_integral = 'integral'; //积分
    public static $table_integral_order = 'integral_order'; //积分订单
    public $time;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->app_id = Session::get('app_id');
        $this->deputy_id = Session::get('deputy_id');
        $this->store_id = Session::get('user_id');
        self::$msg = array_merge(DataAnalysis::$msg,self::$msg);
        $this->time = time();
    }

    public function index()
    {
        return view('store/index');
    }

    //图片上传

    public function upload($img = '',$validate = ['size'=>10240000,'ext'=>'jpg,png,gif'])
    {
        if ($img == '') {
            return false;
        } else {
            $pic_arr = '';
            // 获取表单上传文件 例如上传了001.jpg
            $files = request()->file($img);
            //dd($files);
            //判断是不是多图上传
            $dir = ROOT_PATH . 'public' . DS . 'uploads' . DS;
            $date = date('Ymd', time()) . '/';
            $path = $dir . $date;
            if (!file_exists($path)) {
                mkdir($path, 0775, true);
            }
            if (empty($files)) {
                return false;
            } else {
                if (is_array($files)) {
                    foreach ($files as $file) {
                        // 移动到框架应用根目录/public/uploads/ 目录下
                        $info = $file->validate($validate)->rule('uniqid')->move($path);
                        if ($info) {
                            // 成功上传后 获取上传信息
                            // 输出 jpg
                            //echo $info->getExtension();
                            // 输出 42a79759f284b767dfcb2a0197904287.jpg
                            //echo $info->getFilename();
                            $pic_arr .= $info->getFilename() . ',';
                        } else {
                            // 上传失败获取错误信息
                            continue;
                        }
                    }
                } else {
                    $info = $files->validate($validate)->rule('uniqid')->move($path);
                    if ($info) {
                        $pic_arr = $info->getFilename();
                    } else {
                        // 上传失败获取错误信息
                        return false;
                    }
                }
                return trim($pic_arr);
            }
        }
    }

    public function uploadone($validate = ['size'=>10240000,'ext'=>'jpg,png,gif']){
        $files = request()->file('fileList');
        //dd($files);
        //判断是不是多图上传
        $dir = ROOT_PATH . 'public' . DS . 'uploads' . DS;
        //dd($dir);
        $date = date('Ymd', time()) . '/';
        $path = $dir . $date;
        //dd($path);
        if (!file_exists($path)) {
            mkdir($path, 0775, true);
        }
        if (empty($files)) {
            return false;
        } else {
            // 移动到框架应用根目录/public/uploads/ 目录下
            $info = $files->validate($validate)->rule('uniqid')->move($path);
            if ($info) {
                $pic_arr = '/public/uploads/'.$date.$info->getFilename();
                return trim($pic_arr);
            } else {
                // 上传失败获取错误信息
                return false;
            }
        }

    }

    public function setshop()
    {
        //标识符区分添加修改
        //var_dump($_FILES);
//        $return = $this->upload('fileList');
//        if($return == false){
//            //$this->error('上传图片失败');
//        }
        //dd($return);
        $flag = input('flag');
        $where = [
            'app_id'    => $this->app_id,
            'deputy_id' => $this->deputy_id,
            'store_id'  => $this->store_id,
            'shop_id'   => $this->parme('shop_id'),
        ];
        if(Request::instance()->isPost()){
            $insert = [
                'sort'          => $this->parme('sort'), //排序
                'shop_name'     => $this->parme('shop_name'), // 名称
                'pic_arr'       => trim($this->parme('pics')),//商品图片
                'position'      => $this->parme('position'),//店铺地址
                'address'       => $this->parme('address'),//详细地址
                'longitude'     => $this->parme('longitude'),//经度
                'latitude'      => $this->parme('latitude'),//纬度
                'kefu_phone'    => $this->parme('kefu_phone'),//客服电话
                //'erweima'       => $this->upload('erweima'),//二维码
                'status'        => 1,//状态 //0关店 1营业
                //'shelves'       => 'true',//上下架
                'updated_at'    => $this->time,
            ];
            if($flag == 'add'){
                $insert['deputy_id'] = $this->deputy_id; //代理ID
                $insert['app_id']    = $this->app_id; //哪个平台
                $insert['store_id']    = $this->store_id; //哪个商户
                $insert['created_at']= $this->time;
                //dd($insert);
                $res = db(self::$table_shop)->insertGetId($insert);
            }else{
                //删除原图片
                $pic_arr = trim($this->parme('pic_arr'),',');
                if(!empty($pic_arr)){
                    $pic_arr = explode(',',$pic_arr);
                    //删除接口
                    $this->delpics($pic_arr);
                }
                $res = db(self::$table_shop)->where($where)->update($insert);
            }
            if($res){
                return $this->redirect('store/setshop');
            }else{
                $this->error('操作失败');
            }
        }else{
            if($flag == 'add'){
                return view('addshop');
            }else if($flag == 'update'){
                $data = db(self::$table_shop)
                    ->where($where)
                    ->find();
                if($data) {
                    $pics = trim($data['pic_arr'], ',');
                    if (!empty($pics)) {
                        $pics = explode(',', $pics);
                    } else {
                        $pics  = '';
                    }
                }
                return view('updateshop',[
                    'data'   => $data,
                    'pics'   => $pics,
                ]);
            }else{
                $wherelist = [
                    'app_id'    => $this->app_id,
                    'deputy_id' => $this->deputy_id,
                    'store_id'  => $this->store_id,
                ];
                if($this->parme('status')){
                    $where['status'] = $this->parme('status');  //下架或售罄商品
                }
                $data = db(self::$table_shop)
                    ->where($wherelist)
                    ->page(input('page',1),input('pageshow',15))
                    ->select();
                if($data){
                    foreach ($data as $k=>$v){
                        $pics = trim($v['pic_arr'],',');
                        if(!empty($pics)){
                            $pics = explode(',',$pics);
                            $logo = $pics[0];
                        }else{
                            $logo = '暂无图片';
                        }
                        $data[$k]['logo'] = $logo;
                        if(empty($v['erweima'])){
                            //var_dump(11);
                            $erweima = '暂无二维码';
                        }else{
                            $erweima = $v['erweima'];
                        }
                        $data[$k]['erweima'] = $erweima;
                    }
                }
                //dd($data);
                return view('listshop',[
                    'data'    => $data,
                    'status'  => (int)$this->parme('status','1'),
                ]);
            }
        }
    }
    //删除图片从本地
    function delpics($arr){
        $path = trim(ROOT_PATH,'/');
        foreach($arr as $k=>$v){
            //dd($path.$v);
            if(file_exists($path.$v)){
                //dd(11);
                unlink($path.$v);
            }
        }
    }

    //删除商户

    public function delshop()
    {
        //dd(222);
        if(Request::instance()->isAjax()) {
            $where = [
                'app_id'    => $this->app_id,
                'deputy_id' => $this->id,
                'store_id'  => $this->store_id,
                'shop_id'   => $this->parme('shop_id'),
            ];
            $res = db(self::$table_shop)->where($where)->delete();
            if ($res) {
                return json(['code'=>200,'msg'=>'操作成功']);
            } else {
                return json(['code'=>400,'msg'=>'操作失败']);
            }
        }
    }

    //设置优惠活动
    public function setactivity()
    {
        //标识符区分添加修改
        $flag = input('flag');
        $where = [
            'app_id'    => $this->id,
            'deputy_id' => $this->parme('deputy_id'),
            'store_id'  => $this->parme('store_id'),
            'active_id' => $this->parme('active_id')
        ];
        if(Request::instance()->isPost()){
            $return = $this->upload('image');
//            if($return == false){
//                $this->error('上传图片失败');
//            }
            $insert = [
                'sort'          => $this->parme('sort'), //排序
                'name'          => $this->parme('name'), // 名称
                'active_desc'   => $this->parme('active_desc'),//活动简介
                'position'      => $this->parme('position'),//活动地点
                'longitude'     => $this->parme('longitude'),//经度
                'latitude'      => $this->parme('latitude'),//纬度
                'kefu_phone'    => $this->parme('kefu_phone'),//活动方电话
                'active_start_time' => $this->parme('active_start_time'),//活动开始时间
                'active_end_time'=> $this->parme('active_end_time'),//活动结束时间
                //'erweima'       => $this,//二维码
                'status'        => 1,//状态 //0关闭 1开启
                //'shelves'       => 'true',//上下架
                'updated_at'    => $this->time,
            ];
            if($flag == 'add'){
                $insert['deputy_id'] = $this->parme('deputy_id'); //代理ID
                $insert['store_id']  = $this->parme('store_id'); //商家ID
                $insert['app_id']    = $this->id; //哪个平台
                $insert['created_at']= $this->time;
                $res = db(self::$table_activity)->insertGetId($insert);
            }else{
                $res = db(self::$table_activity)->where($where)->update($insert);
            }
            if($res){
                return $this->redirect('index/setactivity');
            }else{
                $this->error('操作失败');
            }
        }else{
            if($flag == 'add'){
                return view('addactivity');
            }else if($flag == 'update'){
                $data = db(self::$table_activity)
                    ->where($where)
                    ->find();
                return view('updateactivity',[
                    'data'   => $data
                ]);
            }else{
                $wherelist = [
                    'app_id'    => $this->id,
                    'deputy_id' => $this->parme('deputy_id'),
                    'store_id'  => $this->parme('store_id'),
                ];
                if($this->parme('status')){
                    $where['status'] = $this->parme('status');  //下架或售罄商品
                }
                $data = db(self::$table_activity)
                    ->where($wherelist)
                    ->page(input('page',1),input('pageshow',15))
                    ->select();
                return view('listactivity',[
                    'data'    => $data,
                    'status'  => (int)$this->parme('status','0'),
                ]);
            }
        }
    }

    //充值管理

    public function setchongzhi()
    {
        //标识符区分添加修改
        $flag = input('flag');
        $where = [
            'app_id'    => $this->id,
            'deputy_id' => $this->parme('deputy_id'),
            'store_id'  => $this->parme('store_id'),
            'id'        => $this->parme('id')
        ];
        if(Request::instance()->isPost()){
            $insert = [
                'sort'          => $this->parme('sort'), //排序
                'needmoney'     => floatval($this->parme('needmoney')), // 金钱
                //'erweima'       => $this,//二维码
                'status'        => 1,//状态 //0关闭 1开启
                //'shelves'       => 'true',//上下架
                'updated_at'    => $this->time,
            ];
            if($flag == 'add'){
                $insert['deputy_id'] = $this->parme('deputy_id'); //代理ID
                $insert['store_id']  = $this->parme('store_id'); //商家ID
                $insert['app_id']    = $this->id; //哪个平台
                $insert['created_at']= $this->time;
                $res = db(self::$table_chongzhi)->insertGetId($insert);
            }else{
                $res = db(self::$table_chongzhi)->where($where)->update($insert);
            }
            if($res){
                return $this->redirect('index/setchongzhi');
            }else{
                $this->error('操作失败');
            }
        }else{
            if($flag == 'add'){
                return view('addchongzhi');
            }else if($flag == 'update'){
                $data = db(self::$table_chongzhi)
                    ->where($where)
                    ->find();
                return view('updatechongzhi',[
                    'data'   => $data
                ]);
            }else{
                $wherelist = [
                    'app_id'    => $this->id,
                    'deputy_id' => $this->parme('deputy_id'),
                    'store_id'  => $this->parme('store_id'),
                ];
                if($this->parme('status')){
                    $where['status'] = $this->parme('status');  //下架或售罄商品
                }
                $data = db(self::$table_chongzhi)
                    ->where($wherelist)
                    ->page(input('page',1),input('pageshow',15))
                    ->select();
                return view('listchongzhi',[
                    'data'    => $data,
                    'status'  => (int)$this->parme('status','0'),
                ]);
            }
        }
    }

    //生成门店二维码

    public function qr_code($url='',$level=3,$size=7)
    {
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
        $url = 'http://www.ztwlxx.net?a=2';
        //return $url;
        \QRcode::png($url, $savepath, $errorCorrectionLevel, $matrixPointSize, 2);
        return '/yangwenyu/public/qr_uploads/'.$ad;
        //dd($png);
        //$res = file_put_contents($savepath,$png);
//        if($res !== false){
//            return $savepath;
//        }
    }

    public function url()
    {

        $img = $this->qr_code();
        //dd($img);
        echo "<img src = '$img' />";
    }

    public function ditu()
    {
        return view('store/ditu');
    }

    public function img()
    {
        return view('store/img');
    }

    /****************************************************服务订购************************************************************/
    //购水订单列表
    public function serviceorder()
    {
        $where = [
            'app_id'    => $this->app_id,
            'deputy_id' => $this->deputy_id,
            'store_id'  => $this->store_id,
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
                $data[$k]['from'] = db(self::$table_deputy)
                    ->where(['app_id'=>$this->app_id,'deputy_id'=>$v['deputy_id']])
                    ->value('deputy_name');
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
            'app_id' => $this->app_id,
            'type_id' => $this->deputy_id,
            'type' => 2
        ];
//        if(Request::instance()->isPost()){
//
//        }else{
        //上级代理
        $list = db(self::$table_goushui)
            ->where($where)
            ->page(input('page', 1), input('pageshow', 15))
            ->select();

        return view('goushuilist', [
            'data'      => $list,
            'deputy_id' => $this->deputy_id,
            'app_id'    => $this->app_id,
            'store_id'  => $this->store_id,
            'parentid'  => 0
        ]);
    }

    //}

    //生成购水页面

    public function goushuiorder()
    {
        $params = input();
        return view("goushuiorder",[
            'params' => $params
        ]);
    }

    //生成购水订单

    public function creategoushuiorder()
    {
        if(Request::instance()->isAjax()){
            $insert = [
                'app_id'        => input('app_id'),
                'deputy_id'     => input('deputy_id'),
                'shui_id'       => input('shui_id'),
                'store_id'      => input('store_id'),
                'parentid'      => (int)input('parentid','0'),
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
                    return $this->s_qr_code($code_url);
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

    //生成二维码
    public function s_qr_code($url='',$level=3,$size=7)
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
                'deputy_id' => $this->deputy_id,
                'store_id' => $this->store_id,
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
            'deputy_id' => $this->deputy_id,
            'store_id'  => $this->store_id,
            'order_id'  => $id
        ];
        //查找订单
        $order = db(self::$table_goushui_order)->where($where)->find();
        if($order) {
            Db::startTrans();
            try {
                db(self::$table_goushui_order)->where($where)->update(['status' => 3, 'updated_at' => time()]);
                $wheres = [
                    'app_id'    => $this->app_id,
                    'type_id'   => $this->store_id,
                    'shui_id'   => $order['shui_id'],
                    'type'      => 3,
                ];
                $insert = [
                    'app_id'     => $this->app_id,
                    'shui_id'    => $order['shui_id'],
                    'name'       => $order['name'],
                    'stock'      => $order['stock'],
                    'totalstock' => $order['stock'],
                    'price'      => $order['price'],
                    'created_at' => time(),
                    'type'       => 3,
                    'type_id'    => $this->store_id,
                    //'status'     => 1,
                ];
                //查找是否已购买过该水，如果购买过 只增加水余量
                $count = db(self::$table_goushui)
                    ->where($wheres)
                    ->count();
                if($count>0){
                    db(self::$table_goushui)->where($wheres)->setInc('stock',$order['stock']);
                    db(self::$table_goushui)->where($wheres)->setInc('totalstock',$order['stock']);
                }else{
                    db(self::$table_goushui)->insertGetId($insert);
                }
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
                'deputy_id' => $this->deputy_id,
                'store_id'  => $this->store_id,
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
                'deputy_id' => $this->deputy_id,
                'store_id'  => $this->store_id,
                'order_id'  => $this->parme('order_id')
            ];
            //查找订单
            $order = db(self::$table_goushui_order)->where($where)->find();
            if($order) {
                Db::startTrans();
                try {
                    db(self::$table_goushui_order)->where($where)->update(['status' => 3, 'updated_at' => time()]);
                    $wheres = [
                        'app_id'    => $this->app_id,
                        'type_id'   => $this->store_id,
                        'shui_id'   => $order['shui_id'],
                        'type'      => 3,
                    ];
                    $insert = [
                        'app_id'     => $this->app_id,
                        'shui_id'    => $order['shui_id'],
                        'name'       => $order['name'],
                        'totalstock' => $order['stock'],
                        'stock'      => $order['stock'],
                        'price'      => $order['price'],
                        'created_at' => time(),
                        'type'       => 3,
                        'type_id'    => $order['store_id'],
                        //'status'     => 1,
                    ];
                    //查找是否已购买过该水，如果购买过 只增加水余量
                    $count = db(self::$table_goushui)
                        ->where($wheres)
                        ->count();
                    if($count>0){
                        db(self::$table_goushui)->where($wheres)->setInc('stock',$order['stock']);
                        db(self::$table_goushui)->where($wheres)->setInc('totalstock',$order['stock']);
                    }else{
                        db(self::$table_goushui)->insertGetId($insert);
                    }
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

    /******************************************已购买到货的水**********************************************************/
    public function myshui()
    {
        $where = [
            'app_id'    => $this->app_id,
            'type_id'   => $this->store_id,
            'type'      => 3,
        ];
        $data = db(self::$table_goushui)
            ->where($where)
            ->page(input('page',1),input('pageshow',15))
            ->select();
//        if(!empty($data)){
//            foreach ($data as $k=>$v){
//                //根据级别获取总平台配置
//                if($level == 1){ //分公司卖给普通代理的价格区间
//                    $pricespace = db(self::$table_shui)->where(['app_id'=>$this->app_id,'shui_id'=>$v['shui_id']])->value('betweenprice');
//                }else{ //普通代理卖给商户的价格区间
//                    $pricespace = db(self::$table_shui)->where(['app_id'=>$this->app_id,'shui_id'=>$v['shui_id']])->value('storeprice');
//                }
//                if(!empty($pricespace)){
//                    $pricespace = explode(',',$pricespace);
//                    $data[$k]['pricelow'] = $pricespace[0];
//                    $data[$k]['priceup']  = $pricespace[1];
//                }else{
//                    $data[$k]['pricelow'] = 0;
//                    $data[$k]['priceup']  = 0;
//                }
//            }
//        }
        //dd($data);
        return view('myshui',[
            'data'  => $data,
        ]);
    }

//    //设置水价
//
//    public function setprice()
//    {
//        if(Request::instance()->isAjax()) {
//            $where = [
//                'app_id' => $this->app_id,
//                'goushui_id' => input('goushui_id'),
//            ];
//            $price = floatval(input('price'));
//            $pricelow = floatval(input('pricelow'));
//            $priceup = floatval(input('priceup'));
//            if ($price >= $pricelow && $price <= $priceup) {
//                $res = db(self::$table_goushui)
//                    ->where($where)
//                    ->update(['price' => $price, 'updated_at' => time()]);
//                if ($res !== false) {
//                    return json(array('code' => 200, 'msg' => '操作成功'));
//                } else {
//                    return json(array('code' => 400, 'msg' => '操作失败'));
//                }
//            } else {
//                return json(array('code' => 400, 'msg' => '价格'.$price.'应在'.$pricelow.'到'.$priceup.'之间'));
//            }
//        }
//    }

    //购水详情

//    public function goushuidetail()
//    {
//        $order = [];
////        //代理在分公司购买详情
////        $returndeputy = $this->goushuideputydetail();
////        if($returndeputy!==false){
////            foreach ($returndeputy as $k1=>$v1){
////                $order[$k1] = $v1;
////            }
////        }
//        $count = count($order);
//        //商家在普通代理购买详情
//        $returnstore = $this->goushuistoredetail();
//        if($returnstore!==false){
//            foreach ($returnstore as $k2=>$v2){
//                $order[$count+$k2] = $v2;
//            }
//        }
//        return view('goushuidetail',[
//            'data' => $order,
//        ]);
//    }

//    //代理在分公司购买详情
//
//    public function goushuideputydetail()
//    {
//        //普通代理在分公司购买
//        $where = [
//            'app_id'  => $this->app_id,
//            'shui_id' => input('shui_id'),
//            'parentid'=> $this->id,
//            'store_id'=> 0,
//        ];
//        $order = db(self::$table_goushui_order)
//            ->where($where)
//            ->page(input('page',1),input('pageshow',15))
//            ->select();
//        $statusarr = [
//            '未支付', '支付成功，待发货', '待收货', '订单完成',
//        ];
//        if(!empty($order)){
//            foreach($order as $k=>$v){
//                $deputyname = db(self::$table_deputy)
//                    ->where(['app_id'=>$this->app_id,'deputy_id'=>$v['deputy_id']])
//                    ->value('deputy_name');
//
//                $order[$k]['deputyname'] = $deputyname;
//                $order[$k]['storename'] = '';
//                $order[$k]['statusname'] = $statusarr[$v['status']];
//            }
//            return $order;
//        }else{
//            return false;
//        }
//    }

    //购水详情

//    public function goushuistoredetail()
//    {
//        //普通代理在分公司购买
//        $where = [
//            'app_id'  => $this->app_id,
//            'shui_id' => input('shui_id'),
//            'deputy_id'=> $this->deputy_id,
//            'parentid'=> 0,
//            'store_id'=> $this->store_id,
//        ];
//        $order = db(self::$table_goushui_order)
//            ->where($where)
//            ->page(input('page',1),input('pageshow',15))
//            ->select();
//        $statusarr = [
//            '未支付', '支付成功，待发货', '待收货', '订单完成',
//        ];
//        if(!empty($order)){
//            foreach($order as $k=>$v){
//                $storename = db(self::$table_store)
//                    ->where(['app_id'=>$this->app_id,'deputy_id'=>$this->deputy_id,'store_id'=>$v['store_id']])
//                    ->value('store_name');
//                //$order[$k]['deputyname'] = '';
//                $order[$k]['storename']  = $storename;
//                $order[$k]['statusname'] = $statusarr[$v['status']];
//            }
//            return $order;
//        }else{
//            return false;
//        }
//    }
    /************************************************积分购买************************************************************/
    //展示订单记录表
    public function integrallist()
    {
        //dd(111);
        $where = [
            'app_id'   => $this->app_id,
            'type_id'  => $this->store_id,
            'type'     => '1',
        ];

        $data = db(self::$table_integral_order)
            ->where($where)
            ->page(input('page',1),input('pageshow',15))
            ->select();
        if(count($data)>0){
            foreach ($data as $k=>$v){
                $data[$k]['statusname'] = $v['status'] == 0?'未支付':'已支付';
            }
        }
        //dd($data);
        return view('integrallist',[
            'data'  => $data,
        ]);
    }
    //展示可购买积分
    public function showintegrallist()
    {
        $where = [
            'app_id' => $this->app_id,
            'status' => 1,
        ];

        $data = db(self::$table_integral)
            ->where($where)
            ->select();
        return view('showintegrallist',[
            'data'  => $data,
        ]);
    }
    //创建积分订单

    public function createintegralorder()
    {
        if(Request::instance()->isAjax()){
            $insert = [
                'app_id'        => $this->app_id,
                'type'          => 1,
                'type_id'       => $this->store_id,
                'integral_id'   => input('integral_id'),
                'jifen'         => (int)input('jifen'),
                'status'        => 0,
                'needpay'       => floatval(input('needpay')),
                'order_num'     => time().rand(000000,999999),
                'created_at'    => time(),
            ];
            $id = db(self::$table_integral_order)->insertGetId($insert);
            if($id){
                //跳转到扫码支付页
                //return json(['code'=>400,'msg'=>$id]);
                $return = $this->saomapayintegral($id);
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

    public function saomapayintegral($id='')
    {
        $where = [
            'app_id'   => $this->app_id,
            'type_id'  => $this->store_id,
            'type'     => '1',
            'order_id' => $id
        ];
        //查询订单
        $order = db(self::$table_integral_order)
            ->where($where)
            ->field('order_num,needpay,integral_id')
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
                $notify_url = "http://www.yilingjiu.cn/wechat/Saomapay/notifyintegral";
                //$order['needpay']
                $somapay = new Saomapay($order['integral_id'],$openid='', $paysetting['mch_id'], $paysetting['mch_key'],$order['order_num'],'购买积分',0.01,$id,$notify_url);
                $return  = $somapay->pay();
                //dd($return);
                if($return['return_code'] == 'SUCCESS'&&$return['return_msg'] == 'OK'){
                    //return json(['code'=>400,'msg'=>444]);
                    $code_url = $return['code_url'];
                    //生成二维码
                    return $this->s_qr_code($code_url);
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

    //检测是否支付成功

    public function checkintegral()
    {
        if(Request::instance()->isAjax()){
            $status = db(self::$table_integral_order)->where(['app_id'=>$this->app_id,'order_id'=>input('order_id')])->value('status');
            if($status == 1){
                return json(['code'=>200]);
            }else{
                return json(['code'=>400]);
            }
        }
    }

    //删除购买积分订单
    public function delintegralorder()
    {
        //dd(222);
        if(Request::instance()->isAjax()) {
            $where = [
                'app_id'    => $this->app_id,
                'type'      => '1',
                'type_id'   => $this->store_id,
                'order_id'  => $this->parme('order_id')
            ];
            $res = db(self::$table_integral_order)->where($where)->delete();
            if ($res) {
                return json(['code'=>200,'msg'=>'操作成功']);
            } else {
                return json(['code'=>400,'msg'=>'操作失败']);
            }
        }
    }

    //超时不支付自动删除订单

    public function autodel()
    {
        if(Request::instance()->isAjax()) {
            $where = [
                'app_id'    => $this->app_id,
                'type'      => '1',
                'type_id'   => $this->store_id,
                'status'    => 0,
            ];

            $late = 7200;

            $order = db(self::$table_integral_order)
                ->where($where)
                ->select();
            if (!empty($order)) {
                //选出满足自动收获条件的数据
                foreach ($order as $k => $v) {
                    if (time() > ($v['created_at'] + $late)) {
                        $this->delorder($v['order_id']);
                    }
                }
            }
        }
    }

    //shuohuo
    private function delorder($id)
    {
        $where = [
            'app_id'    => $this->app_id,
            'type'      => '1',
            'type_id'   => $this->store_id,
            'status'    => 0,
            'order_id'  => $id
        ];
        db(self::$table_integral_order)->where($where)->delete();
    }

    /************************************************资产总揽************************************************************/

    public function assetprofile()
    {
        //总支出
        //买水支出
        $shuipaymoney = 0;
        $shuipay = $this->shuipay();
        if($shuipay!==false){
            $shuipaymoney = $shuipay['money'];
        }
        dd($shuipaymoney);
        //买积分支出
        return view('assetprofile');
    }
    //买水支出
    public function shuipay()
    {
        $where = [
            'app_id'    => $this->app_id,
            'deputy_id' => $this->deputy_id,
            'parentid'  => 0,
            'store_id'  => $this->store_id,
            'status'    => ['in',[1,2,3]],
        ];
        $shuipay = db(self::$table_goushui_order)
            ->where($where)
            ->page(input('page',1),input('pageshow',15))
            ->select();
        $shuipaymoney = db(self::$table_goushui_order)
            ->where($where)
            ->sum('needpay');
        if(!empty($shuipay)){
            return array('data'=>$shuipay,'money'=>$shuipaymoney);
        }else{
            return false;
        }

    }
    /************************************************订单总揽************************************************************/
}












