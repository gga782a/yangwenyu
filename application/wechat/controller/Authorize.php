<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/15
 * Time: 上午9:33
 */

namespace app\wechat\controller;
use think\Controller;
use think\Request;
use think\Session;

class Authorize extends Controller
{
    public $appId;
    public $appSecret;
    public $id;
    public static $table_member = 'member';

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->appId     = _config('AppId');
        $this->appSecret = _config('AppSecret');
        $this->id        = Session::get('user_id');
    }
    //第一步：用户同意授权，获取code

    /**
     * @param string $redirect_uri
     * @param string $state
     */
    public function get_url($redirect_uri ='',$state='STATE')
    {
        $redirect_uri = $redirect_uri?$redirect_uri:'http://www.yilingjiu.cn/wechat/authorize/get_url_s';
        $redirect_uri = urlencode($redirect_uri);
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$this->appId."&redirect_uri=".$redirect_uri."&response_type=code&scope=snsapi_userinfo&state={$state}#wechat_redirect";
        dd($url);
        header("location:".$url);
    }

    public function get_url_s($code='')
    {
        if(empty($code)){
            $code = input('code');
        }
        //获取网页授权token  openid
        $data = json_decode($this->get_access_token($this->appId,$this->appSecret,$code),true);
        if($data['code'] == 200){
            $openid = $data['data']['openid'];
            $accesstoken = $data['data']['access_token'];
        }else{
            //刷新access_token
            $accesstoken_s = json_decode($this->get_access_token_s(),true);
            if($accesstoken_s['code'] != 200){
                $this->error($this->get_access_token_s());
            }else{
                $access_token_s = $accesstoken_s['data'];
            }
        }
        $access_token=$accesstoken?$accesstoken:$access_token_s;
        //根据openid获取用户信息
        $user = json_decode($this->get_user($access_token,$openid),true);
        if($user['code'] != 200){
            $this->error($this->get_user($access_token,$openid));
        }else{
            $userdata = $user['data'];
        }
        //dd($userdata);
        $where = [
            'app_id' => $this->id,
            'openid' => $userdata['openid'],
            'status' => 1,
        ];
        $userone = db(self::$table_member)->where($where)->find();
        //获取用户信息
        if(!empty($userone)){
            $member_id = $userone['member_id'];
        }else{
            //插入数据到数据库 用户不存在添加
            $ini['openid'] = $userdata['openid'];
            $ini['app_id'] = $this->id;
            $ini['name']   = $userdata['nickname'];
            $ini['cover']  = $userdata['headimgurl'];
            $ini['updated_at'] = time();
            $ini['created_at'] = time();
            $ini['status']    = 1;
            $member_id    = db(self::$table_member)->insertGetId($ini);
        }
        $url = 'http://www.yilingjiu.cn/index/common/check?member_id='.$member_id;
        header("location:".$url);
        //dd($member_id);


        //dd($access_token);
    }

    /**
     * @param $appId
     * @param $appSecret
     * @param $code
     * @return mixed
     */
    //获取网页授权token  openid
    public function get_access_token($appId ='',$appSecret='',$code='')
    {
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$this->appId."&secret=".$this->appSecret."&code={$code}&grant_type=authorization_code";
        $data_token = json_decode(curl_request($url,true));
        if(array_key_exists('errcode',$data_token)){
            return json_encode(['code'=>$data_token->errcode,'data'=>$data_token->errmsg]);
        }else{
            $gtime = 28*86400;
            cache('access_token',$data_token->access_token,7000);
            cache('refresh_token',$data_token->refresh_token,$gtime);
            return json_encode(['code'=>'200','data'=>$data_token]);
        }

    }
    //更新access_token

    /**
     * @return mixed|string
     */
    public function get_access_token_s()
    {
        //判断缓存是否过期
        if(cache('access_token'))
        {
            $access_token = cache('access_token');
        }else{
            //判断refresh_token过期没
            if(cache('refresh_token')){
                $refresh_token = cache('refresh_token');
                $url = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid={$this->appId}&grant_type=refresh_token&refresh_token={$refresh_token}";
                $data_token = json_decode(curl_request($url,true));
                if(array_key_exists('errcode',$data_token)){
                    return json_encode(['code'=>$data_token->errcode,'msg'=>$data_token->errmsg]);
                }else{
                    cache('access_token',$data_token->access_token,7000);
                    $access_token = $data_token->access_token;
                }
            }else{
                return json_encode(['code'=>'400','msg'=>'请重新授权']);
            }
        }
        return json_encode(['code'=>'200','data'=>$access_token]);
    }
    //获取用户基本信息
    public function get_user($access_token='',$openid='')
    {
        $http = "https://api.weixin.qq.com/sns/userinfo?access_token={$access_token}&openid={$openid}&lang=zh_CN";
        $data = json_decode(curl_request($http));
        //dd($data);
        if(array_key_exists('errcode',$data)){
            //dd($data_token->access_token);
            return json_encode(['code'=>$data->errcode,'msg'=>$data->errmsg]);
        }else{
            return json_encode(['code'=>'200','data'=>$data]);
        }
    }
}