<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/15
 * Time: 上午9:33
 */

namespace app\admin\controller;


use think\Session;
use think\Request;

class Index extends Common
{
    public $id;

    public static $table_user = 'user';

    public static $primarykey = 'user_id';

    public $time;

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->id = Session::get('user_id');
        $this->time = time();
    }

    public function index()
    {
        //dd(1);
        return view('index');
    }

    public function more()
    {
        return view('more');
    }

    //重置密码

    public function resetpwd()
    {
        if(Request::instance()->isPost()){
            $rule = [
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
                $pwd = md5(sha1($this->parme('pwd')));
                $res = db(self::$table_user)
                    ->where(self::$primarykey,$this->id)
                    ->update(['pwd'=>$pwd,'updated_at'=>$this->time]);
                if($res){
                    return $this->redirect('index/index');
                }else{
                    $this->error('操作失败');
                }
            }
        }else{
//            $username = db(self::$table_user)
//                ->where(['user_id'=>$this->id])
//                ->value('username');
            $username = 'aa';
            return view('resetpwd',[
                'username' => $username
            ]);
        }
    }
}