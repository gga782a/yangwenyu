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
    public function parme($key = '',$value = null)
    {

        $this->parme    =  input();
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

    public function __destruct(){

    }
}