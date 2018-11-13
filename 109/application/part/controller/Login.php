<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/10/11
 * Time: 上午10:51
 */
namespace app\part\controller;
use app\DataAnalysis;
use app\part\Controller;
use think\Validate;

class Login extends Controller
{
    //导师
    public static $table_daoshi = 'daoshi';
    public static $primarykey_daoshi = 'id';
    public $user_type_daoshi = 'teacher';
    //admin
    public static $table_admin = 'admin';
    public static $primarykey_admin = 'id';
    public $user_type_admin = 'admin';
    //log
    public static $table_log = 'log';
    public $time;
    public static $msg = [];

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->time = time();
        self::$msg = array_merge(DataAnalysis::$msg,self::$msg);
    }

    public function index()
    {
        dump(111);
    }
    //登陆
    public function checklogin()
    {
        $rule = [
            'username' => 'require',
            'pwd'      => 'require',
        ];

        $field = [
            'username' => '账号',
            'pwd'      => '密码',
        ];
        $validate = new Validate($rule,self::$msg,$field);
        if(!$validate->check($this->parme)){
            $this->abnormal($this->ValitorError,$validate->getError());
        }else{
            $where_admin = array(
                'name'          => $this->parme('username'),
                'password'      => md5(md5($this->parme('pwd'))),
            );
            $where_daoshi = array(
                'username'      => $this->parme('username'),
                'password'      => md5(md5($this->parme('pwd'))),
            );
            //判断超级管理员admin是否登陆
            $admin = db(self::$table_admin)->where($where_admin)->find();
            $url = $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
            if(count($admin)>0){
                $admin['token']         =   $this->time.':'.$admin[self::$primarykey_admin];
                //存入日志
                $log = [
                    'table'     =>  self::$table_admin,
                    'name'      =>  '登陆',
                    'before'    =>  '登陆数据',
                    'after'     =>  '登陆数据',
                    'ctime'     =>  $this->time,
                    'uid'       =>  $admin['id'],
                    'data_id'   =>  $admin['id'],
                    'status'    =>  1,
                    'action'    =>  'adminlogin',
                    'ip'        =>  getIP(),
                    'url'       =>  $url,
                    'user_type' =>  $this->user_type_admin,
                ];
                db(self::$table_log)->insertGetId($log);
                $this->datas = $admin;
            }else {
                //判断是否是导师登陆
                $list = db(self::$table_daoshi)->where($where_daoshi)->find();
                if (count($list) > 0) {
                    $list['token']         =   $this->time.':'.$list[self::$primarykey_daoshi];
                    //存入日志
                    $log = [
                        'table'     =>  self::$table_daoshi,
                        'name'      =>  '登陆',
                        'before'    =>  '登陆数据',
                        'after'     =>  '登陆数据',
                        'ctime'     =>  $this->time,
                        'uid'       =>  $list['id'],
                        'data_id'   =>  $list['id'],
                        'status'    =>  1,
                        'action'    =>  'daoshilogin',
                        'ip'        =>  getIP(),
                        'url'       =>  $url,
                        'user_type' =>  $this->user_type_daoshi,
                    ];
                    db(self::$table_log)->insertGetId($log);
                    $this->datas = $list;
                } else {
                    $this->abnormal($this->LoginError, '账号/密码错误');
                }
            }
        }
    }

    //注销 存日志
    public function dologin()
    {
        $rule = [
            'user_type' => 'require',
            'id'        => 'require',
        ];

        $field = [
            'user_type' => '管理员类型',
            'id'        => '操作者id',
        ];
        $validate = new Validate($rule,self::$msg,$field);
        if(!$validate->check($this->parme)){
            $this->abnormal($this->ValitorError,$validate->getError());
        }else{
            //存入日志
            $url = $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
            if($this->parme('user_type') == $this->user_type_admin){
                $table = self::$table_admin;
                $action = 'admindologin';
            }else{
                $table = self::$table_daoshi;
                $action = 'daoshidologin';
            }
            $log = [
                'table'     =>  $table,
                'name'      =>  '注销',
                'before'    =>  '注销数据',
                'after'     =>  '注销数据',
                'ctime'     =>  $this->time,
                'uid'       =>  $this->parme('id'),
                'data_id'   =>  $this->parme('id'),
                'status'    =>  1,
                'action'    =>  $action,
                'ip'        =>  getIP(),
                'url'       =>  $url,
                'user_type' =>  $this->parme('user_type'),
            ];
            db(self::$table_log)->insertGetId($log);
        }
    }


}











