<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/15
 * Time: 上午9:33
 */

namespace app\wechat\controller;
use think\Controller;
use think\Cookie;
use think\Request;
use think\Session;

class Authorize extends Controller
{
    public $appId;
    public $appSecret;
    public static $table_member = 'member';

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->appId     = _config('AppId');
        $this->appSecret = _config('AppSecret');
    }
    //第一步：用户同意授权，获取code

    /**
     * @param string $redirect_uri 授权回调地址
     * @param string $state
     */
    public function get_url($redirect_uri ='',$state='STATE')
    {
        //dd(111);
        //dd(input('redirecturl'));
        $redirect_uri = $redirect_uri ? $redirect_uri : 'http://www.yilingjiu.cn/wechat/authorize/get_url_s?redirecturl='.input('redirecturl');
        $redirect_uri = urlencode($redirect_uri);
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . $this->appId . "&redirect_uri=" . $redirect_uri . "&response_type=code&scope=snsapi_userinfo&state={$state}#wechat_redirect";
        //dd($url);
        return $this->redirect($url);
    }
    //回调
    /**
     * @param string $code
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_url_s($code='')
    {
        if(empty($code)){
            $code = input('code');
        }
        //dd($code);
        //获取网页授权token  openid
        $data = $this->get_access_token($this->appId,$this->appSecret,$code);
        //dd($data);
        $openid = $data->openid;

        $refresh_token = $data->refresh_token;

        //获取access_token
        $access_token = $this->get_access_token_s($refresh_token);

        //根据openid获取用户信息
        $userdata = $this->get_user($access_token,$openid);
        //dd($userdata);
        $where = [
            'openid' => $userdata->openid,
            'status' => 1,
        ];
        $userone = db(self::$table_member)->where($where)->find();
        //dd($userone);
        //获取用户信息
        if(!empty($userone)){
            $member_id = $userone['member_id'];
        }else{
            //dd($userdata);
            //插入数据到数据库 用户不存在添加
            $ini['openid'] = $userdata->openid;
            //$ini['app_id'] = $this->id;
            $ini['name']   = $userdata->nickname;
            $ini['cover']  = $userdata->headimgurl;
            $ini['updated_at'] = time();
            $ini['created_at'] = time();
            $ini['status']    = 1;
            //dd($ini);
            $member_id    = db(self::$table_member)->insertGetId($ini);
        }
        if($member_id){
            //dd($member_id);
            Session::set("member_id",$member_id);
            if(input('redirecturl')){
                $url = 'http://www.yilingjiu.cn/index/index/'.input('redirecturl').'?member_id='.$member_id;
            }else{
                $url = 'http://www.yilingjiu.cn/index/index/index?member_id='.$member_id;
            }
            //dd($url);
            return $this->redirect($url);
        }else{
            return $this->error();
        }
    }
    //获取网页授权token  openid

    /**
     * @param $appId
     * @param $appSecret
     * @param $code
     * @return mixed
     */
    public function get_access_token($appId ='',$appSecret='',$code='')
    {
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$this->appId."&secret=".$this->appSecret."&code={$code}&grant_type=authorization_code";
        $data_token = json_decode(curl_request($url,true));
        //var_dump($data_token);
        if(array_key_exists('errcode',$data_token)){
            return $this->error(json_encode(['errcode'=>$data_token->errcode,'data'=>$data_token->errmsg]));
        }else{
            cache('access_token',$data_token->access_token,7000);
            return $data_token;
        }

    }
    //更新access_token

    /**
     * @return mixed|string
     */
    public function get_access_token_s($refresh_token='')
    {
        //判断缓存是否过期
        if(cache('access_token'))
        {
            //dd(11);
            $access_token = cache('access_token');
        }else{
            $url = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid={$this->appId}&grant_type=refresh_token&refresh_token={$refresh_token}";
            $data_token = json_decode(curl_request($url,true));
            if(array_key_exists('errcode',$data_token)){
                return $this->error(json_encode(['errcode'=>$data_token->errcode,'msg'=>$data_token->errmsg]));
            }else{
                cache('access_token',$data_token->access_token,7000);
                $access_token = $data_token->access_token;
            }
        }
        return $access_token;
    }

    //获取用户基本信息

    /**
     * @param string $access_token
     * @param string $openid
     * @return mixed|void
     */
    public function get_user($access_token='',$openid='')
    {
        $http = "https://api.weixin.qq.com/sns/userinfo?access_token={$access_token}&openid={$openid}&lang=zh_CN";
        $data = json_decode(curl_request($http));
        if(array_key_exists('errcode',$data)){
            return $this->error(json_encode(['errcode'=>$data->errcode,'msg'=>$data->errmsg]));
        }else{
            return $data;
        }
    }
}