<?php
namespace app;
use think\Request;

class DataAnalysis extends ErrorCode
{
    public static $msg =   [
        'length'    =>  '长度应为{min}~{max}位',
        'require'   =>  '必须填写',
        'unique'    =>  '已存在',
        'in'        =>  '数据不正确',
        'alphaNum'  =>  '必须是英文加数字的构成',
        'confirm'   =>  '确认不一致',
        'exits'     =>  '不存在',
        'exists'     =>  '不存在'
    ];

    public static $keyword;

    public function __construct(Request $request = null)
    {
        $this->keyword   =   self::Encrypt('keyword');
    }

    //数据不可逆加密
    public static function Encrypt($data,$keyword = null)
    {
        return encrypt($data ,$keyword);
    }



    public static function create($data)
    {
        $time               =   time();
        $data['create_at']  =   $time;
        $data['update_at']  =   $time;
        return $data;
    }

    public static function save($data)
    {
        $time               =   time();
        $data['update_at']  =   $time;
        return $data;
    }

    public static function del($data)
    {
        $time               =   time();
        $data['delete_at']  =   $time;
        $data['del']        =   '1';
        return $data;
    }
}