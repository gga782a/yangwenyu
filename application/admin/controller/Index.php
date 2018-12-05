<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/15
 * Time: 上午9:33
 */

namespace app\admin\controller;
use app\DataAnalysis;
use think\Db;
use think\Session;
use think\Request;
use think\Validate;
use think\File;

class Index extends Common
{
    public $id;

    public static $table_user = 'user';

    public static $table_slyderAdventures = 'slyderadventures'; //大转盘

    public static $table_deputy = 'deputy'; //代理

    public static $table_goods = 'goods'; // 商品

    public static $table_member = 'member'; //会员

    public static $table_store = 'store'; // 门店

    public static $table_activity = 'activity';//活动

    public static $table_chongzhi = 'chongzhi';//充值

    public static $primarykey = 'user_id';

    public static $table_prize = 'prize';

    public $time;

    public static $msg = [];

    public function __construct()
    {
        //dd(1);
        parent::__construct(); // TODO: Change the autogenerated stub
        $this->id = Session::get('user_id');
        $this->time = time();
        self::$msg = array_merge(DataAnalysis::$msg,self::$msg);
    }

    public function index()
    {
        //dd(input('flag'));
        return view('index');
    }

    public function deputyindex()
    {
        //dd(input('flag'));
        return view('deputyindex');
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
                //dd($pwd);
                $res = Db::table('shui_user')
                    ->where(self::$primarykey,$this->id)
                    //->select();
                    ->update(['pwd'=>$pwd,'updated_at'=>$this->time]);
                //dd($res);
                if($res!==false){
                    $this->success('操作成功');
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
       // dd($this->time);
        //标识符区分添加修改
        $flag = input('flag');
        $where = [
            'app_id'    => $this->id,
            'active_id' => $this->parme('active_id')
        ];
        if(Request::instance()->isPost()){
            //dd(input());
            $time = $this->parme('time');
            $time1 = $this->parme('time1');
            $time2 = $this->parme('time2');
            //换成我想要的时间组
            $returntime = $this->gettimegroup($time);
            $returntime1 = $this->gettimegroup($time1);
            $returntime2 = $this->gettimegroup($time2);
            //
            $activeperiod = [];
            if($returntime!=''){
                $activeperiod[count($activeperiod)] = $returntime;
            }
            if($returntime1!=''){
                $activeperiod[count($activeperiod)] = $returntime1;
            }
            if($returntime2!=''){
                $activeperiod[count($activeperiod)] = $returntime2;
            }
            //转换成json字符串
            //dd($activeperiod);
            if(!empty($activeperiod)){
                $activeperiod = json_encode($activeperiod);
            }else{
                $activeperiod = db(self::$table_slyderAdventures)
                    ->where($where)
                    ->value('activeperiod');
            }
            //判断中奖率超过100反错
            $prize_ids = $this->parme('prize');
            $sumcount = 0;
            foreach ($prize_ids as $ke=>$prize_id){
                $sumcount += db(self::$table_prize)
                    ->where(['app_id'=>$this->id,'prize_id'=>$prize_id])
                    ->value('probability');

            }
            if($sumcount>100){
                return $this->error('中奖率累加不能超过100');
            }
            //dd($activeperiod);
            $insert = [
                'activetitle'   => $this->parme('activetitle'), //活动标题
                'activeperiod'  => $activeperiod, // 活动时间段
                'limit_collar'  => $this->parme('limit_collar'),//每人可抽奖次数 每次/每个时间段
                'prize'         => json_encode($this->parme('prize')),//奖项礼品
                'probability'   => $this->parme('probability'),//奖项概率
                'updated_at'    => $this->time,
            ];
            //dd($this->time);
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
                return $this->error('操作失败');
            }
        }else{
           if($flag == 'add'){
                $prize = $this->listprize();
                //dd($prize);
                if($prize === false){
                    return $this->error('请先去添加礼品','index/setprize');
                }else{
                    $prize = json_decode($prize,true);
                }
                return view('addslyderAdventures',[
                    'prize' => $prize,
                ]);
           }else if($flag == 'update'){
               $data = db(self::$table_slyderAdventures)
                   ->where($where)
                   ->find();
               $prize = $this->listprize();
               if($prize === false){
                   return $this->error('请先去添加礼品','index/setprize');
               }else{
                   $prize = json_decode($prize,true);
               }
               return view('modifyslyderAdventures',[
                   'data'   => $data,
                   'prize' => $prize,
               ]);
           }else{
               $data = db(self::$table_slyderAdventures)
                   ->where('app_id',$this->id)
                   ->order('created_at desc')
                   ->page(input('page',1),input('pageshow',15))
                   ->select();
               if(!empty($data)){
                   foreach ($data as $k=>$v){
                       $activeperiod = json_decode($v['activeperiod'],true);
                       foreach ($activeperiod as $key=>$val){
                            $val = explode(',',$val);
                            foreach ($val as $ke=>$va){
                                $val[$ke] = date("Y-m-d H:i:s",$va);
                            }
                            $activeperiod[$key] = $val;
                       }
                       $data[$k]['activeperiod'] = $activeperiod;
                       $prize_ids = json_decode($v['prize'],true);
                       foreach ($prize_ids as $ke=>$prize_id){
                           $prizes[$ke] = db(self::$table_prize)
                               ->where(['app_id'=>$this->id,'prize_id'=>$prize_id])
                               ->field('name,probability')
                               ->find();
                       }
                       $data[$k]['prizes'] = $prizes;
                   }
                   //dd($data);
               }
               return view('listslyderAdventures',[
                  'data'    => $data,
               ]);
           }
        }
    }

    private function listprize()
    {
        $where['app_id']  = $this->id;
        $where['status']  = 1;
        $list = db(self::$table_prize)->where($where)->select();
        if($list){
            return json_encode($list);
        }else{
            return false;
        }
    }

    //huanshijianzu

    private function gettimegroup($arr=[])
    {
        $newtime = '';
        if(!empty($arr)){
            $timearr = explode('~',$arr);
            foreach($timearr as $v){
                $newtime .= strtotime(trim($v)).",";
            }
        }
        $newtime = trim($newtime,',');
        return $newtime;
    }

    //删除大转盘

    public function delslyderAdventures()
    {
        if(Request::instance()->isAjax()) {
            $where = [
                'app_id'    => $this->id,
                'active_id' => $this->parme('active_id')
            ];
            $res = db(self::$table_slyderAdventures)->where($where)->delete();
            if ($res) {
                return json(['code'=>200,'msg'=>'操作成功']);
            } else {
                return json(['code'=>400,'msg'=>'操作失败']);
            }
        }
    }

    //代理设置

    public function setdeputy()
    {
        //标识符区分添加修改
        $flag = input('flag');
        $level = input('level',0);
        $where = [
            'app_id'    => $this->id,
            'deputy_id' => $this->parme('deputy_id')
        ];
        if(Request::instance()->isPost()){
            $province = $this->parme('province');
            $city     = $this->parme('city');
            $county   = $this->parme('county');
            if($province == "请选择"){
                $province = '';
            }
            if($city == "请选择"){
                $city = '';
            }
            if($county == "请选择"){
                $county = '';
            }
            $position = $province.$city.$county;
            if($flag == 'add'){
                //dd($this->parme);
//                $rule = [
//                    'username' => 'require|unique|chsDash|min:4|max:18',
//                    'pwd' => 'require|confirm:repwd|alphaNum|min:4|max:18',
//                    'province' => 'require',
//                ];
//                $field = [
//                    'username' => '账号',
//                    'pwd' => '密码',
//                    'province' => '省',
//                ];
//                $validate = new Validate($rule, self::$msg, $field);
//                if (!$validate->check($this->parme)) {
//                    $this->error($validate->getError());
//                } else {
                    $return = $this->checkdeputy($province,$city,$county);
               // }
            }else{
               $return = $this->checkdeputy($province,$city,$county,$this->parme('deputy_id'));
            }
            if($return === false){
                $this->error('当前城市已存在代理');
            }

            $insert = [
                'deputy_name'   => $this->parme('deputy_name'),
                'phone'         => $this->parme('phone'),
                'province'      => $province,
                'city'          => $city,
                'county'        => $county,
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
                $insert['type_id']  = 0; //活动ID
                $insert['type']     = 0; //活动类型
                $insert['level']    = 0;//0普通代理 1分公司级
                $insert['parentid'] = 0;//上级代理ID
                $insert['status']   = 1;//状态
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
                //dd($data);
                return view('updatedeputy',[
                    'data'   => $data
                ]);
            }else{
                //var_dump($level);
                $where['parentid'] = (int)$this->parme('parentid','0');
                $data = db(self::$table_deputy)
                    ->where(['app_id'=>$this->id,'level'=>$level])
                    ->order('created_at desc')
                    ->page(input('page',1),input('pageshow',15))
                    ->select();
                if(!empty($data)){
                    foreach($data as $k=>$v){
                        $data[$k]['parentname'] = db(self::$table_deputy)
                            ->where(['app_id'=>$this->id,'deputy_id'=>$v['parentid']])
                            ->value('deputy_name');
                        $data[$k]['activetitle'] = db(self::$table_slyderAdventures)
                            ->where(['app_id'=>$this->id,'active_id'=>$v['type_id']])
                            ->value('activetitle');
                    }
                }

                return view('listdeputy',[
                    'data'    => $data,
                    'level'   => $level,
                ]);
            }
        }
    }

    //查找当前省市区是否有代理

    private function checkdeputy($province,$city,$county,$deputyid=null)
    {
        $where = [
            'app_id'    => $this->id,
            'province'      => $province,
            'city'          => $city,
            'county'        => $county,
            'status'    => 1,
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

    //删除代理

    public function deldeputy()
    {
        //dd(222);
        if(Request::instance()->isAjax()) {
            $where = [
                'app_id' => $this->id,
                'deputy_id' => $this->parme('deputy_id')
            ];
            $res = db(self::$table_deputy)->where($where)->delete();
            if ($res) {
                return json(['code'=>200,'msg'=>'操作成功']);
            } else {
                return json(['code'=>400,'msg'=>'操作失败']);
            }
        }
    }

    //更改代理权限

    public function updatelevel()
    {
        if(Request::instance()->isAjax()) {
            $where = [
                'app_id' => $this->id,
                'deputy_id' => $this->parme('deputy_id')
            ];
            $update = ['level'=>$this->parme('level'),'updated_at'=>$this->time];
            $res = db(self::$table_deputy)->where($where)->update($update);
            if ($res!==false) {
                return json(['code'=>200,'msg'=>'操作成功']);
            } else {
                return json(['code'=>400,'msg'=>'操作失败']);
            }
        }
    }

    //为代理选择分公司

    public function choicecompany(){
        $deputy_id = $this->parme('deputy_id');
        $parentid  = $this->parme('parentid');
        if(Request::instance()->isPost()){
            $where = [
                'app_id'    => $this->id,
                'deputy_id' => $deputy_id,
            ];
            $res = db(self::$table_deputy)
                ->where($where)
                ->update(['parentid'=>$parentid,'updated_at'=>$this->time]);
            if($res!==false){
                return $this->redirect('index/setdeputy');
            }else{
                $this->error('操作失败');
            }
        }else{
            $where = [
                'app_id'    => $this->id,
                'level'     => 1,
                'status'    => 1,
            ];
            $company = db(self::$table_deputy)
               ->where($where)
               ->page(input('page',1),input('pageshow',15))
               ->select();
            return view('index/choicecompany',[
                'data' => $company,
                'parentid' => $parentid,
                'deputy_id'=> $deputy_id,
            ]);
        }
    }

    //为代理选择大转盘

    public function choicedzp(){
        $deputy_id = $this->parme('deputy_id');
        $active_id = $this->parme('active_id');
        //dd($active_id);
        $type      = $this->parme('type');
        if(Request::instance()->isPost()){
            $where = [
                'app_id'    => $this->id,
                'deputy_id' => $deputy_id,
            ];
            //dd($active_id);
            $res = db(self::$table_deputy)
                ->where($where)
                ->update(['type_id'=>$active_id,'type'=>$type,'updated_at'=>$this->time]);
            if($res!==false){
                return $this->redirect('index/setdeputy');
            }else{
                $this->error('操作失败');
            }
        }else{
            $where = [
                'app_id'    => $this->id,
            ];
            $dzp = db(self::$table_slyderAdventures)
                ->where($where)
                ->page(input('page',1),input('pageshow',15))
                ->select();
            if(!empty($dzp)) {
                foreach ($dzp as $k => $v) {
                    $activeperiod = json_decode($v['activeperiod'], true);
                    foreach ($activeperiod as $key => $val) {
                        $val = explode(',', $val);
                        foreach ($val as $ke => $va) {
                            $val[$ke] = date("Y-m-d H:i:s", $va);
                        }
                        $activeperiod[$key] = $val;
                    }
                    $dzp[$k]['activeperiod'] = $activeperiod;
                }
            }
            return view('index/choicedzp',[
                'data' => $dzp,
                'active_id' => $active_id,
                'deputy_id'=> $deputy_id,
            ]);
        }
    }

    //为代理选则大转盘 //活动类型为1

    public function chooiceactive()
    {
        $rule = [
            'type_id'     => 'require',
            'deputy_id'   => 'require',
            'type'        => 'require',
        ];
        $field = [
            'type_id'     => '活动ID',
            'deputy_id'   => '代理ID',
            'type'        => '活动类型',
        ];

        $validate = new Validate($rule,self::$msg,$field);

        if(!$validate->check($this->parme)){
            $this->error($validate->getError());
        }else{
            $where = [
                'app_id'    => $this->id,
                'deputy_id' => $this->parme('deputy_id')
            ];
            $update = [
                'type_id'   => $this->parme('type_id'),
                'type'      => $this->parme('type'),
                'updated_at'=> $this->time,
            ];
            $res = db(self::$table_deputy)
                ->where($where)
                ->update($update);
            if($res){
                return $this->redirect('index/setdeputy');
            }else{
                $this->error('操作失败');
            }
        }
    }

    //积分商城

    //商品设置
    public function setgoods()
    {
        //标识符区分添加修改
        $flag = input('flag');
        $where = [
            'app_id'    => $this->id,
            'goods_id'  => $this->parme('goods_id')
        ];
        if(Request::instance()->isPost()){
            $return = $this->upload('image');
            if($return == false){
                $this->error('上传图片失败');
            }
            $insert = [
                'sort'          => $this->parme('sort'), //排序
                'goods_name'    => $this->parme('goods_name'), // 名称
                'pic_arr'       => $return,//商品图片
                'price'         => $this->parme('price'),//价格
                'stock'         => $this->parme('stock'),//库存
                'goods_desc'    => $this->parme('goods_desc'),//详情
                'sales'         => $this->parme('sales'),//销量
                'status'        => $this->parme('status'),//状态 //0上架 1售罄 2下架
                //'shelves'       => 'true',//上下架
                'updated_at'    => $this->time,
            ];
            if($flag == 'add'){
                //$insert['deputy_id'] = $this->parme('deputy_id'); //代理ID
                $insert['app_id']    = $this->id; //哪个平台
                $insert['created_at']= $this->time;
                $res = db(self::$table_goods)->insertGetId($insert);
            }else{
                $res = db(self::$table_goods)->where($where)->update($insert);
            }
            if($res){
                return $this->redirect('index/setgoods');
            }else{
                $this->error('操作失败');
            }
        }else{
            if($flag == 'add'){
                return view('addgoods');
            }else if($flag == 'update'){
                $data = db(self::$table_goods)
                    ->where($where)
                    ->find();
                return view('updategoods',[
                    'data'   => $data
                ]);
            }else{
                $wherelist = [
                    'app_id' => $this->id,
                ];
                if($this->parme('status')){
                   $where['status'] = $this->parme('status');  //下架或售罄商品
                }
                $data = db(self::$table_goods)
                    ->where($wherelist)
                    ->page(input('page',1),input('pageshow',15))
                    ->select();
                return view('listgoods',[
                    'data'    => $data,
                    'status'  => (int)$this->parme('status','0'),
                ]);
            }
        }
    }

    //删除商品

    public function delgoods()
    {
        $rule = [
            'goods_id'   => 'require',
            //'app_id'      => 'require',
        ];
        $field = [
            'goods_id'   => '商品ID',
            'app_id'     => '平台ID',
        ];

        $validate = new Validate($rule,self::$msg,$field);

        if(!$validate->check($this->parme)){
            $this->error($validate->getError());
        }else{
            $where = [
                'app_id'    => $this->id,
                'goods_id'  => $this->parme('goods_id')
            ];
            $res = db(self::$table_goods)->where($where)->delete();
            if($res){
                return $this->redirect('index/setgoods');
            }else{
                $this->error('操作失败');
            }
        }
    }

    //图片上传

    public function upload($img = '',$validate = ['size'=>15678,'ext'=>'jpg,png,gif'])
    {
        if ($img == '') {
            return false;
        } else {
            $pic_arr = '';
            // 获取表单上传文件 例如上传了001.jpg
            $files = request()->file($img);
            //判断是不是多图上传
            $dir = ROOT_PATH . 'public' . DS . 'uploads' . DS;
            $date = date('Ymd', time()) . '/';
            $path = $dir . $date;
            if (file_exists($path)) {
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
                            return false;
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

    //dai代理商管理  绑定代理商

    public function managedeputy()
    {
        $where = [
            'app_id'    => $this->id,
            'deputy_id'  => $this->parme('deputy_id')
        ];
        if(Request::instance()->isPost()){
            $insert = [
                'sale_id'      => $this->parme('sale_id'), //销售人员ID
                'sale_phone'   => $this->parme('sale_phone'), //手机
                'auth'         => $this->parme('auth'),//价格
                'updated_at'   => $this->time,
            ];

            $res = db(self::$table_deputy)->where($where)->update($insert);
            if($res){
                return $this->redirect('index/setdeputy');
            }else{
                $this->error('操作失败');
            }
        }else{
            $data = db(self::$table_deputy)
                ->where($where)
                ->find();
            return view('updategoods',[
                'data'   => $data
            ]);
        }
    }

    //会员管理

    public function membermanage()
    {
        //标识符区分添加修改
        $flag = input('flag');
        $where = [
            'app_id'     => $this->id,
            'member_id'  => $this->parme('member_id')
        ];
        if(Request::instance()->isPost()){
            $insert = [
                'sort'          => $this->parme('sort'), //排序
                'nick_name'     => $this->parme('nick_name'), // 名称
                'phone'         => $this->parme('phone'),//
                'follow_time'   => $this->parme('follow_time'),
                'app_id'        => $this->id,
                'register_from' => $this->parme('register_from'),
                'paymoney'      => floatval($this->parme('paymoney')),
                'created_at'     => $this->time,
            ];
            $res = db(self::$table_member)->insertGetId($insert);
            if($res){
                return $this->redirect('index/membermanage');
            }else{
                $this->error('操作失败');
            }
        }else{
            if($flag == 'add'){
                return view('addmember');
            }else{
                $wherelist = [
                    'app_id' => $this->id,
                ];
                $data = db(self::$table_member)
                    ->where($wherelist)
                    ->page(input('page',1),input('pageshow',15))
                    ->select();
                return view('listmember',[
                    'data'    => $data,
                ]);
            }
        }
    }

    //设置礼品

    public function setprize()
    {
        //标识符区分添加修改
        $flag = input('flag');
        $where = [
            'app_id'     => $this->id,
            'prize_id'  => $this->parme('prize_id')
        ];
        if(Request::instance()->isPost()){
            //dd(floatval($this->parme('probability')));
            if(floatval($this->parme('probability'))>100){
                return $this->error('中奖概率不能高于100');
            }
            //$insert['type']        = $this->parme('type'); //活动类型
            $insert['name']        = $this->parme('name');  //奖品名称
            $insert['sum']         = $this->parme('sum');  //奖品数量
            $insert['probability'] = floatval($this->parme('probability'));  //中奖概率
            //$insert['type_id']     = $this->parme('type_id');  //活动ID
            $insert['updated_at']  = time();  //创建时间
            $insert['status']      = '1';   //启用
            if($flag == 'add'){
                $insert['app_id']  = $this->id;
                $insert['created_at']= time();
                $res = db(self::$table_prize)->insertGetId($insert);
            }else{
                $res = db(self::$table_prize)->where($where)->update($insert);
            }
            if($res){
                return $this->redirect('index/setprize');
            }else{
                $this->error('操作失败');
            }
        }else{
            if($flag == 'add'){
                return view('addprize');
            }else if($flag == 'update'){
                $data = db(self::$table_prize)->where($where)->find();
                return view('editprize',[
                    'data'    => $data,
                ]);
            }else{
                $wherelist = [
                    'app_id' => $this->id,
                    'status' => 1,
                ];
                $data = db(self::$table_prize)
                    ->where($wherelist)
                    ->page(input('page',1),input('pageshow',15))
                    ->select();
                return view('listprize',[
                    'data'    => $data,
                ]);
            }
        }
    }

    //删除礼品

    public function delprize()
    {
        if(Request::instance()->isAjax()) {
            //dd(11);
            //检查礼品是否有被使用
            $list = db(self::$table_slyderAdventures)
                ->where(['app_id'=>$this->id])
                ->field('prize')
                ->select();
            //dd($list);
            $isfalse = false;
            if(!empty($list)){
                foreach ($list as $k=>$v){
                    //dd($v['prize']);
                    if($v['prize']){
                        //var_dump($this->parme('prize_id'));
                        //var_dump(json_decode($v['prize'],true));
                        if(in_array($this->parme('prize_id'),json_decode($v['prize'],true))){
//dd(222);
                            $isfalse = true;
                        }
                    }
                }
            }
            //dd($isfalse);
            if($isfalse){
                return json(['code'=>400,'msg'=>'礼品已被使用']);
            }
            dd(22);
            $where = [
                'app_id' => $this->id,
                'prize_id' => $this->parme('prize_id')
            ];
            $res = db(self::$table_prize)->where($where)->delete();
            if ($res) {
                return json(['code'=>200,'msg'=>'操作成功']);
            } else {
                return json(['code'=>400,'msg'=>'操作失败']);
            }
        }
    }

    /***********************************商家后台开始**********************************************************/
    //门店管理


    //设置优惠储值



    /***********************************商家后台结束**********************************************************/

    /***********************************代理后台开始**********************************************************/


























    /***********************************代理后台结束**********************************************************/
}













