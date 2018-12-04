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

class Store extends Common
{
    public $deputy_id;
    public $app_id;
    public $store_id;
    public static $msg = [];
    public static $table_shop = 'shop';
    public $time;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->app_id = Session::get('app_id');
        $this->deputy_id = Session::get('deputy_id');
        $this->store_id = Session::get('store_id');
        self::$msg = array_merge(DataAnalysis::$msg,self::$msg);
        $this->time = time();
    }

    public function index()
    {
        return view('store/index');
    }

    //图片上传

    public function upload($img = '',$validate = ['size'=>15678,'ext'=>'jpg,png,gif'])
    {
        //dd($img);
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

    public function setshop()
    {
        //标识符区分添加修改
        //var_dump($_FILES);
        $return = $this->upload('fileList');
        if($return == false){
            //$this->error('上传图片失败');
        }
        dd($return);
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
                'pic_arr'       => $return,//商品图片
                'position'      => $this->parme('position'),//店铺地址
                'longitude'     => $this->parme('longitude'),//经度
                'latitude'      => $this->parme('latitude'),//纬度
                'kefu_phone'    => $this->parme('kefu_phone'),//客服电话
                'erweima'       => $this->upload('erweima'),//二维码
                'status'        => 1,//状态 //0关店 1营业
                //'shelves'       => 'true',//上下架
                'updated_at'    => $this->time,
            ];
            if($flag == 'add'){
                $insert['deputy_id'] = $this->deputy_id; //代理ID
                $insert['app_id']    = $this->app_id; //哪个平台
                $insert['shop_id']    = $this->shop_id; //哪个商户
                $insert['created_at']= $this->time;
                $res = db(self::$table_shop)->insertGetId($insert);
            }else{
                $res = db(self::$table_store)->where($where)->update($insert);
            }
            if($res){
                return $this->redirect('index/storemanage');
            }else{
                $this->error('操作失败');
            }
        }else{
            if($flag == 'add'){
                return view('store/addshop');
            }else if($flag == 'update'){
                $data = db(self::$table_store)
                    ->where($where)
                    ->find();
                return view('updatestore',[
                    'data'   => $data
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
                return view('listshop',[
                    'data'    => $data,
                    'status'  => (int)$this->parme('status','1'),
                ]);
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
}