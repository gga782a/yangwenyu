<?php
namespace app\wxcheck;
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




}