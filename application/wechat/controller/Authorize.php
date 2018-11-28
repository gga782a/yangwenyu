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

class Authorize extends Controller
{
    public $appId;
    public $appSecret;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->appId     = _config('AppId');
        $this->appSecret = _config('AppSecret');
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
        //dd($url);
        header("location:".$url);
    }

    public function get_url_s($code='')
    {
        if(empty($code)){
            $code = input('code');
        }
        //获取网页授权token  openid
        $data = json_decode($this->get_access_token($this->appId,$this->appSecret,$code),true);

dd($data);
        //获取微信
        $access_token = $this->get_access_token_s();
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
        //dd($data_token);
        if(array_key_exists('errcode',$data_token)){
            //dd($data_token->access_token);
            return json_encode(['code'=>$data_token->errcode,'data'=>$data_token->errmsg]);
            //return json_encode(['code'=>'404','msg'=>'shshs']);
        }else{
            $gtime = 28*86400;
            cache('access_token',$data_token->access_token,7000);
            cache('refresh_token',$data_token->refresh_token,$gtime);
            return json_encode(['code'=>'200','data'=>$data_token]);
        }

    }
    //更新access_token
//    public function get_access_token_s()
//    {
//        //判断缓存是否过期
//        if(cache('access_token'))
//        {
//            $access_token = cache('access_token');
//        }else{
//            //判断refresh_token过期没
//            if(cache('refresh_token')){
//                $refresh_token = cache('refresh_token');
//                $url = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid={$this->appId}&grant_type=refresh_token&refresh_token={$refresh_token}";
//                $data_token = json_decode(curl_request($url,true));
//                if(array_key_exists('errcode',$data_token)){
//                    //dd($data_token->access_token);
//                    exit(json_encode(['code'=>$data_token->errcode,'msg'=>$data_token->errmsg]));
//                }else{
//                    cache('access_token',$data_token->access_token,7000);
//                    $access_token = $data_token->access_token;
//                }
//            }
//            $access_token = $token_data->access_token;
//        }
//
//
//
//        return $access_token;
//
//
//    }
}