<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/12/3
 * Time: 8:41 AM
 */

namespace app\admin\controller;


use app\DataAnalysis;
use think\Request;
use think\Session;
use think\Validate;

class Deputy extends Common
{
    public $id;
    public $app_id;
    public static $table_store = 'store';
    public static $table_deputy = 'deputy';
    public static $table_shui = 'shui';
    public static $table_goushui = 'goushui';
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

    //重置代理密码

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

    //服务订购

    public function serviceorder()
    {
        $where = [
            'app_id'    => $this->app_id,
            'deputy_id' => $this->id,
        ];
//        //查询当前代理等级
//        $level = db(self::$table_store)->where($where)->value('level');
        return view('listservice',[

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
                ]);
            }
        }
    }




}