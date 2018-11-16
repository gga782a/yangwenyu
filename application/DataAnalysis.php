<?php
namespace app;

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
}