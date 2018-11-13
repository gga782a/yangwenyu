<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------



//header("Access-Control-Allow-Origin:http://www.ztwlxx.club");
header('Access-Control-Allow-Origin: *');
// 响应类型
header('Access-Control-Allow-Methods:POST,GET');
header('Access-Control-Allow-Credentials: true');
// 响应头设置
header('Access-Control-Allow-Headers:x-requested-with,content-type');




// 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');
//define('EXTEND_PATH',__DIR__.'/../vendor/zongjingli/');
// 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';

