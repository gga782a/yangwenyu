<?php
namespace app\wechat\controller;
use app\wechat\Controller;

class Index extends controller
{
    public $AppId;
    public $secret;
    public $redis;
    public $ticket;
    public $token;
    public $AesKey;
    public $post_xml;
    public function __construct()
    {
        parent::__construct();
        $this->AppId    =   _config('AppID');
        $this->secret   =   _config('AppSecret');
        $this->redis    =   redis();
        $this->AesKey   =   _config('AesKey');
        $this->ticket   =   $this->redis->get('app_ticket');
    }

    public function ticket(){
        require ROOT_PATH . 'weixin/pkcs7Encoder.php';
        $text       =   file_get_contents("php://input");
//        db('ceshi')->insert(['text'=>json_encode($text),'text1'=>23333]);
        $Prpcrypt   =   new \Prpcrypt($this->AesKey);
        $xml        =   new \DOMDocument();
        $xml->loadXML($text);
        $array_e    =   $xml->getElementsByTagName('Encrypt');
        $encrypt    =   $array_e->item(0)->nodeValue;
        $data       =   $Prpcrypt->decrypt($encrypt, $this->AppId);

        if (!empty($data[1]) && $data[0] == 0) {
            $xmlArr = simplest_xml_to_array($data[1]);
//            dump($xmlArr);
            if(!empty($xmlArr['ComponentVerifyTicket'])){
                db('config')->where('config_var','App_auth')->update(['config_value'=>json_encode($xmlArr,JSON_UNESCAPED_UNICODE)]);
                redis_set('app_ticket',$xmlArr['ComponentVerifyTicket']);
                $this->Returned = TRUE;
//                dump($xmlArr);
                die("success");
            }else if(array_key_exists('InfoType',$xmlArr))
            {

                $appid          =     $xmlArr['AuthorizerAppid'];
                $infotype       =     strtolower($xmlArr['InfoType']);
                $status         =     $infotype;
                if($infotype    ==    'updateauthorized' ){
                    $infotype   =     'authorized';
                }
                db('app')->where('wx_appid',$appid)->update(['status'=> $infotype ]);

                $remark_status=0;
                if($status          ==   'unauthorized'){
                    $status         =    4;
                    $remark_status  =    1;
                }
                if($status          ==   'updateauthorized'){
                    $status         =    3;
                }
                if($status          ==   'authorized'){
                    $status         =    1;
                }
                $ip=getIP();
                $time               =    $xmlArr['CreateTime'];
                $app_id             =    db('app')->where('wx_appid',$appid)->find();
//                db('ceshi')->insert(['text'=>json_encode($xmlArr),'text1'=>99999999999]);
                $data=db('app_submit')->insert(['status'=>$status,'app_id'=>$app_id['app_id'],'create_at'=>$time,'create_ip'=>$ip,'remark_status'=>$remark_status]);
                $this->datas=$data;
            }
        }
    }

    public function event()
    {
        require ROOT_PATH . 'weixin/pkcs7Encoder.php';
        $text       =   file_get_contents("php://input");
//        db('ceshi')->insert(['text'=>json_encode($text),'text1'=>888]);
        $Prpcrypt   =   new \Prpcrypt($this->AesKey);
        $xml        =   new \DOMDocument();
        $xml->loadXML($text);
        $array_e    =   $xml->getElementsByTagName('Encrypt');
        $encrypt    =   $array_e->item(0)->nodeValue;
        $data       =   $Prpcrypt->decrypt($encrypt, $this->AppId);

        if (!empty($data[1]) && $data[0] == 0) {
            $xmlArr = simplest_xml_to_array($data[1]);
            if($xmlArr['Event']    ==      'weapp_audit_success' || $xmlArr['Event'] == 'weapp_audit_fail' ){
//                db('ceshi')->insert(['text'=>json_encode($xmlArr),'text1'=>6666]);

                $uid    =   $xmlArr['ToUserName'];
                if($xmlArr['Event']      ==      'weapp_audit_success' ){
                    $infotype   =   'auditedsuccess';
                    $reason     =   'ok';
                    $status=10;
                }else{
                    $infotype   =   'auditederror';
                    $reason     =   $xmlArr['Reason'];
                    $status=9;
                }
                $remark_status = 1;
                db('app')->where('user_name',$uid)->update(['status'=> $infotype ]);
                $ip         =   getIP();
                $time       =   $xmlArr['CreateTime'];
                $app_id     =   db('app')->where('user_name',$uid)->find();

                $data       =   db('app_submit')->insert(['remark_status'=>$remark_status,'status'=>$status,'app_id'=>$app_id['app_id'],'create_at'=>$time,'create_ip'=>$ip,'remark'=>$reason]);
                $this->datas=   $data;
            }else{
                db('ceshi')->insert(['text'=>json_encode($xmlArr),'text1'=>3333]);
            }

        }
    }


    public function userce()
    {
        $id = db('user')->value('user_id');
        $this->abnormal('200',encode(['user_id'=>(time()+24*60*60).':'.$id]));

    }

    //获取草稿箱内的所有临时代码草稿
    public function draft()
    {
        $data                               =   json_decode(file_get_contents('https://api.weixin.qq.com/wxa/gettemplatedraftlist?access_token='.$this->token()),true);
        if($data['errcode']==0)
        {
            $App_weizhan_template           =   $data['draft_list']['0']['draft_id'];
            $App_weizhan_template_version   =   $data['draft_list']['0']['user_version'];
            $App_weizhan_template_desc      =   $data['draft_list']['0']['user_desc'];

            $return                         =   CurlApi('https://api.weixin.qq.com/wxa/addtotemplate?access_token='.$this->token(),json_encode(['draft_id'=>$App_weizhan_template]),'post');
            if($return['errcode']==0)
            {
                $returndata                 =   json_decode(file_get_contents('https://api.weixin.qq.com/wxa/gettemplatelist?access_token='.$this->token()),true);
                if($returndata['errcode']   ==  0 )
                {
                    $list   =   $returndata['template_list'];
                    $count  =   count($list);
                    db('config')->where('config_var','App_weizhan_template')->update(['config_value'=>$list[$count-1]['template_id']]);
                    db('config')->where('config_var','App_weizhan_template_version')->update(['config_value'=>str_replace(' ','',$App_weizhan_template_version)]);
                    db('config')->where('config_var','App_weizhan_template_desc')->update(['config_value'=>$App_weizhan_template_desc]);
                    $this->datas    =   ['App_weizhan_template'=>$list[$count-1]['template_id'],'App_weizhan_template_version'=>$App_weizhan_template_version,'App_weizhan_template_desc'=>$App_weizhan_template_desc];
                }else{
                    $this->abnormal($this->AuthError,'模板保存失败');
                }
            }else{
                $this->abnormal($this->AuthError,'模板草稿箱推送失败');
            }
        }else{
            $this->abnormal($this->AuthError,'获取草稿箱失败');
        }
    }


    //获取草稿箱内的所有临时代码草稿
    public function shop_draft()
    {
        $data                               =   json_decode(file_get_contents('https://api.weixin.qq.com/wxa/gettemplatedraftlist?access_token='.$this->token()),true);
        if($data['errcode']==0)
        {
            $App_weizhan_template           =   $data['draft_list']['0']['draft_id'];
            $App_weizhan_template_version   =   $data['draft_list']['0']['user_version'];
            $App_weizhan_template_desc      =   $data['draft_list']['0']['user_desc'];

            $return                         =   CurlApi('https://api.weixin.qq.com/wxa/addtotemplate?access_token='.$this->token(),json_encode(['draft_id'=>$App_weizhan_template]),'post');
            if($return['errcode']==0)
            {
                $returndata                 =   json_decode(file_get_contents('https://api.weixin.qq.com/wxa/gettemplatelist?access_token='.$this->token()),true);
                if($returndata['errcode']==0)
                {
                    $list   =   $returndata['template_list'];
                    $count  =   count($list);
                    db('config')->where('config_var','App_shop_template')->update(['config_value'=>$list[$count-1]['template_id']]);
                    db('config')->where('config_var','App_shop_template_version')->update(['config_value'=>str_replace(' ','',$App_weizhan_template_version)]);
                    db('config')->where('config_var','App_shop_template_desc')->update(['config_value'=>$App_weizhan_template_desc]);
                    $this->datas    =   ['App_shop_template'=>$list[$count-1]['template_id'],'App_shop_template_version'=>$App_weizhan_template_version,'App_shop_template_desc'=>$App_weizhan_template_desc];
                }else{
                    $this->abnormal($this->AuthError,'模板保存失败');
                }
            }else{
                $this->abnormal($this->AuthError,'模板草稿箱推送失败');
            }
        }else{
            $this->abnormal($this->AuthError,'获取草稿箱失败');
        }
    }




    //获取component_access_token
    public function token()
    {
        $redis  =   $this->redis;
        if($redis->exists('component_access_token'))
        {
            $component_access_token         =   $redis->get('component_access_token');
//            dd($component_access_token);
            return $component_access_token;
        }else{
            $url    =   'https://api.weixin.qq.com/cgi-bin/component/api_component_token';
            $json   =   json_encode([
                'component_appid'           =>  $this->AppId,
                'component_appsecret'       =>  $this->secret,
                'component_verify_ticket'   =>  $this->ticket,
            ]);
            $data   =   json_decode(curl_request($url,$json),true);

            if(array_key_exists('errcode',$data))
            {
                //dd(11);
                if($data['errcode']!=0 && $data['errcode']!='ok')
                {
                    $this->abnormal($this->AuthError,'获取component_access_token失败 ['.$data['errcode'].']'.$data['errmsg']);
                }else{
                    redis_set('component_access_token',$data['component_access_token']);
                }
            }else{
               // dd(11);
                redis_set('component_access_token',$data['component_access_token']);
            }
//            dd($data['component_access_token']);
            return $data['component_access_token'];
        }
    }
    
}