<?php
    namespace  app;

    use think\Controller;

    class ErrorCode extends Controller{
        //成功
        public $OK              =   200;
        //登陆失败
        public $LoginError      =   -100;
        //数据不允许的操作
        public $DataError       =   -200;
        //数据过滤匹配错误
        public $StrainError     =   -501;
        //注册失败
        public $RegisterError   =   -10000;
        //密码错误
        public $PasswordError   =   -10001;
        //不允许的访问方式
        public $ErrorMethod     =   -10002;
        //未找到应用
        public $MissingApp      =   -10003;
        //未找到用户
        public $MissingUser     =   -10004;
        //用户被冻结
        public $FrozenUser      =   -10005;
        //授权取消
        public $UnAuthorized    =   -10006;
        //应用未授权
        public $NoAuthorize     =   -10007;
        //上传错误
        public $FileError       =   -30001;
        //数据验证错误
        public $ValitorError    =   -40000;
        //数据库操作失败
        public $DbError         =   -40001;
        //数据不存在
        public $DbNull          =   -40004;
        //授权失败
        public $AuthError       =   -50001;

        //未提交审核
        public $errStatus       =   -50005;

        //模板错误
        public $AuthTemplateError=  -50002;
        //获取预览码错误
        public $QrcodeError     =   -50003;
        //微信支付拉起失败
        public $WechatPayFail   =   -20001;
        //缺少支付参数
        public $MissPayParam    =   -20002;
        //商家未设置微信支付
        public $MissPaySetting  =   -20003;
        //腾讯地图API请求失败
        public $LocationError   =   -60001;
        //优惠券已领取
        public $CouponError     =   -991;
        //门店审核中
        public $Conduct         =   -300;
        //门店审核失败
        public $errfail         =   -400;
        //門店不存在
        public $isexistence     =   -500;
        //超出代理范围
       public $overDeputy       =   -700;
       //用户已入驻门店
        public $isEnter         =   -705;
        //唯一性错误
        public $uniqueError     =   -909;
    }