<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/10/11
 * Time: 上午11:28
 */

namespace app\part;

use app\ErrorCode;

class Controller extends ErrorCode
{
    protected $Returned = FALSE;
    protected $code     = 0;
    protected $datas    = [];
    protected $ErrorMsg = '';
    public $parme;
    public function _initialize()
    {
        $this->parme();

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