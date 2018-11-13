<?php
namespace app\wechat;
use app\ErrorCode;

use app\manage\controller\Manage;
use app\user\controller\User;

class Controller extends ErrorCode{

    protected $Returned = FALSE;
    protected $code     = 0;
    protected $datas    = [];
    protected $ErrorMsg = '';
    public $parme;
    public $user_id;


    public function __construct()
    {
        if(input('u_token'))
        {
            $arr            =   explode(':',decode(input('u_token')));

            if(time()>$arr[0])
            {
                $this->abnormal($this->LoginError,'_token已过期');
            }else{
                if(db(User::$table)->where(User::$primary,$arr[1])->count()==0)
                {
                    $this->abnormal($this->LoginError,'_token错误');
                }else{
                    $this->user_id    =   $arr[1];

                    $this->parme();
                }
            }
        }else if(input('m_token'))
        {
            $arr            =   explode(':',decode(input('m_token')));
            if(time()>$arr[0])
            {
                $this->abnormal($this->LoginError,'_token已过期');
            }else{
                if(db(Manage::$table)->where(User::$primary,$arr[1])->count()==0)
                {
                    $this->abnormal($this->LoginError,'_token错误');
                }else{
                    $this->parme();
                }
            }
        }
    }

    public function parme($key = null,$value=null)
    {

        $this->parme    =   decode(input());
        $this->parme    =   array_merge($this->parme,['user_id'=>$this->user_id]);
        if($key)
        {
            if(array_key_exists($key,$this->parme))
            {
                return $this->parme[$key]!=''?$this->parme[$key]:$value;
            }else{
                return $value!=''?$value:null;
            }
        }
    }
    public function abnormal($ErrorCode,$message){

        $this->code     = $ErrorCode;
        $this->ErrorMsg = $message;
        $this->Returned = TRUE;
        if(is_array($this->datas))
        {
//            dump($this->datas);

            $this->datas            =   encode($this->datas);
            // =========== 2018年7月24日11:59:28 SSS ============
            if(!array_key_exists('length',$this->datas)){
                $this->datas['length']  =   count($this->datas);
            }
        }


        $this->result($this->datas,$this->code,$this->ErrorMsg,'json');

    }

    public function SuccessReturn($message){

        $this->abnormal($this->OK,$message);

    }

    public function DbSuccess($return)
    {
        $return ? $this->SuccessReturn('操作成功') : $this->ErrorMsg = $this->DbError;
    }

    public function __destruct(){

        if ($this->Returned === FALSE){

            $this->SuccessReturn("访问成功!");

        }

    }

}