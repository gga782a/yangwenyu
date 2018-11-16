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
use think\Session;
use think\Url;
use think\Validate;
use think\Request;

class Login extends Controller
{

    public $time;
    public static $msg = [];
    public static $table_user = 'user';


    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->time = time();
        self::$msg = array_merge(DataAnalysis::$msg,self::$msg);
    }

    public function index()
    {
        //Session::set('user_id',null);
        return $this->redirect('admin/index/index');
    }
    //登陆
    public function checklogin()
    {
        $type = input('type');
        if (Request::instance()->isPost()) {
            $rule = [
                'username' => 'require',
                'pwd'      => 'require',
            ];

            $field = [
                'username' => '账号',
                'pwd' => '密码',
            ];
            $validate = new Validate($rule, self::$msg, $field);
            if (!$validate->check($this->parme)) {
                $this->error($validate->getError());
            } else {
                $where = array(
                    'username'   => $this->parme('username'),
                    'type'       => $type,

                );
                $user = db(self::$table_user)
                    ->where($where)
                    ->find();
                if(count($user) >0){
                    if($user['del'] != 0 ){
                        if($user['status'] != 1){
                            if($user['pwd']!==md5(shar1($this->parme('pwd')))){
                                //存入session
                                Session::set('username',$user['username']);
                                Session::set('user_id',$user['user_id']);
                                //重定向到主页
                                return $this->redirect('admin/index/index');
                            }else{
                               $this->error('密码错误');
                            }
                        }else{
                           $this->error('账号被封禁');
                        }
                    }else{
                        $this->error('账号已被注销');
                    }
                }else{
                    $this->error('账号不存在');
                }
            }
        } else {
            return view('part/login');
        }
    }
    // 注册  注册成功跳转到登陆页
    public function register(){
        if(Request::instance()->isPost()){
            //dd($this->parme('username'));
            $rule = [
                'username' => 'require|exists:user,username|chsDash|min:4|max:18',
                'pwd' => 'require|confirm:repwd|alphaNum|min:4|max:18',
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
                    'username'      => $this->parme('username'),
                    'pwd'           => md5(sha1($this->parme('pwd'))),
                    'created_at'    => $this->time,
                    'type'          => $this->parme('type'),//type区分登陆者类型 1app 2deputy 3store
                    'status'        => 1, //1可用 0 禁用
                    'del'           => 0, //1注销 0 可用
                ];
                $id = db(self::$table_user)->insertGetId($data);
                if($id){//重定向到登陆页 传递type参数
                    return $this->redirect('checklogin',array('type'=>$this->parme('type')));
                }else{
                    $this->error('操作失败','register','',1);
                }
            }
        }else{
            return view('part/register');
        }
    }


}











