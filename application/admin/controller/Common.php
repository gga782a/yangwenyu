<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/15
 * Time: 上午9:33
 */

namespace app\admin\controller;
use think\Controller;
use think\Request;
use think\Session;

class Common extends Controller
{
    public  $id;
    public $parme;

    public function __construct(Request $request = null)
    {
        $this->id = Session::get('user_id');
        $this->check();
        $this->parme();
    }

    public function check(){
        if(empty($this->id)){
            return $this->redirect('part/login/checklogin');
        }
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

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
    }
}