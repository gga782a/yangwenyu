<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/10/12
 * Time: 上午9:04
 */
namespace aap\pub;
use app\ErrorCode;
class Controller extends ErrorCode
{
    protected $Returned = FALSE;
    protected $code     = 0;
    protected $datas    = [];
    protected $ErrorMsg = '';
    public $parme;
    public static $_token ;
    public function _initialize()
    {
        $this->parme();
        $this->checktoken();

    }
    public function checktoken()
    {
        self::$_token = encrypt('ztwlxx');
        if($this->parme('token')) {
            if ($this->parme('token') === self::$_token) {
                $this->abnormal($this->OK,'欢迎使用');
            } else {
                $this->abnormal($this->LoginError,'参数错误');
            }
        }else{
            $this->abnormal($this->LoginError,'参数缺失');
        }
    }
    public function parme($key = '',$value = '')
    {

        $this->parme    =   decode(input());
        $this->parme    =   array_merge($this->parme);
        if($key)
        {
            if(array_key_exists($key,$this->parme))
            {
                return $this->parme[$key]!=''?$this->parme[$key]:$value;
            }else{
                return $value!=''?$value:'';
            }
        }
    }

    public function abnormal($ErrorCode,$message){

        $this->code     = $ErrorCode;
        $this->ErrorMsg = $message;
        $this->Returned = TRUE;
        if(is_array($this->datas))
        {
            $this->datas    =   encode($this->datas);
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