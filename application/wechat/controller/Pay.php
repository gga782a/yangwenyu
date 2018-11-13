<?php
namespace app\wechat\controller;
class Pay
{
    public function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val)
        {
            if (is_numeric($val))
            {
                $xml .= "<".$key.">".$val."</".$key.">";
            }else{
                $xml .= "<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }
//使用证书，以post方式提交xml到对应的接口url

    /**
     *   作用：使用证书，以post方式提交xml到对应的接口url
     */
    function curl_post_ssl($url, $vars, $second = 30)
    {
        $ch = curl_init();
//超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

//以下两种方式需选择一种
        /******* 此处必须为文件服务器根目录绝对路径 不可使用变量代替*********/
        curl_setopt($ch, CURLOPT_SSLCERT, ROOT_PATH."/public/apiclient_cert.pem");
        curl_setopt($ch, CURLOPT_SSLKEY, ROOT_PATH."/public/apiclient_key.pem");


        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);

        $data = curl_exec($ch);
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }

//企业向个人付款
    public function touser($openid = '', $desc = '提现成功', $amount = '100')
    {
        //微信付款到个人的接口
        $openid = 'opLcb5K9mzh-k_WoKOdB81hsWxCc';
        $url = 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';

        $params["mch_appid"] = 'wx19823ab8ea64a7b5';   //公众账号appid
        $params["mchid"] = '1498851792';   //商户号 微信支付平台账号
        $params["nonce_str"] = 'longdongzhiye99' . mt_rand(100, 999);   //随机字符串
        $params["partner_trade_no"] = time().mt_rand(10000000, 99999999);           //商户订单号
        $params["amount"] = $amount;          //金额
        $params["desc"] = $desc;            //企业付款描述
        $params["openid"] = $openid;          //用户openid
        $params["check_name"] = 'NO_CHECK';       //不检验用户姓名
        $params['spbill_create_ip'] = getIP();   //获取IP

        $str = 'amount='.$params["amount"].'&check_name='.$params["check_name"].'&desc='.$params["desc"].'&mch_appid='.$params["mch_appid"].'&mchid='.$params["mchid"].'&nonce_str='.$params["nonce_str"].'&openid='.$params["openid"].'&partner_trade_no='.$params["partner_trade_no"].'&spbill_create_ip='.$params['spbill_create_ip'].'&key=fqa5sJ6QsPWyVILdqUxCMjhn7xzm7WSd';
        $sign = strtoupper(md5($str));

        $params["sign"] = $sign;//签名

        $xml = $this->arrayToXml($params);

        return $this->curl_post_ssl($url, $xml);


    }

}
