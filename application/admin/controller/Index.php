<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/15
 * Time: 上午9:33
 */

namespace app\admin\controller;
use app\DataAnalysis;
use think\Session;
use think\Request;
use think\Validate;

class Index extends Common
{
    public $id;

    public static $table_user = 'user';

    public static $table_slyderAdventures = 'slyderAdventures'; //大转盘

    public static $table_deputy = 'deputy'; //代理

    public static $primarykey = 'user_id';

    public $time;

    public static $msg = [];

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $this->id = Session::get('user_id');
        $this->time = time();
        self::$msg = array_merge(DataAnalysis::$msg,self::$msg);
    }

    public function index()
    {
        //dd(input('flag'));
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
            $username = db(self::$table_user)
                ->where(['user_id'=>$this->id])
                ->value('username');
            //$username = 'aa';
            return view('resetpwd',[
                'username' => $username
            ]);
        }
    }

    //大转盘设置  slyderAdventures

    public function slyderAdventures()
    {
        //标识符区分添加修改
        $flag = input('flag');
        $where = [
            'app_id'    => $this->id,
            'active_id' => $this->parme('active_id')
        ];
        if(Request::instance()->isPost()){
            $insert = [
                'activetitle'   => $this->parme('activetitle'), //活动标题
                'activeperiod'  => $this->parme('activeperiod'), // 活动时间段
                'limit_collar'  => $this->parme('limit_collar'),//每人可抽奖次数 每次/每个时间段
                'prize'         => $this->parme('prize'),//奖项礼品
                'probability'   => $this->parme('probability'),//奖项概率
                'updated_at'    => $this->time,
            ];
            if($flag == 'add'){
                //$insert['deputy_id'] = $this->parme('deputy_id'); //代理ID
                $insert['app_id']    = $this->id; //哪个平台
                $insert['created_at']= $this->time;
                $res = db(self::$table_slyderAdventures)->insertGetId($insert);
            }else{
                $res = db(self::$table_slyderAdventures)->where($where)->update($insert);
            }
            if($res){
                return $this->redirect('index/slyderAdventures');
            }else{
                $this->error('操作失败');
            }
        }else{
           if($flag == 'add'){
                return view('addslyderAdventures');
           }else if($flag == 'update'){
               $data = db(self::$table_slyderAdventures)
                   ->where($where)
                   ->find();
               return view('updateslyderAdventures',[
                   'data'   => $data
               ]);
           }else{
               $data = db(self::$table_slyderAdventures)
                   ->where('app_id',$this->id)
                   ->page(input('page',1),input('pageshow',15))
                   ->select();
               return view('listslyderAdventures',[
                  'data'    => $data,
               ]);
           }
        }
    }

    //删除大转盘

    public function delslyderAdventures()
    {
        $rule = [
            'active_id'   => 'require',
            'app_id'      => 'require',
        ];
        $field = [
            'active_id'   => '大转盘ID',
            'app_id'      => '平台ID',
        ];

        $validate = new Validate($rule,self::$msg,$field);

        if(!$validate->check($this->parme)){
            $this->error($validate->getError());
        }else{
            $where = [
                'app_id'    => $this->id,
                'active_id' => $this->parme('active_id')
            ];
            $res = db(self::$table_slyderAdventures)->where($where)->delete();
            if($res){
                return $this->redirect('index/slyderAdventures');
            }else{
                $this->error('操作失败');
            }
        }
    }

    //代理设置

    public function setdeputy()
    {
        //标识符区分添加修改
        $flag = input('flag');
        $where = [
            'app_id'    => $this->id,
            'deputy_id' => $this->parme('deputy_id')
        ];
        if(Request::instance()->isPost()){
            if($flag == 'add'){
                $return = $this->checkdeputy($this->parme('position'));
            }else{
               $return = $this->checkdeputy($this->parme('position'),$this->parme('deputy_id'));
            }
            if($return === false){
                $this->error('当前城市已存在代理');
            }
            $insert = [
                'deputy_name'   => $this->parme('deputy_name'),
                'phone'         => $this->parme('phone'),
                'position'      => $this->parme('position'),
                'updated_at'    => $this->time,
            ];
            //检测当前账号是否可用
            if($flag == 'add'){
                $res = $this->checkusername($this->parme('username'));
                if($res === false){
                    $this->error('账号已存在');
                }
                $pwd = md5(sha1($this->parme('pwd')));
                $insert['username'] = $this->parme('username');
                $insert['pwd']      = $pwd;
                $insert['app_id']   = $this->id;
                $insert['created_at']= $this->time;
                $result = db(self::$table_deputy)->insertGetId($insert);
            }else{
                $result = db(self::$table_deputy)->where($where)->update($insert);
            }
            if($result){
                return $this->redirect('index/setdeputy');
            }else{
                $this->error('操作失败');
            }
        }else{
            if($flag == 'add'){
                return view('adddeputy');
            }else if($flag == 'update'){
                $data = db(self::$table_deputy)
                    ->where($where)
                    ->find();
                return view('updatedeputy',[
                    'data'   => $data
                ]);
            }else{
                $data = db(self::$table_deputy)
                    ->where('app_id',$this->id)
                    ->page(input('page',1),input('pageshow',15))
                    ->select();
                return view('listdeputy',[
                    'data'    => $data,
                ]);
            }
        }
    }

    //查找当前省市区是否有代理

    private function checkdeputy($position,$deputyid=null)
    {
        $where = [
            'app_id'    => $this->id,
            'position'  => $position,
        ];
        if($deputyid){
            $where['deputy_id'] = ['notIn',$deputyid];
        }
        $count = db(self::$table_deputy)
            ->where($where)
            ->count();
        if($count > 0){
            return false;
        }else{
            return true;
        }
    }

    //检测当前账号是否可用

    private function checkusername($username)
    {
        $where = [
            'app_id'    => $this->id,
            'username'  => $username,
        ];
        $count = db(self::$table_deputy)
            ->where($where)
            ->count();
        if($count > 0){
            return false;
        }else{
            return true;
        }
    }

    //重置代理密码

    public function resetdeputypwd()
    {
        if(Request::instance()->isPost()){
            $rule = [
                'pwd'       => 'require|confirm:repwd|alphaNum|min:4|max:18',
                'deputy_id' => 'require',
            ];
            $field = [
                'username'  => '账号',
                'pwd'       => '密码',
                'deputy_id' => '代理ID',
            ];
            $validate = new Validate($rule, self::$msg, $field);
            if (!$validate->check($this->parme)) {
                $this->error($validate->getError());
            } else {
                $pwd = md5(sha1($this->parme('pwd')));
                $res = db(self::$table_deputy)
                    ->where('deputy_id',$this->parme('deputy_id'))
                    ->update(['pwd'=>$pwd,'updated_at'=>$this->time]);
                if($res){
                    return $this->redirect('index/setdeputy');
                }else{
                    $this->error('操作失败');
                }
            }
        }else{
            $username = db(self::$table_deputy)
                ->where(['deputy_id'=>$this->parme('deputy_id')])
                ->value('username');
            //$username = 'aa';
            return view('resetdeputypwd',[
                'username' => $username
            ]);
        }
    }
}













