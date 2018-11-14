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
use think\Request;

class Login extends Controller
{

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
        if (Request::instance()->isPost()) {
            $rule = [
                'username' => 'require',
                'pwd' => 'require',
            ];

            $field = [
                'username' => '账号',
                'pwd' => '密码',
            ];
            $validate = new Validate($rule, self::$msg, $field);
            if (!$validate->check($this->parme)) {
                $this->error($validate->getError());
            } else {

            }
        } else {
            return view('part/login');
        }
    }

    public function register(){
        if(Request::instance()->isPost()){
            $rule = [
                'username' => 'require|exists:user,username|alphaDash|min:4|max:18',
                'pwd' => 'require|confirm|alphaNum|min:4|max:18',
            ];
            $field = [
                'username' => '账号',
                'pwd' => '密码',
            ];
            $validate = new Validate($rule, self::$msg, $field);
            if (!$validate->check($this->parme)) {
                $this->error($validate->getError());
            } else {
                $data = [
                    'username' => $this->parme('username'),
                    'pwd'      => md5(sha1($this->parme('pwd')))
                ];
            }
        }else{
            return view('part/register');
        }
    }
}











