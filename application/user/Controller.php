<?php

namespace app\user;
use think\Db;
use app\DataAnalysis;
use app\ErrorCode;
use app\user\controller\User;
use think\Request;

class Controller extends ErrorCode{

    protected $Returned = FALSE;
    protected $code     = 0;
    protected $datas    = [];
    protected $ErrorMsg = '';
    protected $checkUser = FALSE;
    public $parme;
    public $user_id;

    public function _initialize()
    {
//dd(0);
//        dd(input());
//        $this->abnormal($this->LoginError,input('_token'));
        if(input('_token'))
        {
            $request        =   Request::instance();

            $arr            =   explode(':',decode(input('_token')));
//            dd($arr);
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
        }else{
            $this->abnormal($this->LoginError,'_token丢失');
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
            $this->datas            =   encode($this->datas);
//            $this->datas['length']  =   count($this->datas);
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