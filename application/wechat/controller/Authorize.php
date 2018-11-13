<?php
namespace app\wechat\controller;
use app\DataAnalysis;
use app\Qiniu;
use think\Validate;

class Authorize extends Index
{
    public static $msg  =   [];
    public $app;
    protected static $file  =   [
        'template_id'   =>  '模板id',
        'json'          =>  '信息',
        'app_id'        =>  '应用ID'
    ];
    public function __construct()
    {
        parent::__construct();
        $this->verfy();
    }
    //验证小程序所有权
    public function verfy()
    {

        $app  =   db('app')->where(['app_id'=>$this->parme('app_id'),'creat_user'=>$this->parme('user_id')])->find();
        if(!$this->parme('app_id') && $app) {
            $this->app  =   $app;
            $this->abnormal($this->AuthError, ['message' => '小程序归属错误']);
        }
    }

    //获取授权URL
    public function url()
    {
        $component_access_token =   $this->token();
        $url                    =   'https://api.weixin.qq.com/cgi-bin/component/api_create_preauthcode?component_access_token='.$component_access_token;
        $json                   =   json_encode(['component_appid' => $this->AppId]);
        $data                   =   json_decode(curl_request($url ,$json),true);

        if(!array_key_exists('errcode',$data))
        {
            $url                =   'https://mp.weixin.qq.com/cgi-bin/componentloginpage?component_appid='.$this->AppId.'&pre_auth_code='.$data['pre_auth_code'].'&redirect_uri='.urlencode('http://www.ztwlxx.club/wxchat?app_id='.$this->parme('app_id'));
            $this->datas        =   ['url'=>$url];
        }else{
            $this->abnormal($this->AuthError,['message'=>'获取授权网址失败']);
        }
    }

    /*
     *
     * 信息网代理登录授权开始
     *
     */
    //获取授权回调
    public function xxw_callback()
    {
        if($this->parme('auth_code') )
        {
            $dataS                  =   $this->parme;
            $component_access_token =   $this->token();
            $json                   =   json_encode(['authorization_code' => $dataS['auth_code'], 'component_appid' => $this->AppId]);
            $callback               =   json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token=' . $component_access_token,$json),true);

            if (!array_key_exists('errcode',$callback)) {
                $this->xxwdiao($callback);
            } else {
                $this->abnormal($this->AuthError, '授权失败');
            }
        }else{

            $this->abnormal($this->AuthError,'授权回调失败，缺失auth_code');
        }
    }

    //获取小程序授权信息
    public function xxwdiao($callback)
    {
        $component_access_token     =   $this->token();
        $diao                       =   json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token='.$component_access_token,json_encode(['authorizer_appid'=>$callback['authorization_info']['authorizer_appid'],'component_appid'=>$this->AppId])),true);
        if(!array_key_exists('errcode',$diao))
        {
            $wx_appid                 =       $diao['authorization_info']['authorizer_appid'];
            $authorizer_refresh_token =       $diao['authorization_info']['authorizer_refresh_token'];
            $data = array(
                'wx_appid' => $wx_appid,
                'authorizer_refresh_token' => $authorizer_refresh_token
            );
            $this->datas = $data;
        }else{
            $this->abnormal($this->AuthError,'授权失败');
        }

    }

    /*
    *
    * 信息网代理登录授权结束
    *
    */


    //获取授权回调
    public function callback()
    {
        if($this->parme('auth_code') )
        {
            $dataS                  =   $this->parme;
            $component_access_token =   $this->token();
            $json                   =   json_encode(['authorization_code' => $dataS['auth_code'], 'component_appid' => $this->AppId]);
            $callback               =   json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/component/api_query_auth?component_access_token=' . $component_access_token,$json),true);

            if (!array_key_exists('errcode',$callback)) {
                $this->diao($callback);
            } else {
                $this->abnormal($this->AuthError, '授权失败');
            }
        }else{
            $this->abnormal($this->AuthError,'授权回调失败，缺失auth_code');
        }
    }

    //获取小程序授权信息
    public function diao($callback)
    {
        $component_access_token     =   $this->token();
        $diao                       =   json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_info?component_access_token='.$component_access_token,json_encode(['authorizer_appid'=>$callback['authorization_info']['authorizer_appid'],'component_appid'=>$this->AppId])),true);
        if(!array_key_exists('errcode',$diao))
        {
            $appid                              =       $this->parme('app_id');
            $insert['wx_appid']                 =       $diao['authorization_info']['authorizer_appid'];
            $insert['authorizer_refresh_token'] =       $diao['authorization_info']['authorizer_refresh_token'];
            $insert['nick_name']                =       $diao['authorizer_info']['nick_name'];
            $insert['head_img']                 =       $diao['authorizer_info']['head_img'];
            $insert['user_name']                =       $diao['authorizer_info']['user_name'];
            $insert['signature']                =       $diao['authorizer_info']['signature'];
            $insert['principal_name']           =       $diao['authorizer_info']['principal_name'];
            $insert['business_info']            =       json_encode($diao['authorizer_info']['business_info']);
            $insert['func_info']                =       json_encode($diao['authorization_info']['func_info']);
            $insert['verify_type_info']         =       json_encode($diao['authorizer_info']['verify_type_info']['id']);
            $insert['service_type_info']        =       json_encode($diao['authorizer_info']['service_type_info']['id']);
            $insert['miniprograminfo']          =       json_encode($diao['authorizer_info']['MiniProgramInfo']);
            $insert['update_at']                =       time();
            $insert['status']                   =       'authorized';

            $where['app_id']=$appid;
            $where['remark_status']=0;
            $data=db('app_submit')->where($where)->order('create_at desc')->find();
            //
            if(is_array($data) && $data)
            {
                if(array_key_exists('submit_id',$data)){
                    db('app_submit')->where('submit_id',$data['submit_id'])->update(['remark'=>json_encode($diao),'remark_status'=>1]);
                }
            }

            if($this->redis->exists( 'authorizer_access_token:'.  $appid))
            {
                $this->redis->del  ('authorizer_access_token:'.  $appid);
            }
            if($this->redis->exists( 'authorizer_refresh_token:'. $appid))
            {
                $this->redis->del  ('authorizer_refresh_token:'. $appid);
            }
            redis_set('authorizer_refresh_token:'.$appid,$diao['authorization_info']['authorizer_refresh_token'],'all');
            if(db('app')->where('app_id',$appid)->update($insert)!==false)
            {
                $res=$this->modify_domain();

                $this->datas    =   $res;
            }else{
                $this->abnormal($this->AuthError,'授权更新状态失败');
            }
        }else{
            $this->abnormal($this->AuthError,'授权失败');
        }

    }

    private function modify_domain(){
        $authorizer_access_token = $this->authorizer_access_token();
        $sendjson=json_encode([
            "action"              =>      "add",
            "requestdomain"       =>      ["https://api.ztwlxx.club", "https://api.ztwlxx.club","https://wxapi.ztwlxx001.com"],
            "wsrequestdomain"     =>      ["wss://api.ztwlxx.club",  "wss://api.ztwlxx.club","wss://wxapi.ztwlxx001.com"],
            "uploaddomain"        =>      ["https://api.ztwlxx.club", "https://api.ztwlxx.club","https://wxapi.ztwlxx001.com"],
            "downloaddomain"      =>      ["https://api.ztwlxx.club", "https://api.ztwlxx.club","https://wxapi.ztwlxx001.com"],
        ]);
        $data = json_decode(curl_request('https://api.weixin.qq.com/wxa/modify_domain?access_token=' . $authorizer_access_token, $sendjson ,true));
    }


    //添加体验者
    public function exper()
    {
        $authorizer_access_token = $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/wxa/bind_tester?access_token=' . $authorizer_access_token, json_encode(['wechatid' => $this->parme('weichat')])), true);
        if ($data['errcode'] == '85001') {
            $this->abnormal($this->AuthError, '微信号不存在');
        }else{
            if ($data['errcode'] == '85004') {
                $this->abnormal($this->AuthError, '该体验者已存在');
                $wheres['app_id'] = $this->parme('app_id');
                $wheres['wechat_id'] = $this->parme('weichat');
                $back = db('app_exper')->where($wheres)->find();
                if (!$back) {
                    $inserts['app_id']           =       $this->parme('app_id');
                    $inserts['wechat_id']        =       $this->parme('weichat');
                    $inserts['create_at']        =       time();
                    db('app_exper')->insertGetId($inserts);
                }
            } else {
                if ($data['errcode'] != 0) {
                    $this->abnormal($this->AuthError, $this->parme('app_id').'添加体验者失败[' . $data['errcode'] . ':' . $data['errmsg'] . ']');
                } else {

                    if ($this->parme['weichat']) {
                        $insert['app_id']           =       $this->parme('app_id');
                        $insert['wechat_id']        =       $this->parme('weichat');
                        $insert['create_at']        =       time();
                        if ($id = db('app_exper')->insertGetId($insert)) {
                            $this->datas = ['msg'   =>      '添加体验者成功', 'code' => $id];
                        } else {
                            $this->abnormal($this->AuthError, '记录体验者失败');
                        }
                    } else {
                        $this->abnormal($this->AuthError, '请填写体验者账户');
                    }
                }

            }
        }

    }

    //解除绑定小程序体验者
    public function pullexper()
    {
        $authorizer_access_token        =       $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/wxa/unbind_tester?access_token=' . $authorizer_access_token, json_encode(['wechatid' => $this->parme('wechatid')])),true);

        if($data['errcode']!='ok')
        {
            $this->abnormal($this->AuthError,'解绑体验者失败['.$data['errcode'].':'.$data['errmsg'].']');
        }else{
            if($this->parme['wechatid'])
            {
                $where['app_id']        =       $this->parme('app_id');
                $where['wechat_id']     =       $this->parme('wechatid');
                if(db('app_exper')->where($where)->delete())
                {
                    $this->datas    =   '解绑体验者成功';
                }else{
                    $this->abnormal($this->AuthError,'体验者记录删除失败');
                }
            }else{
                $this->abnormal($this->AuthError,'请填写体验者ID');
            }
        }
        return $data;

    }

    //获取授权方的选项信息
    public function get_option()
    {
        $option_name        =       $this->parme('option_name');
        $wx_appid           =       db('app')->where('app_id',$this->parme('app_id'))->value('wx_appid');
//        $option_name        =       'location_report';
//        $wx_appid   =  'wx4df5c3c4067c63e1';

        /*
        $option_name='location_report';
        $wx_appid   =  'wx92ef42be44692ad8';
        */
        //voice_recognize 语音识别开关选项
        //customer_service 多客服开关选项
        //location_report 地理位置上报选项 0 1 2

        $sendjson = json_encode(['component_appid'=> $this->AppId,'authorizer_appid'=> $wx_appid,'option_name'=>$option_name]);
//        dump($sendjson.$this->authorizer_access_token().$this->token());
        $callback = json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/component/api_get_authorizer_option?component_access_token='.$this->token(), $sendjson),true);
        $this->datas = $callback;
//        if($callback)
//        {
//            $this->datas    =  $callback;
//        }else{
//            $this->abnormal($this->AuthError,'获取权限失败，重新授权尝试');
//        }
    }


    //设置授权方的选项信息
    public function set_option()
    {

//        $option_name=$this->parme('option_name');
//        $option_value=$this->parme('option_value');
//        $wx_appid   =db('app')->where('app_id',$this->parme('app_id'))->value('wx_appid');
        $wx_appid           =       db('app')->where('app_id',$this->parme('app_id'))->value('wx_appid');
        $option_name        =       'location_r eport';
        $option_value       =       0;

        //voice_recognize 语音识别开关选项
        //customer_service 多客服开关选项
        //location_report 地理位置上报选项 0 1 2
        $sendjson = json_encode(['component_appid'=>$this->AppId,'authorizer_appid'=>$wx_appid,'option_name'=>$option_name,'option_value'=>$option_value]);
        $callback = json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/component/api_set_authorizer_option?component_access_token='.$this->token(), $sendjson),true);
        $this->datas = $callback;

//        if($callback['errcode']==0)
//        {
//            $this->datas    =   '更改权限成功';
//        }else{
//            $this->abnormal($this->AuthError,'获取权限失败，重新授权尝试');
//        }

    }

//
    //查询某个版本的审核状态
    public function audit_status()
    {
        $authorizer_access_token            =       $this->authorizer_access_token();
        $app_id                             =       $this->parme('app_id');
        $auditid                            =       $this->parme('auditid');
//        $auditid=db('app_submit')->where('app_id',$app_id)->limit(1)->order('submit_id desc')->value('auditid');
        //dump($auditid);
        $auditid=json_encode(['auditid'     =>     $auditid]);
        $data = json_decode(curl_request('https://api.weixin.qq.com/wxa/get_auditstatus?access_token='.$authorizer_access_token, $auditid),true);
        $data['is_release']=db('app')->where('app_id',$app_id)->value('is_release');
        $this->datas = $data;
    }


    //    查询最新一次提交的审核状态
    public function last_audit_status()
    {
        $authorizer_access_token    =   $this->authorizer_access_token();
        //dump($authorizer_access_token);
        $data = json_decode(curl_request('https://api.weixin.qq.com/wxa/get_latest_auditstatus?access_token='.$authorizer_access_token),true);
        //dump($data);
        $data['is_release']=db('app')->where('app_id', $this->parme('app_id'))->value('is_release');
        if($data['errcode']===85058){
            $this->datas = $data;
            $this->abnormal($this->errStatus,'未提交审核');
        }
        $this->datas = $data;
    }

    //发布已通过审核的小程序
    public function release_audit()
    {
        $authorizer_access_token    =   $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/wxa/release?access_token='.$authorizer_access_token,'{}'),true);
        $this->datas = $data;
    }

    //  修改小程序线上代码的可见状态
    public function revise_code_status()
    {
        //设置可访问状态，发布后默认可访问，close为不可见，open为可见
        $send=json_encode(['action'     =>      $this->parme('action')]);
        $authorizer_access_token        =       $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/wxa/change_visitstatus?access_token='.$authorizer_access_token,$send),true);
        $this->datas = $data;
    }

    //小程序版本回退
    public function version_rollback()
    {
        $authorizer_access_token    =   $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/wxa/revertcoderelease?access_token='.$authorizer_access_token),true);
        $this->datas = $data;
    }

    //小程序审核撤回
    public function release_callback()
    {
        $authorizer_access_token    =   $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/wxa/undocodeaudit?access_token='.$authorizer_access_token),true);
        if($data['errcode']==87013){
            $this->abnormal($this->errStatus,'每天只能撤回一次');
        }else{
            db('app')->where('app_id',$this->parme('app_id'))->update(['status'=>"release","is_release"=>1]);
        }
        $this->datas = $data;
    }

    //小程序分阶段发布
    //（1）分阶段发布接口
    public function phased_release()
    {
        //"gray_percentage": 1 //灰度的百分比，1到100的整数
        $send=json_encode(['gray_percentage'=>$this->parme('gray_percentage')]);
        $authorizer_access_token    =   $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/wxa/grayrelease?access_token='.$authorizer_access_token,$send),true);
        $this->datas = $data;
    }

    //（2）取消分阶段发布
    public function phasing_out()
    {
        $authorizer_access_token    =   $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/wxa/grayrelease?access_token='.$authorizer_access_token,'{}'),true);
        $this->datas = $data;
    }

    //（3）查询当前分阶段发布详情
    public function phasing_query()
    {
        $authorizer_access_token    =   $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/wxa/getgrayreleaseplan?access_token='.$authorizer_access_token),true);
        $this->datas = $data;
    }

    // 查询当前设置的最低基础库版本及各版本用户占比
    public function get_foundation()
    {
        $authorizer_access_token    =   $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/wxopen/getweappsupportversion?access_token='.$authorizer_access_token,'{}'),true);
        $this->datas = $data;
    }

    //设置最低基础库版本
    public function set_foundation()
    {
        $send=json_encode(['version'  =>  $this->parme('version')]);
        $authorizer_access_token      =   $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/wxopen/setweappsupportversion?access_token='.$authorizer_access_token,$send),true);
        $this->datas = $data;
    }

    //增加或修改二维码规则
    public function qrcode_jump_add()
    {
        /*$send=[
            'prefix'=>"http://www.renyidaode.com",
            'permit_sub_rule'=>2,
            'path'=>"pages/index/index",
            'open_version'=>2,
            'debug_url'=>[
                "http://www.renyidaode.com?a=1",
                "http://www.renyidaode.com?a=2",
            ],
            'is_edit'=>0
        ];*/
        $rule = [
            'prefix'             =>      'url',
            'permit_sub_rule'    =>      'require|between:1,2',
            'path'               =>      'require|max:50',
            'open_version'       =>      'require|between:1,3',
            'is_edit'            =>      'require|between:0,1'
        ];
        $field = [
            'prefix'             =>      '网址',
            'permit_sub_rule'    =>      '二维码前置占用规则',
            'path'               =>      '小程序功能页面',
            'open_version'       =>      '测试范围',
            'is_edit'            =>      '0为增加二维码，1为修改二维码'
        ];

        $validate = new Validate($rule,[],$field);
        if(!$validate->check($this->parme)){
            $this->abnormal($this->ValitorError,$validate->getError());
        }else {
            $send = [
                'prefix'              =>      $this->parme('prefix'),
                'permit_sub_rule'     =>      $this->parme('permit_sub_rule'),
                'path'                =>      $this->parme('path'),
                'open_version'        =>      $this->parme('open_version'),
                'debug_url'           =>      explode(',',$this->parme('debug_url')),
                'is_edit'             =>      $this->parme('is_edit')
            ];
        }

        if( $send['is_edit']  ==  0 ){
            $res=$this->qrcode_jump_download($send['prefix']);
            if( $res != "ok" ){
                $this->abnormal($this->AuthError,'请重试'.$res['data']);
            }
        }

        $send                       =    json_encode($send);
        $authorizer_access_token    =    $this->authorizer_access_token();
        $data                       =    json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/wxopen/qrcodejumpadd?access_token='.$authorizer_access_token,$send),true);

        if( $data ){
            switch ( $data['errcode'] )
            {
                case -1:
                    $data       =     '系统错误';
                    break;
                case 0:
                    $data       =     '成功';
                    break;
                case 85066:
                    $data       =     '链接错误';
                    break;
                case 85068:
                    $data       =     '测试链接不是子链接';
                    break;
                case 85069:
                    $data       =     '校验文件失败';
                    break;
                case 85070:
                    $data       =     '链接为黑名单';
                    break;
                case 85071:
                    $data       =     '已添加该链接，请勿重复添加';
                    break;
                case 85072:
                    $data       =     '该链接已被占用';
                    break;
                case 85073:
                    $data       =     '二维码规则已满';
                    break;
                case 85074:
                    $data       =     '小程序未发布, 小程序必须先发布代码才可以发布二维码跳转规则';
                    break;
                case 85075:
                    $data       =     '个人类型小程序无法设置二维码规则';
                    break;
                case 85076:
                    $data       =     '链接没有ICP备案';
                    break;
                default:
                    $data       =     '出现未知错误['.$data['errcode'].':'.$data['errmsg'].']';
                    break;
            }
        }
        $this->datas = $data;
    }

    public function get_qrcode($url = "www.baidu.com",$level = 3,$size = 4 )
    {

        if(empty($url))
        {
            $this->abnormal($this->AuthError,'url不能为空');
        }
        if(is_array($url) ||  is_array($this->object_array( json_decode($url) ))){
            $url=$this->object_array( json_decode($url));
            $i=0;
            foreach ($url as $res_url)
            {
                $res[$i]  =  $this->get_qrcode($res_url);
                $i++;
            }
            return $res;
        }
        Vendor('phpqrcode.phpqrcode');
        $errorCorrectionLevel   =    intval($level);//容错级别
        $matrixPointSize        =    intval($size);//生成图片大小
        //生成二维码图片
        $object     =       new \QRcode();
        $filename   =       rand(1000,9999).time();
        $object->png( $url, $filename, $errorCorrectionLevel, $matrixPointSize, 2);
        $data   =   Qiniu::qiniu($filename,'png');
        //dd($data);
        return  $data;
    }

    //获取已设置的二维码规则
    public function qrcode_jump_get()
    {
        $authorizer_access_token    =   $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/wxopen/qrcodejumpget?access_token='.    $authorizer_access_token,'{}'),true);
        $this->datas = $data;
    }

    //获取校验文件名称及内容
    function qrcode_jump_download($url='',$debug_url='')
    {
        $authorizer_access_token    =   $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/wxopen/qrcodejumpdownload?access_token='.$authorizer_access_token,'{}'),true);

        if($data['errmsg']=='ok'){
            if(     $this->redis->exists('jump_download_file_name:'.$data['file_name'])
                &&  $this->redis->exists('jump_prefix:'.$url) )
            {
                return  'ok';
            }else{
                $res=curl_request( "http://api.ztwlxx.com/get_txt.php",$data );
                redis_set(  'jump_download_file_name:' .$data['file_name'],7000 );
                redis_set(  'jump_prefix:' .$url,7000 );
                if($res == '32'){ $res = 'ok'; }
                return  $res;
            }
        }else{
            $this->datas = $data;
        }
    }

    //删除已设置的二维码规则
    public function qrcode_jump_delete()
    {
        $send=json_encode(['prefix'   =>    $this->parme('prefix')]);
        $authorizer_access_token      =     $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/wxopen/qrcodejumpdelete?access_token='.$authorizer_access_token,$send),true);
        $this->datas = $data;
    }

    //发布已设置的二维码规则
    public function qrcode_jump_publish()
    {
        $send=json_encode(['prefix'   =>   $this->parme('prefix')]);
        $authorizer_access_token      =    $this->authorizer_access_token();
        $data = json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/wxopen/qrcodejumppublish?access_token='.$authorizer_access_token,$send),true);
        $this->datas = $data;
    }

    //获取小程序码指定页面
    public function getwxacodeunlimit()
    {
        $rule = [
            'scene|scene' => 'require',
            'page|页面' => 'require',
            'width|宽度' => 'require',
        ];
        $validate = new Validate($rule);
        if (!$validate->check($this->parme)) {
            $this->abnormal($this->ValitorError, $validate->getError());
        } else {

            $send =json_encode([
                'scene'     =>      $this->parme("scene"),
                'page'      =>      $this->parme("page"),
                'width'     =>      $this->parme("width"),
            ]);
            $authorizer_access_token      =    $this->authorizer_access_token();
            $data   =   curl_request('https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token='.$authorizer_access_token,$send);
            $base   =   base64_encode($data);
            $this->datas  = "data:image/jpeg;base64,".$base;
        }
    }

    //获取小程序码
    public  function getwxacode()
    {
        $send =json_encode([
            'path'      =>      'index',
            'width'     =>      '180'
        ]);
        $authorizer_access_token      =    $this->authorizer_access_token();

        $data   =   curl_request('https://api.weixin.qq.com/wxa/getwxacode?access_token='.$authorizer_access_token,$send);
        $base   =   base64_encode($data);
        $this->datas  = "data:image/jpeg;base64,".$base;
    }

    //小程序体验者列表
    public function experlist()
    {
        $where['app_id']   =  $this->parme('app_id');
        $list   =   db('app_exper')->where($where)->select();
        if($list)
        {
            $this->datas   =   $list;
        }else{
            $this->abnormal($this->DbNull,'暂无体验者');
        }
    }



    //刷新第三方access_token
    public function refresh_token($appid='')
    {
        if($appid == ''){
            $app_id = $this->parme('app_id');
        }else{
            $app_id = $appid;
        }
//        $authorizer_refresh_token   =   $this->redis->get('authorizer_refresh_token:'.$app_id);
//        if(!$authorizer_refresh_token)
//        {
            $authorizer_refresh_token=db('app')->where('app_id',$app_id)->value('authorizer_refresh_token');
            redis_set('authorizer_refresh_token:'.$app_id,$authorizer_refresh_token,'all');
//        }
//        dd($authorizer_refresh_token);
        $wx_appid                   =   db('app')->where('app_id',$app_id)->value('wx_appid');
        $sendjson                   =   json_encode(['authorizer_refresh_token'=>$authorizer_refresh_token,'component_appid'=>$this->AppId,'authorizer_appid'=>$wx_appid]);

        $callback                   =   json_decode(curl_request('https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token?component_access_token='.$this->token(), $sendjson),true);
//        dd($callback);
        if(!array_key_exists('errcode',$callback))
        {
            redis_set('authorizer_access_token:' .$app_id,$callback['authorizer_access_token']);
            redis_set('authorizer_refresh_token:'.$app_id,$callback['authorizer_refresh_token'],'all');
            //var_dump($callback['authorizer_access_token']);
            return $callback['authorizer_access_token'];
        }else{
            $this->abnormal($this->AuthError,'获取authorizer_refresh_token失败');
        }
    }

    //提交小程序代码  微站
    public function push()
    {
        $rule  =   [
            'json'          =>  'require',
            'app_id'        =>  'require|exits:app',
        ];
        $validate = new Validate($rule,self::$msg,self::$file);
        if(!$validate->check($this->parme))
        {
            $this->abnormal($this->ValitorError,$validate->getError());
        }else{
            $authorizer_access_token    =   $this->authorizer_access_token();

            $json                       =   [
                'template_id'   =>  _config('App_weizhan_template'),
                'ext_json'      =>  $this->parme('json'),
                'user_version'  =>  _config('App_weizhan_template_version'),
                'user_desc'     =>  _config('App_weizhan_template_desc'),
            ];
            $send   =   json_encode($json);
            $data   =   json_decode(curl_request('https://api.weixin.qq.com/wxa/commit?access_token=' . $authorizer_access_token, $send),true);

            if($data['errcode']!=0)
            {
                $this->abnormal($this->AuthError,'小程序代码推送失败['.$data['errcode'].']'.$data['errmsg']);
            }else{

                $this->qrcode();
                $this->push_log(true);
                $file = Qiniu::upload('template_cover');
                if ($file['code']) {
                    $insert['template_cover']   =   $this->parme('template_cover');
                }
                $insert['auth_status']     =   1;
                $insert['app_id']          =   $this->parme('app_id');
                $insert['user_id']         =   $this->parme('user_id');
                $insert['json']            =   $this->parme('json');
                $insert['template_name']   =   $this->parme('template_name');
                $insert['create_at']       =   time();
                $this->DbSuccess(db('template')->insertGetId($insert));
                $this->datas    =   '小程序代码推送成功';
            }
        }
    }



    //提交小程序代码
    public function shop_push()
    {



        $rule  =   [
            'json'          =>  'require',
            'app_id'        =>  'require|exits:app',
        ];

        $validate = new Validate($rule,self::$msg,self::$file);
        if(!$validate->check($this->parme))
        {
            $this->abnormal($this->ValitorError,$validate->getError());
        }else{
            $authorizer_access_token    =   $this->authorizer_access_token($this->parme('app_id'));
            $type = input('type');
            if($type == 'hotel'){//酒店
                $json                       =   [
                    'template_id'   =>  _config('App_hotel_template'),
                    'ext_json'      =>  $this->parme('json'),
                    'user_version'  =>  _config('App_hotel_template_version'),
                    'user_desc'     =>  _config('App_hotel_template_desc'),
                ];
            }else{
                $json                       =   [
                    'template_id'   =>  _config('App_shop_template'),
                    'ext_json'      =>  $this->parme('json'),
                    'user_version'  =>  _config('App_shop_template_version'),
                    'user_desc'     =>  _config('App_shop_template_desc'),
                ];
            }

            $send   =   json_encode($json);
            $data   =   json_decode(curl_request('https://api.weixin.qq.com/wxa/commit?access_token=' . $authorizer_access_token, $send),true);
            if($data['errcode']!=0) {
                $this->abnormal($this->AuthError,'小程序代码推送失败['.$data['errcode'].']'.$data['errmsg']);
            }else{
                $this->qrcode();
                $this->push_log(true);
                $file = Qiniu::upload('template_cover');
                if ($file['code']) {
                    $insert['template_cover']   =   $this->parme('template_cover');
                }
                $res['app_id']  = $this->parme('app_id');
                $res['json']    = $this->parme('json');
                $res['create_at']  = time();
                $res['is_del']  = '0';
                $res['status']  = '1';
                $app_id = db('template_online')->where(array('app_id'=>$this->parme['app_id']))->value('app_id');
                if(!$app_id){
                    db('template_online')->insertGetId($res);
                }else{
                    db('template_online')->where(array('app_id'=>$app_id))->update($res);
                }


                $insert['auth_status']      =   1;
                $insert['app_id']           =   $this->parme('app_id');
                $insert['user_id']          =   $this->parme('user_id');
                $insert['json']             =   $this->parme('json');
                $insert['template_name']    =   $this->parme('template_name');
                $insert['create_at']        =   time();

                $this->DbSuccess(db('template')->insertGetId($insert));
                $this->datas    =   '小程序代码推送成功';
            }
        }

    }

    //小程序模板保存日志
    public function push_log($code=false)
    {
        $insert['json']         =   $this->parme('json');
        $insert['app_id']       =   $this->parme('app_id');
        $insert['create_at']    =   time();
        $insert['auth_status']  =   '1';
        if(db('template')->insert($insert)==false)
        {
            $this->abnormal($this->AuthError,'小程序模板推送记录保存失败');
        }else{
            if($code!=true)
            {
                $this->abnormal('小程序模板保存成功');
            }
        }

    }

    //获取授权放access_token
    public function authorizer_access_token($appid='')
    {
        if($appid!==''){
            $app_id = $appid;
        }else{
            $app_id = $this->parme('app_id');
        }
        //dd($app_id);
        if($this->redis->exists('authorizer_access_token:'.$app_id))
        {
            $authorizer_access_token    =   $this->redis->get('authorizer_access_token:'.$app_id);
        }else{
            $authorizer_access_token    =   $this->refresh_token($app_id);
        }

        return $authorizer_access_token;
    }
    //获取体验码
    public function qrcode()
    {
        $result =   file_get_contents('https://api.weixin.qq.com/wxa/get_qrcode?access_token=' . $this->authorizer_access_token()).'&path';
        $date   =   date('Ymd', time()) . '/';
        $file   =   $date . md5(uniqid()) . '.png';
        $dir    =   ROOT_PATH . 'public' . DS . 'uploads' . DS;
        if (!is_dir($dir . $date)) {
            mkdir($dir . $date, 0755, true);
        }
        if(file_put_contents($dir . $file, $result))
        {
            $data   =   Qiniu::qiniu($dir.$file,'png');
            if($data['code']==true)
            {
                if(db('app')->where('app_id',$this->parme('app_id'))->update(['qrcode_url'=>$data['file_path']]))
                {
                    $this->datas    =   $data['file_path'];
                }else{
                    $this->abnormal($this->QrcodeError,'体验二维码保存错误');
                }
            }else{
                $this->abnormal($this->QrcodeError,'体验二维码上传错误');
            }
        }else{
            $this->abnormal($this->QrcodeError,'体验二维码保存错误');
        }
    }


    //授权小程序帐号的可选类目
    public function get_category($code = false)
    {
        $authorizer_access_token    =   $this->authorizer_access_token();
        $data                       =   json_decode(curl_request('https://api.weixin.qq.com/wxa/get_category?access_token=' . $authorizer_access_token),true);
    //        dump($data);
        if($data['errcode']!='ok')
        {
            db('ceshi')->insert(['text'=>json_encode($data),'text1'=>date('Y-m-d H:i:s', time())]);
            $this->abnormal($this->AuthError,'获取授权类目失败');
        }else{
            if($code)
            {
                return $data['category_list'][0];
            }else{
                $arr_a    =   [];
                $arr_b    =   [];
                $arr_c    =   [];
                foreach ($data['category_list'] as $key => $value)
                {
                    $arr1['key']    =   $value['first_id'];
                    $arr1['name']   =   $value['first_class'];
                    if(!in_array($arr1,$arr_a))
                    {
                        $arr_a[]    =   $arr1;
                    }

                    $arr2['key']    =   $value['second_id'];
                    $arr2['name']   =   $value['second_class'];
                    $arr2['pid']    =   $value['first_id'];
                    if(!in_array($arr2,$arr_b))
                    {
                        $arr_b[]    =   $arr2;
                    }

                    if(array_key_exists('third_id',$value)){
                        $arr3['key']    =   $value['third_id'];
                        $arr3['name']   =   $value['third_class'];
                        $arr3['pid']    =   $value['second_id'];

                        if(!in_array($arr3,$arr_c))
                        {
                            $arr_c[]    =   $arr3;
                        }
                    }
                }
                $return =   [
                    $arr_a,
                    $arr_b,
                    $arr_c
                ];
                $this->datas    =   $return;
            }

        }
    }

    //获取小程序的第三方提交代码的页面配置
    public function get_page($code = false)
    {
        $authorizer_access_token    =   $this->authorizer_access_token();
        //dd($authorizer_access_token);
        $data                       =   json_decode(curl_request('https://api.weixin.qq.com/wxa/get_page?access_token=' . $authorizer_access_token),true);
        //dd($data);
        if($data['errcode']!='ok')
        {
            $this->abnormal($this->AuthError,$data);
        }else{
            if($code)
            {
                return $data['page_list'][0];
            }else{
                $this->datas    =   $data['page_list'];
            }
            $this->datas    =   $data['page_list'];
        }
    }


    //将第三方提交的代码包提交审核
    public function submit_audit()
    {
        header("Content-Type:text/html; charset=UTF-8");
        $keyword            =   $this->parme('keyword');
        $title            =  input();
        if(is_array($keyword))
        {
            $arr  =   [];
            foreach (array_filter($keyword) as $value)
            {
                if (strlen($value) <= 1 || strlen($value) >= 20) {
                    $this->abnormal($this->AuthError,'关键字长度不正确');
                }else{
                    $arr[]    =   $value;
                }
            }
        }else{
            if(substr_count($keyword,',')!=0)
            {
                $array=   array_filter(explode(',',$keyword));
                $arr  =   [];
                foreach ($array as $value)
                {
                    if (strlen($value) <= 1 || strlen($value) >= 20) {
                        $this->abnormal($this->AuthError,'关键字长度不正确');
                    }else{
                        $arr[]    =   $value;
                    }
                }
            }else{
                $arr  =   [$keyword];
            }
        }
            $key                  =   implode(' ',$arr);

        if($key=='')
        {
            $this->abnormal($this->AuthError,'关键词错误');
        }else {
            $array=[];
            $array['address']   =    $this->get_page(true);
            $array['tag']       =    $key;
            $array['title']     =    $this->parme('title',db('app')->where('app_id',$this->parme('app_id'))->value('app_name'));

            $get_category = [
                "first_class"   =>   $this->parme('first_class'),
                "second_class"  =>   $this->parme('second_class'),
                "third_class"   =>   $this->parme('third_class'),
                "first_id"      =>   $this->parme('first_id'),
                "second_id"     =>   $this->parme('second_id'),
                "third_id"      =>   $this->parme('third_id'),
            ];
            $array              =    array_merge($array,$get_category);
            $sendarr            =    json_encode(['item_list'=>[$array]],JSON_UNESCAPED_UNICODE);
            $return             =    json_decode(curl_request('https://api.weixin.qq.com/wxa/submit_audit?access_token='.$this->authorizer_access_token(),$sendarr),true);

            if( $return['errcode'] == '0' )
            {
                db('app')->where('app_id',$this->parme('app_id'))->update(['status'=>'audited','is_release'=>0]);
                $this->DbSuccess(db('app_submit')->insert([
                    'app_id'        =>  $this->parme('app_id'),
                    'auditid'       =>  $return['auditid'],
                    'create_ip'     =>  getIP(),
                    'remark_status' =>  0,
                    'create_at'     =>  time(),
                    'status'        =>  8
                ]));
            }else{
                if($return['errcode']=='86001' )
                {
                    $this->abnormal('200','已经提交的代码');
                }else if($return['errcode']=='85009')
                {
                    $this->abnormal('200','已经有正在审核的版本');
                }else{
                    switch ($return['errcode'])
                    {
                        case -1:
                            $msg    =   '系统繁忙';
                            break;
                        case 86000:
                            $msg    =   '系统错误';
                            break;
                        case 85006:
                            $msg    =   '标签格式错误';
                            break;
                        case 85007:
                            $msg    =   '页面路径错误';
                            break;
                        case 85008:
                            $msg    =   '已经有正在审核的版本';
                            break;
                        case 85009:
                            $msg    =   '标签格式错误';
                            break;
                        case 85010:
                            $msg    =   'item_list有项目为空';
                            break;
                        case 85011:
                            $msg    =   '标题填写错误';
                            break;
                        case 85023:
                            $msg    =   '审核列表填写的项目数不在1-5以内';
                            break;
                        case 85077:
                            $msg    =   '小程序类目信息失效（类目中含有官方下架的类目，请重新选择类目）';
                            break;
                        case 86002:
                            $msg    =   '小程序还未设置昵称、头像、简介。请先设置完后再重新提交。';
                            break;
                        case 85085:
                            $msg    =   '近7天提交审核的小程序数量过多，请耐心等待审核完毕后再次提交';
                            break;
                        default:
                            $msg    =   '出现未知错误['.$return['errcode'].':'.$return['errmsg'].']';
                            break;
                    }
                    $this->abnormal($this->AuthError,$msg);
                }
            }
        }
    }

    public function audit_list()
    {
//        dd($this->parme['app_id']);
        $list   =   db('app_submit')->where(['app_id'    =>  $this->parme['app_id']])->select();
        if($list)
        {
            $this->datas    =   $list;
        }else{
            $this->abnormal($this->DbNull,'暂无数据');
        }
    }

    //查询某个指定版本的审核状态（仅供第三方代小程序调用）
    public function audit_type()
    {
        if($this->parme('submit_id'))
        {
            $auditid    =   db('app_submit')->where('submit_id',$this->parme('submit_id'))->value('auditid');
        }else{
            $auditid    =   db('app_submit')->where('app_id',$this->parme('app_id'))->order('submit_id','desc')->value('auditid');
        }

        $return     =   json_decode(curl_request('https://api.weixin.qq.com/wxa/get_auditstatus?access_token='.$this->authorizer_access_token(),json_encode(['auditid'=>$auditid])),true);
        if($return['errcode']=='0')
        {
            switch ($return['status'])
            {
                case 1:
                    $type   =   '审核失败';
                    db('app')->where('app_id',$this->parme['app_id'])->update([
                        'status'    =>    'auditederror'
                    ]);
                    break;
                case 2:
                    $type   =   '审核中';
                    db('app')->where('app_id',$this->parme['app_id'])->update([
                        'status'    =>    'audited'
                    ]);
                    break;
                case 0:
                    $type   =   '审核成功';
                    db('app')->where('app_id',$this->parme['app_id'])->update([
                        'status'    =>    'auditedsuccess'
                    ]);
                    break;
            }
            $this->datas    =   $type;
        }else{
            $this->abnormal($this->AuthError,$return['errmsg']);
        }
    }

    //发布已通过审核的小程序（仅供第三方代小程序调用）
    public function release()
    {
        $return     =   json_decode(curl_request('https://api.weixin.qq.com/wxa/release?access_token='.$this->authorizer_access_token(),'{}'),true);
//        dump($return);
        if($return['errcode']=='0')
        {
            db('app')->where('app_id',$this->parme('app_id'))->update(['is_release'=>1]);
            $this->datas    =   '发布成功';

        }else{
            switch ($return['errcode'])
            {
                case -1	:
                    $msg    =   '系统繁忙';
                    break;
                case 85019	:
                    $msg    =   '没有审核版本';
                    break;
                case 85020	:
                    $msg    =   '审核状态未满足发布';
                    break;
                case 85052	:
                    $msg    =   '已经在其他平台发布过，请重新提交审核';
                    break;

                default	:
                    $msg    =   '出现未知错误['.$return['errcode'].':'.$return['errmsg'].']';
                    break;
            }
            if($return['errcode']==85052){
                db('app')->where('app_id',$this->parme('app_id'))->update(['is_release'=>1]);
            }
            db('app')->where('app_id',$this->parme('app_id'))->update(['status'=>'release']);
            $this->abnormal($this->AuthError,$msg);
        }
    }

    //小程序登录
    public function xcx_login()
    {
        $where['app_id'] = $this->parme('app_id');
        $app  =   db('app')->where($where)->find();
        //dump($app);
        $jscode = $this->parme('jscode');
        if ($jscode) {
            //dump($jscode);
            //第三方component_access_token
            $appid                   =   $app['wx_appid'];
            //dump($appid);
            $component_appid         =   $this->AppId;
            //dump($component_appid);
            $component_access_token  =   $this->token();
            //dump($component_access_token);
            $data                    =   json_decode(curl_request('https://api.weixin.qq.com/sns/component/jscode2session?appid='.$appid.'&js_code='.$jscode.'&grant_type=authorization_code&component_appid='.$component_appid.'&component_access_token='.$component_access_token.'',true),true);
            if(array_key_exists('errcode',$data))
            {
                $this->abnormal($data,'登录失败');
            }else{
                unset($where);
                $app_id             =   $this->parme('app_id');
                $where['open_id']   =   $data['openid'];
                $where['app_id']    =   $app_id;
                $back               =   db('app_member')->where($where)->find();

                if (!$back) {
                    $insert_data    =   [
                        'open_id'   =>  $data['openid'],
                        'app_id'    =>  $app_id,
                        'nick_name' =>  $this->parme('nick_name'),
                        'cover'     =>  $this->parme('cover'),
                    ];
                    $insert =   DataAnalysis::create($insert_data);
                    $id     =   db('app_member')->insertGetId($insert);
                }else{
                    $id     =   $back['member_id'];

                }

                // echo '<pre>';print_r($data);die;
                $set_mch['app_id']      =     $this->parme('app_id');
                $set_mch['var_name']    =     'mch_id';
                $set_key['app_id']      =     $this->parme('app_id');
                $set_key['var_name']    =     'key';
                $this->datas    =   [
                    'qiniu_host'    =>  _config('qiniu_host'),
                    'member_id'     =>  $id,
                    'app_id'        =>  $app_id,
                    'session_key'   =>  $data['session_key'],
                    'openid'        =>  $data['openid'],
                    'nick_name'     =>  $this->parme('nick_name'),
                    'cover'         =>  $this->parme('cover'),
                    'phone'         =>  $back['phone'],
                    'is_distribut'  =>  $app['is_distribut'],
                    'distribut_type'=>  $app['distribut_type'],
                    'distribut_num' =>  $app['distribut_num'],
                    'wx_appid'      =>  db('app')->where('app_id',$this->parme('app_id'))->value('wx_appid'),
                    'mch_id'        =>  db('app_setting')->where($set_mch)->value('var_value'),
                    'key'           =>  db('app_setting')->where($set_key)->value('var_value'),
                ];
            }
        }else{
            $this->abnormal('参数缺失,登陆失败','jscode为null');
        }
    }




//授权时获取用户信息 更新用户表
    public function updateapp(){
        $member_id = $this->parme('member_id');
        $url = $this->parme('cover');
        if(!$url)
        {
          $nick_name = '游客'.rand(1000,9999);
          db('app_member')->where(array('member_id'=>$member_id))->update(array('nick_name'=>$nick_name,'cover'=>'242bf201805110940273025.jpg'));
          die;
        }else{
          $update = [];
          $result =   file_get_contents($url);
          $date   =   date('Ymd', time()) . '/';
          $file   =   $date . md5(uniqid()) . '.png';
          $dir    =   ROOT_PATH . 'public' . DS . 'uploads' . DS;
          if (!is_dir($dir . $date)) {
              mkdir($dir . $date, 0755, true);
          }
          if(file_put_contents($dir . $file, $result))
          {
              $data   =   Qiniu::qiniu($dir.$file,'png');
              if($data['code']==true)
              {
                  $nick_name = $this->parme('nick_name');
                  $cover=   $data['file_path'];
                  $data   =      db('app_member')->where(array('member_id'=>$member_id))->find();
                  if(!$data['nick_name'])
                  {
                      $update['nick_name']    =   $nick_name;
                  }
                  if(!$data['cover'])
                  {
                      $update['cover']    =   $cover;
                  }
                  if(isset($update))
                  {
                      $this->DbSuccess($id =db('app_member')->where(array('member_id'=>$member_id))->update(array('nick_name'=>$nick_name,'cover'=>$cover)));
                  }
              }else{
                  $this->abnormal($this->QrcodeError,'体验二维码上传错误');
              }
          }else{

              $this->abnormal($this->QrcodeError,'体验二维码保存错误');
          }
        }
    }


    //信息网小程序登录
    public function xxw_login()
    {
//        dd(1212);
        $huoqu = $this->listcity($this->parme['list']);
        if(isset($huoqu['area'])){
            $provinceid = $huoqu['province'];
            $cityid     = $huoqu['city'];
            $areaid     = $huoqu['area'];
        }else{
            $provinceid = $huoqu['province'];
            $cityid     = $huoqu['city'];
            $areaid     = '0';
        }
        $app_id = db('deputy')->where(array('province'=>$provinceid,'city'=>$cityid,'area'=>$areaid))->value('app_id');
//       dd($app_id);
        $appid = db('deputy')->where(array('province'=>$provinceid,'city'=>$cityid,'area'=>$areaid))->value('wx_appid');
        $appid = trim($appid);
        $jscode = $this->parme('jscode');
        if ($jscode) {
            //第三方component_access_token
            $component_appid         =   $this->AppId;
            $component_access_token  =   $this->token();
//            dd($component_access_token);
            $data                    =   json_decode(curl_request('https://api.weixin.qq.com/sns/component/jscode2session?appid='.$appid.'&js_code='.$jscode.'&grant_type=authorization_code&component_appid='.$component_appid.'&component_access_token='.$component_access_token.'',true),true);
            //dd($data);
            if(array_key_exists('errcode',$data))
            {
                $this->abnormal($data,'登录失败');
            }else{
//                unset($where);
                $where['open_id']   =   $data['openid'];
                $where['app_id']    =   $app_id;
                $back               =   db('app_member')->where($where)->find();

                if (!$back) {
                    $insert_data    =   [
                        'open_id'   =>  $data['openid'],
                        'app_id'    =>  $app_id,
                        'nick_name' =>  $this->parme('nick_name','游客'.rand(1000,9999)),
                        'cover'     =>  $this->parme('cover','242bf201805110940273025.jpg'),
                        'provinceid'=>  $provinceid,
                        'cityid'    =>  $cityid,
                        'areaid'    =>  $areaid,
                        'identification'    =>  0,
                        'from_type' =>  '2',

                    ];
                    $insert =   DataAnalysis::create($insert_data);
                    $id     =   db('app_member')->insertGetId($insert);
                }else{
                    $id     =   $back['member_id'];
                    $provinceid    =  $provinceid;
                    $cityid    =  $cityid;
                    $areaid    =  $areaid;
                    db('app_member')->where(array('member_id'=>$id))->update(array('provinceid'=>$provinceid,'cityid'=>$cityid,'areaid'=>$areaid));

                }
                $this->datas        =   [
                    'qiniu_host'    =>  _config('qiniu_host'),
                    'member_id'     =>  $id,
                    'app_id'        =>  "$app_id",
                    'openid'        =>  $data['openid'],
                    'session_key'   =>  $data['session_key'],
                    'province'      =>  db('city')->where(array('id'=>$provinceid))->value('name'),
                    'city'          =>  db('city')->where(array('id'=>$cityid))->value('name'),
                    'wx_appid'      =>  "$appid",
                    'identification'=>  $back['identification'],
                ];
            }
        }else{
            $this->abnormal('参数缺失,登陆失败','jscode为null');
        }
    }

    public function logininfo()
    {
        $data     =   json_decode(curl_request( 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$this->authorizer_access_token().'&openid=omgTi5OzbfxAVSEFDUaxpLTnhSxY&lang=zh_CN'));
        dd($data);
    }


    public  function listcity($input){
        $city = str_replace(['[',']','\''],'',$input);
        $arr = explode(',',$city);
        foreach($arr as $k => $v ){
            if($k == 0){
                $list['province'] = db('city')->where(array('name'=>$v,'leveltype'=>'1'))->value('id');
            }
            else if($k == 1){
                $list['city']     =  db('city')->where(array('name'=>$v,'leveltype'=>'2'))->value('id');
            }else{
                $list['area'] =  db('city')->where(array('name'=>$v,'leveltype'=>'3'))->value('id');
            }

        }
        return $list;
    }

//昨日数据统计-小程序
    public function countmews()
    {

        $day = date("Ymd",strtotime("-1 day"));
        $cc = array();
        $cc['begin_date']   =    $day;
        $cc['end_date']     =    $day;

        // if($this->redis->exists('getweanalysisappiddailyvisittrend:'.$this->parme('app_id').$day))
        // {
        //     //读取redis
        //     $back_info    =   $this->redis->get('getweanalysisappiddailyvisittrend:'.$this->parme('app_id').$day);
        //     $back         =   explode(',',$back_info);

        // }else{
            //缓存时效或过期
            $back = json_decode(curl_request( 'https://api.weixin.qq.com/datacube/getweanalysisappiddailyvisittrend?access_token='.$this->authorizer_access_token(),json_encode($cc)),true);
            // $info = $back;
            // $save_info = implode(',',$info['list'][0]);
            // redis_set('getweanalysisappiddailyvisittrend:'.$this->parme('app_id').$day,$save_info);
        // }

        $this->datas    =   $back;

        /*
        ef_date 时间： 如： "20170313"
        session_cnt 打开次数
        visit_pv    访问次数
        visit_uv    访问人数
        visit_uv_new    新用户数
        stay_time_uv    人均停留时长 (浮点型，单位：秒)
        stay_time_session   次均停留时长 (浮点型，单位：秒)
        visit_depth 平均访问深度 (浮点型)
        */
    }

//月数据统计-小程序
    public function countmews_month()
    {
        $cc = array();
        $cc['begin_date']   =    date('Y-m-01', strtotime('-1 month'));
        $cc['end_date']     =    date('Y-m-t', strtotime('-1 month'));
            $back = json_decode(curl_request( 'https://api.weixin.qq.com/datacube/getweanalysisappidmonthlyvisittrend?access_token='.$this->authorizer_access_token(),json_encode($cc)),true);
        $this->datas    =   $back;

    }

    //获取授权状态
    public function authorize_status()
    {
        $rule = [
            'app_id|app_id' => 'require'
        ];
        $validate = new Validate($rule);
        if (!$validate->check($this->parme)) {
            $this->abnormal($this->ValitorError, $validate->getError());
        }else{
            $app_id=$this->parme("app_id");
            $data=db("app")->where('app_id',$app_id)->value('status');
            switch ($data)
            {
                case 'noauthorize':
                    $data       =       'noauthorize';
                    break;
                case 'authorized':
                    $data       =       'authorized';
                    break;
                case 'audited':
                    $data       =       'authorized';
                    break;
                case 'auditedsuccess':
                    $data       =       'authorized';
                    break;
                case 'auditederror':
                    $data       =       'authorized';
                    break;
                case 'release':
                    $data       =       'authorized';
                    break;
                case 'unauthorized':
                    $data       =       'noauthorize';
                    break;
                default :
                    $data       =       'noauthorize';
            }
            $this->datas=$data;
        }
    }

    public function GetIpLookup($ip = ''){
        if(empty($ip)){
            $ip = GetIp();
        }
        $res = @file_get_contents('http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=js&ip=' . $ip);

        if(empty($res)){ return false; }
        $jsonMatches = array();
        preg_match('#\{.+?\}#', $res, $jsonMatches);
        if(!isset($jsonMatches[0])){ return false; }
        $json = json_decode($jsonMatches[0], true);
        if(isset($json['ret']) && $json['ret'] == 1){
            $json['ip'] = $ip;
            unset($json['ret']);
        }else{
            return false;
        }
        return $json;
    }

    function object_array($array) {
        if(is_object($array)) {
            $array = (array)$array;
        }
        if(is_array($array)) {
            foreach($array as $key=>$value) {
                $array[$key]=$this-> object_array($value);
            }
        }
        return $array;
    }

//    // 底部导航创建
//    public function create_nav()
//    {
//        $rule  =   [
//            'json'          =>  'require',
////            'template_id'   =>  'require',
//            'app_id'        =>  'require',
//        ];
//        $validate = new Validate($rule,self::$msg,self::$file);
//        if(!$validate->check($this->parme))
//        {
//            $this->abnormal($this->ValitorError,$validate->getError());
//        }else{
////            $insert['template_id']    =   $this->parme['template_id'];
//            $insert['app_id']         =   $this->parme['app_id'];
//            $insert['json']           =   $this->parme['json'];
//            $insert['create_at']      =   time();
//            $insert['is_del']         =   0;
//            $insert['status']         =   1;
//
//            $this->DbSuccess($id      =   db("template_nav")->insertGetId($insert));
//        }
//    }
//
//    // 底部导航获取
//    public function get_nav()
//    {
//        $rule  =   [
//            'app_id'        =>  'require|exits:app',
//        ];
//
//        $validate = new Validate($rule,self::$msg,self::$file);
//        if(!$validate->check($this->parme))
//        {
//            $this->abnormal($this->ValitorError,$validate->getError());
//        }else {
//            $list = db("template_nav")
//                ->where(['app_id' =>$this->parme['app_id']])
//                ->order('create_at desc')
//                ->find();
//
//            if (count($list) > 0) {
//                $this->datas = $list;
//            } else {
//                $this->abnormal($this->DbNull, '没有数据');
//            }
//        }
//    }

    //创建子页面
    public function create_single(){
        $rule  =   [
            'json'          =>  'require',
//            'template_id'   =>  'require',
            'app_id'        =>  'require',
        ];
        $validate = new Validate($rule,self::$msg,self::$file);
        if(!$validate->check($this->parme))
        {
            $this->abnormal($this->ValitorError,$validate->getError());
        }else{
//            $insert['template_id']    =   $this->parme['template_id'];
            $insert['app_id']         =   $this->parme['app_id'];
//            $insert['user_id']        =   $this->user_id;
            $insert['json']           =   $this->parme['json'];
            $insert['template_name']  =   $this->parme['template_name'];
            $insert['create_at']      =   time();
            $insert['is_del']         =   0;
            $insert['status']         =   1;

            $this->DbSuccess($id        =   db("template_single")->insertGetId($insert));
        }
    }

    //获取子页面列表
    public function get_single_list()
    {
        $rule  =   [
            'app_id'        =>  'require|exits:app',
        ];

        $validate = new Validate($rule,self::$msg,self::$file);
        if(!$validate->check($this->parme))
        {
            $this->abnormal($this->ValitorError,$validate->getError());
        }else {
            $data = db("template_single")
                ->where(['app_id' =>$this->parme('app_id')])
                ->page(input('page', '0'), input('pageshow', 10))
                ->order('create_at desc')
                ->select();
            $count=db("template_single")->where(['app_id' =>$this->parme('app_id')] )->count();
            if($data){
                foreach ($data as $key=>$value)
                {
                    $data[$key]['jump_name']=$data[$key]['template_name'];
                    unset($data[$key]['template_name']);
                }
            }
            $list['list']=$data;
            $list['length']=$count;
            if (count($list) > 0) {
                $this->datas = $list;
            } else {
                $this->abnormal($this->DbNull, '没有数据');
            }
        }
    }

    //获取首页
    public function get_online()
    {
        $rule  =   [
            'app_id'        =>  'require|exits:app',
        ];

        $validate = new Validate($rule,self::$msg,self::$file);
        if(!$validate->check($this->parme))
        {
            $this->abnormal($this->ValitorError,$validate->getError());
        }else {
            $list = db("template_online")
                ->where(['app_id' =>$this->parme['app_id']])
                ->find();
            if ($list) {
                $this->datas = $list;
            } else {
                $this->abnormal($this->DbNull, '没有数据');
            }
        }
    }

//获取子页面
    public function get_single()
    {
        $rule  =   [
            'app_id'        =>  'require|exits:app',
        ];

        $validate = new Validate($rule,self::$msg,self::$file);
        if(!$validate->check($this->parme))
        {
            $this->abnormal($this->ValitorError,$validate->getError());
        }else {
            $list = db("template_single")
                ->where(['app_id' =>$this->parme('app_id'),'template_id'=>$this->parme('template_id')])
                ->find();
            if ($list) {
                $this->datas = $list;
            } else {
                $this->abnormal($this->DbNull, '没有数据');
            }
        }
    }

    //同步线上
    public function online()
    {
        $rule  =   [
            'app_id'             =>  'require|exits:app',
            'json'               =>  'require',
        ];

        $validate = new Validate($rule,self::$msg,self::$file);
        if(!$validate->check($this->parme))
        {
            $this->abnormal($this->ValitorError,$validate->getError());
        }else {
            $app_id         =     $this->parme('app_id');
            $json    =     $this->parme('json');
            if(db('template_online')->where(['app_id'=>$app_id])->find()){
                $res=db('template_online')->where(['app_id'=>$app_id])->update( ['json'=>$json,'update_at'=>time()] );
            }else{
                $res=db('template_online')->insert( ['json'=>$json,'app_id'=>$app_id,'create_at'=>time()] );
            }

            $this->datas=$res;
        }
    }

    // 导航图标
    public function get_nav_icon()
    {
        if($this->parme('grouptype') == '餐饮'){
            $file_group_id = 260;
        }else{
            $file_group_id = 124;
        }
        $data=db('manage_file')->where('file_group_id',$file_group_id)->page(input('page', '0'), input('pageshow', 10))->select();
        $count=db('manage_file')->where('file_group_id',$file_group_id)->count();
        $list['list']=$data;
        $list['length']=$count;
        if (count($list) > 0) {
            $this->datas = $list;
        } else {
            $this->abnormal($this->DbNull, '没有数据');
        }
    }

    public function qiniu_shuiyin()
    {
        $video          =    "f6f09201806021517428244.mp4";
        $img            =    "http://o6wnztyd7.bkt.clouddn.com/22254201807061427376623.png";
        $shuiyin        =    Qiniu::watermark($video,$img);
        $this->datas    =    $shuiyin;
    }

    public function notify()
    {
        $res=(file_get_contents("php://input"));
        db('ceshi')->insert(['text'=>$res,'text1'=>date('Y-m-d H:i:s',time())]);

//        $res=json_decode(file_get_contents("php://input"));
//        $res=$this->object_array($res);
//        db('ceshi')->insert(['text'=>$res['code'].'-'.$res['items'][0]['key'],'text1'=>date('Y-m-d H:i:s',time())]);
    }
    //文章 列表
    public function all_lists(){
        $rule = [
            'app_id'    => 'require',
        ];
        $field = [
            'app_id'    => '应用ID',
        ];
        $where['app_id']  = $this->parme('app_id');
        $validate = new Validate($rule,$field);
        if(!$validate->check($this->parme)){
            $this->abnormal($this->ValitorError,$validate->getError());
        }else{
            $where['del']     = '0';
            $where['type']    =  'shop';

            $list = db('article')->where($where)->order('orderorder desc')->page(input('page','1'),input('pageshow',1000))->select();
            foreach($list as $k => $v){
                $list[$k]['create_at'] = date('Y-m-d',$v['create_at']);
            }
            $count = db('article')->where($where)->order('orderorder desc')->count();
            if($list){
                $arr = array('list'=>$list,'count'=>$count);
                $this->datas = $arr;
            }else{
                $this->abnormal($this->DbNull,'数据不存在');
            }
        }
    }

    //文章详情
    public function get_one(){
        $rule = [
            'article_id'      => 'require',
        ];
        $field = [
            'article_id'      => '文章ID',
        ];
        $validate = new Validate($rule,$field);
        if(!$validate->check($this->parme)){
            $this->abnormal($this->ValitorError,$validate->getError());
        }else{
            $where['del']        = '0';
            $where['article_id'] = $this->parme['article_id'];

            $get = db('article')->where($where)->field('create_at,title,value,cover,article_id')->find();
            if($get){
                $this->datas = $get;
            }else{
                $this->abnormal($this->DbNull,'数据不存在!');
            }
        }
    }

    //文章评论列表
    public function comment_list(){
        $rule = [
            'article_id'    => 'require',
            'app_id'        => 'require',
        ];
        $field = [
            'article_id'    => '文章ID',
            'app_id'        => '应用ID',
        ];
        $validate = new Validate($rule,$field);
        if(!$validate->check($this->parme)){
            $this->abnormal($this->ValitorError,$validate->getError());
        }else{
            $where['authorize_id'] = $this->parme('article_id');
            $where['comment_type'] = '5';
            $where['del']          = '0';
            $where['app_id']       = $this->parme('app_id');
            $list  = db('comment')->where($where)->page(input('page','1'),input('pageshow',1000))->select();
            foreach($list as $k => $v){
                $list[$k]['create_at'] = date('Y-m-d',$v['create_at']);
                $list[$k]['nick_name'] = db('app_member')->where(array('member_id'=>$v['member_id']))->value('nick_name');
                $list[$k]['cover']     = db('app_member')->where(array('member_id'=>$v['member_id']))->value('cover');
            }
            $count =db('comment')->where($where)->count();
            if($list){
                $arr = array('list'=>$list,'count'=>$count);
                $this->datas = $arr;
            }else{
                $this->abnormal($this->DbNull,'数据不存在!');
            }
        }

    }

    //评论页面
    public function my_comment(){
        $rule = [
            'article_id'   => 'require',
        ];
        $field = [
            'article_id'   => '文章ID',
        ];
        $validate = new Validate($rule,$field);
        if(!$validate->check($this->parme)){
            $this->abnormal($this->ValitorError,$validate->getError());
        }else{
            $title     =  db('article')->where(array('article_id'=>$this->parme['article_id']))->value('title');
            $where['del'] = '0';
            $where['authorize_id']  = $this->parme['article_id'];
            $where['comment_type']  = '5';
            $where['app_id']        = $this->parme['app_id'];
            $where['member_id']     = $this->parme['member_id'];
            $mycomment = db('comment')->where($where)->select();
            foreach($mycomment as $k => $v){
                $mycomment[$k]['create_at'] = date('Y-m-d',$v['create_at']);
                $mycomment[$k]['nick_name'] = db('app_member')->where(array('member_id'=>$v['member_id']))->value('nick_name');
                $mycomment[$k]['cover']     = db('app_member')->where(array('member_id'=>$v['member_id']))->value('cover');
            }
            $arr = array('mycomment'=>$mycomment,'title'=>$title);
            if($arr){
                $this->datas = $arr;
            }else{
                $this->abnormal($this->DbNull,'数据不存在!');
            }
        }
    }

    //添加评论
    public function add_comment(){
        $rule = [
            'app_id'       => 'require',
            'member_id'    => 'require',
            'article_id'   => 'require',
            'content'      => 'require',
        ];
        $field = [
            'member_id'    => '用户ID',
            'article_id'   => '文章ID',
            'content'      => '评论内容',
            'app_id'       => '应用ID',
        ];
        $validate = new Validate($rule,$field);
        if(!$validate->check($this->parme)){
            $this->abnormal($this->ValitorError,$validate->getError());
        }else{
            $insert['authorize_id'] = $this->parme('article_id');
            $insert['member_id']    = $this->parme('member_id');
            $insert['app_id']       = $this->parme('app_id');
            $insert['content']      = $this->parme('content');
            $insert['comment_type'] = '5';
            $insert['create_at']    = time();
            $insert['del']          = '0';
            $this->DbSuccess($id=   db('comment')->insert($insert));
        }
    }

    //删除评论
    public function del_comment(){
        $rule = [
            'app_id'       => 'require',
            'comment_id'   => 'require',
        ];
        $field = [
            'comment_id'   => '评论ID',
            'app_id'       => '应用ID',
        ];
        $validate = new Validate($rule,$field);
        if(!$validate->check($this->parme)){
            $this->abnormal($this->ValitorError,$validate->getError());
        }else{
            $comment_id           =  $this->parme('comment_id');
            $where['del']         =  1;
            $where['delete_at']   =  time();

            $this->DbSuccess($id  =  db('comment')->where('comment_id',$comment_id)->update($where) );
        }
    }

    public function wifi_tokens()
    {
            $arr_info = array('callback_url' => 'http://www.ztwlxx.club/league' );
            $json = json_encode($arr_info);
            $component_access_token =   $this->token();
            $callback   =   json_decode(curl_request('https://api.weixin.qq.com/bizwifi/openplugin/token?access_token=' . $component_access_token,$json),true);
dd($callback);
    }

    //版本检测
    public function check_version()
    {
        $app_id['app_id']=$this->parme('app_id');
        $data = db('app')->where($app_id)->value('shop_version');
        if($this->parme('type') =="hotel"){
            $new  = _config('App_hotel_template_version');
        }else{
            $new  = _config('App_shop_template_version');
        }


        $version['version']=$data;
        if( $data != $new){
            $version['new_version']=$new;
            $version['data']="请更新版本";
            $this->datas=$version;
        }else{
            $version['data']="已经是最新版本";
            $this->datas=$version;
        }
    }

    //更新版本
    public function update_version()
    {

        $data=db('template')
            ->where('app_id',$this->parme('app_id'))
            ->order('template_id','desc')
            ->find();

        if(!$data){
            $this->abnormal($this->DbNull,'数据不存在,请先去保存!');
        }
        $json['json']  =$data['json'];
        $json['app_id']= encode($this->parme('app_id'));
        $res=$this->object_array(json_decode(curl_request('http://api.ztwlxx.club/wechat/Authorize/shop_push',$json)));
//        dump($res);
        if($res['msg']=="操作成功"){
            if($this->parme('type') == 'hotel'){//酒店
                db('app')->where('app_id',$this->parme('app_id') )->update(['shop_version'=>_config('App_hotel_template_version')] );
            }else{
                db('app')->where('app_id',$this->parme('app_id') )->update(['shop_version'=>_config('App_shop_template_version')] );
            }

        }
        $this->datas=$res;
    }

    public function redis_notify()
    {

        $this->redis->setex('out_time:32-17',1,1);
        dump(1);

    }

    public function redis_demo()
    {

        dump(encrypt('1234567890'));

    }

    //获取分享名片

    public function  getsharecode(){
        //查询
        $list =  db('app')->where(['app_id'=>$this->parme('app_id')])->field('share_img,share_title')->find();
        if(!empty($list) && $this->parme('title') === $list['share_title']){
            $data = $list['share_img'];
        }else{
            $send = json_encode([
                'scene' => 'aaa',
                'page' => 'pages/allShopList/allShopList',
                'width' => '180',
            ]);
            $app_id = $this->parme('app_id');
            //dd($app_id);
            $authorizer_access_token = $this->refresh_token($app_id);
            //dd($authorizer_access_token);
            $data = curl_request('https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . $authorizer_access_token, $send);
            //dd($data);
            $base = base64_encode($data);
            if ($base) {
                $imageName = "25220_" . date("His", time()) . "_" . rand(1111, 9999) . '.png';
                $path = "./uploads/Qrcode";
                if (!is_dir($path)) { //判断目录是否存在 不存在就创建
                    mkdir($path, 0777, true);
                }
                $imageSrc = $path . "/" . $imageName;  //图片名字
                $r = file_put_contents(ROOT_PATH . "public/" . $imageSrc, base64_decode($base));//返回的是字节数
                $url = ROOT_PATH . "public/" . $imageSrc;

                $file = Qiniu::qiniu($url, 'png');
                //dd($file['file_path']);
            }
            //文字内容

            $windowHeight = $this->parme('width')*1334/750;
            $windowWidth = $this->parme('width');
            $text = $this->parme('title') ? $this->parme('title') : '中天小程序';
            $data = $this->imagemerge($file['file_path'], $windowWidth, $windowHeight, $text);
            db('app')->where(['app_id' => $this->parme('app_id')])->update(['share_img' => $data,'share_title'=>$text]);
        }
        $res = array('image'=>$data);
        $this->datas = $res;
    }

    //给辛伟的  生成太阳码
    public function xinweigetcode(){
        if(input('app_id') > 0){
            $app_id = input('app_id');
        }else{
            $app_id = $this->parme('app_id');
        }
        $pages = $this->parme('pages');
        $scene = $this->parme('scene');
        $send =json_encode([
            'scene'     =>      $scene,
            'page'      =>      $pages,
            'width'     =>      '45',
        ]);
        $authorizer_access_token      =    $this->refresh_token($app_id);
//        dd($authorizer_access_token);
        $data   =   curl_request('https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token='.$authorizer_access_token,$send);
        $base   =   $data;
//        dd($base);
        if($base){
            $imageName = "25220_".date("His",time())."_".rand(1111,9999).'.png';
            $path = "./uploads/Qrcode";
            if (!is_dir($path)){ //判断目录是否存在 不存在就创建
                mkdir($path,0777,true);
            }
            $imageSrc=  $path."/". $imageName;  //图片名字
            $r = file_put_contents(ROOT_PATH ."public/".$imageSrc,$base);//返回的是字节数
            $url = ROOT_PATH ."public/".$imageSrc;
            $file   =   Qiniu::qiniu($url,'png');
//                dd($file['file_path']);
            $this->datas = $file['file_path'];
        }
    }

    ///合图
    public function imagemerge($source,$windowWidth,$windowHeight,$text){
        //获取七牛上的图片
        $source1 = 'https://o6wndwjxn.qnssl.com/'.$source;//二维码
        //$source1 = $source;
        $source2 = 'https://o6wndwjxn.qnssl.com/0c9fa201808220931344462.png';//背景
        //获取图片名
        $name  = basename($source1);
        $name2 = basename($source2);

        //设置下载上传路径
        $dir = str_replace('\\','/',ROOT_PATH.'public/uploads/Qrcode');

        $path1 = $dir.'/img3_'.$name;
        $path2 = $dir.'/img4_'.$name2;

        //保存到本地并获取资源
        $res = file_put_contents($dir.'/'.$name,file_get_contents($source1));
        if($res){
            $source = getimagesize($dir.'/'.$name);
        }else{
            die('wrong');
        }
        $res = file_put_contents($dir.'/'.$name2,file_get_contents($source2));
        if($res){
            $source2 = getimagesize($dir.'/'.$name2);
        }else{
            die('wrong');
        }
        //通过编号来获取图片类型
        $type1 = image_type_to_extension($source[2],false);
        $type2 = image_type_to_extension($source2[2],false);
        //在内存中建立一个和图片类型一样的图像
        $func1 = "imagecreatefrom{$type1}";
        $func2 = "imagecreatefrom{$type2}";
        //获取图片宽高
        list($img1x,$img1y) = $source;
        $image1 = $func1($dir.'/'.$name);
        list($img2x,$img2y) = $source2;
        $image2 = $func2($dir.'/'.$name2);
        $img3x  = $img1x > 190*1.2 ?190*1.2:$img1x;
        $img3y  = $img1y > 190*1.2 ?190*1.2:$img1y;

        //创建一个真彩画布
        $image3 = imageCreatetruecolor($img1x,$img1y);
        $color = imagecolorallocate($image3, 255, 255, 255);
        $color=imagecolortransparent($image3,$color);


        imageColorTransparent($image3, $color);
        imagefill($image3, 0, 0, $color);
        //将原图复制到新的真彩画布上，并按比例缩放
        imagecopyresampled($image3, $image1, 0, 0, 0, 0,$img3x, $img3y, $img1x, $img1y);
        //删除原图
        imagedestroy($image1);
        $func3 = "image{$type1}";
        $func3($image3,$path1);
        imagedestroy($image3);
//        $img4x  = $img2x > 750?750:$img2x;
//        $img4y  = $img2y > 996 ?996:$img2y;
        $img4x = $windowWidth*2;
        $img4y = $windowHeight*2;
//        var_dump($img4x);
//        var_dump($img4y);die;
        $image4 = imageCreatetruecolor($img4x,$img4y);
        $color = imagecolorallocate($image4, 255, 0, 0);
        $color=imagecolortransparent($image4,$color);
        imageColorTransparent($image4, $color);
        imagefill($image4, 0, 0, $color);
        imagecopyresized($image4, $image2, 0, 0, 0, 0,$img4x, $img4y, $img2x, $img2y);
        imagedestroy($image2);
        $func4 = "image{$type2}";
        $func4($image4,$path2);
        imagedestroy($image4);
        //获取图片
        $image_thumb = $func2($path2);
        $image = $func1($path1);
        //复制图片一到真彩画布中（重新取样-获取透明图片）
        $r = imagecopymerge($image_thumb, $image, $img4x-$img3x-60, (9/10*$img4y-$img3y/2), 0, 0,$img3x, $img3y,100);
        if($r){
            $newname = substr($name,0,16).substr($name2,16,strlen($name2));
            $res = $func4($image_thumb,$dir.'/'.$newname);
            if($res){
                imagedestroy($image_thumb);
                imagedestroy($image);
                $dst = imagecreatefrompng($dir.'/'.$newname);
                //var_dump($dst);
                //打上文字
                $font = VENDOR_PATH . 'topthink/think-captcha/assets/zhttfs/1.ttf';//字体
                $black = imagecolorallocatealpha($dst, 255, 255, 255,0);//字体颜色
                $x = $img4x/4;
                $size = 30;
                $y = $img4y/4;
                $__string='';
                $newtext = '';
                //var_dump(mb_strlen($text));
                $len = 0;
                for($i=0;$i<mb_strlen($text);$i++)
                {
                    $box=imagettfbbox($size,0,$font,$text);
                    //var_dump($box);
                    //$_string_length=$box[2]-$box[0];
                    //var_dump($_string_length);
                    //var_dump(333);
                    $box=imagettfbbox($size,0,$font,trim(str_replace('<br>',' ',mb_substr($text,$i,1))));
//var_dump($box);
                    $len += ($box[2]-$box[0]);

                    if($len<$windowWidth)
                    {
                        //var_dump($len);
                        $newtext.= trim(str_replace('<br>',' ',mb_substr($text,$i,1)));
                        //var_dump($newtext);
                        //var_dump(111);
                    }
                    else
                    {
                        //var_dump($windowWidth);
                        $__string.=$newtext."\n";
                        //$newtext = '';
                        $newtext = trim(str_replace('<br>',' ',mb_substr($text,$i,1)));
                        $len = 0;
                        //var_dump($newtext);
                        //var_dump(222);
                    }
                }
                $__string.=$newtext;
                //dd($__string);
                //$box=imagettfbbox($size,0,$font,mb_substr($__string,0,1));
                imagefttext($dst, $size, 0, $x, $y, $black, $font, $__string);
                //imagefttext($dst, $size, 0, $x, $y, $black, $font, $text);
                $savepath = str_replace('\\','/',ROOT_PATH.'public/uploads/Qrcode');
                $filename = time().rand(000,999).basename($dir.'/'.$newname);
                //dd($filename);
                imagepng($dst,$savepath."/".$filename);
                imagedestroy($dst);
                $file   =   Qiniu::qiniu($savepath."/".$filename,'png');
                if($file['code'] == true){
                    $arr = array(
                        $dir.'/'.$name,
                        $dir.'/'.$name2,
                        $path1,
                        $path2,
                        $dir.'/'.$newname
                    );
                    $this->delimage($arr);
                    return $file['file_path'];
//                    dd($file['file_path']);
                }
            }else{
                die('wrong');
            }
        }else{
            die('wrong');
        }
    }

    private function delimage($arr)
    {
        foreach ($arr as $v){
            if(file_exists($v)){
                unlink($v);
            }
        }
    }

    //酒店程序登录
    public function xcx_login_hotel()
    {
        $where['app_id'] = $this->parme('app_id');
        $app  =   db('app')->where($where)->find();
        $jscode = $this->parme('jscode');
        if ($jscode) {
            //dump($jscode);
            //第三方component_access_token
            $appid                   =   $app['wx_appid'];
            //dump($appid);
            $component_appid         =   $this->AppId;
            $component_access_token  =   $this->token();
//            dd($component_appid);
            $data                    =   json_decode(curl_request('https://api.weixin.qq.com/sns/component/jscode2session?appid='.$appid.'&js_code='.$jscode.'&grant_type=authorization_code&component_appid='.$component_appid.'&component_access_token='.$component_access_token.'',true),true);

            if(array_key_exists('errcode',$data))
            {
                $this->abnormal($data,'登录失败');
            }else{
                unset($where);
                $app_id             =   $this->parme('app_id');
                $where['open_id']   =   $data['openid'];
                $where['app_id']    =   $app_id;
                $back               =   db('app_member')->where($where)->find();

                if (!$back) {
                    $insert_data    =   [
                        'open_id'   =>  $data['openid'],
                        'app_id'    =>  $app_id,
                        'nick_name' =>  $this->parme('nick_name','游客'.rand(1000,9999)),
                        'cover'     =>  $this->parme('cover','242bf201805110940273025.jpg'),
                        'from_type' =>  '4',
                    ];
                    $insert =   DataAnalysis::create($insert_data);
                    $id     =   db('app_member')->insertGetId($insert);
                }else{
                    $id     =   $back['member_id'];

                }

                // echo '<pre>';print_r($data);die;
                $set_mch['app_id']      =     $this->parme('app_id');
                $set_mch['var_name']    =     'mch_id';
                $set_key['app_id']      =     $this->parme('app_id');
                $set_key['var_name']    =     'key';
                $this->datas    =   [
                    'qiniu_host'    =>  _config('qiniu_host'),
                    'member_id'     =>  $id,
                    'app_id'        =>  $app_id,
                    'session_key'   =>  $data['session_key'],
                    'openid'        =>  $data['openid'],
                    'nick_name'     =>  $this->parme('nick_name'),
                    'cover'         =>  $this->parme('cover'),
                    'phone'         =>  $back['phone'],
//                    'is_distribut'  =>  $app['is_distribut'],
//                    'distribut_type'=>  $app['distribut_type'],
//                    'distribut_num' =>  $app['distribut_num'],
                    'wx_appid'      =>  db('app')->where('app_id',$this->parme('app_id'))->value('wx_appid'),
//                    'mch_id'        =>  db('app_setting')->where($set_mch)->value('var_value'),
//                    'key'           =>  db('app_setting')->where($set_key)->value('var_value'),
                ];
            }
        }else{
            $this->abnormal('l,登陆失败','jscode为null');
        }
    }

}
