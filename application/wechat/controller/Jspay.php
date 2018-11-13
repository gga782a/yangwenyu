<?php
/*
*       微信支付
*       2018.5.1
*       from : dzw
*/
  
namespace app\wechat\controller;
use app\DataAnalysis;
use app\Qiniu;
use think\Validate;
class Jspay extends Index
{
	    public function __construct()
    {
        parent::__construct();
    	// $where['app_id'] = $this->parme('us_appid');
     //    $app_info = db('app_setting')->where($where)->field('var_name,var_value')->select();
     //    $this->mch_id = $app_info['mch_id'];
     //    $this->key = $app_info['key'];

    }


	 public function jspaymoney()
    {

    	// $where['app_id'] = $this->parme('us_appid');
     //    $app_info = db('app_setting')->where($where)->field('var_name,var_value')->select();
     //    $this->app_info = $app_info;


		require_once ROOT_PATH.'weixin/Weixinpay.php';


		$app_id       =	 input('app_id');
		$openid       =  $this->parme('openid');
		$mch_id       =  $this->parme('mch_id'); //1498851792 
		$key		  =  $this->parme('key');  //fqa5sJ6QsPWyVILdqUxCMjhn7xzm7WSd
		$out_trade_no =  $this->parme('out_trade_no');
		$total_fee    =  $this->parme('total_fee');
		$attach       =  $this->parme('attach');
		$body    	  =  $this->parme('body');
		if(empty($total_fee)) //押金
		{
			$total_fee = floatval(99*100);
		}else {
			$total_fee = floatval($total_fee*100);
		}
		$weixinpay = new \WeixinPay($app_id,$openid,$mch_id,$key,$out_trade_no,$body,$total_fee,$attach);
		$return=$weixinpay->pay();

		$this->datas	=   $return;
	}

//微信支付回调验证
	 public function paynotice()
	{
		// require_once ROOT_PATH.'weixin/Weixinpay.php';
		$a  = 1;

		// $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
		// *******php7 已移除HTTP_RAW_POST_DATA********
		// file_put_contents(APP_ROOT.'/Statics/log2.txt',$res,FILE_APPEND);

		$xml = $GLOBALS['HTTP_RAW_POST_DATA'];
		if (empty($xml)) {
			$xml = file_get_contents("php://input");
		}
		$data=$this->xmlToArrays($xml);

		$data_sign = $data['sign'];
		unset($data['sign']);

		$sign=$this->getSigns($data);

		// 判断签名是否正确  判断支付状态
		if ( ($sign===$data_sign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS') ){

		//处理数据更新
			$where['id'] = decode($data['attach']);
			$list    =  db('payment')->where($where)->find();
			if ($list) {
				$back1 = db('payment')->where($where)->update(['status' => '1','paytime' => time()]);
				$plus = json_decode($list['pay_goods_id'],true);
				foreach ($plus as $key => $value) {
					$wh['goods_id'] = decode($value['goods_id']);
					db('shop_goods')->where($wh)->setDec('goods_stock',$value['goods_stock']);
				}
				$result = 1;
			}else{
				$result = false;	
			}
		}else{ 
            // $this->abnormal($this->DbError,'回调失败');
            $result = false;
		}
		// 返回状态给微信服务器
		if ($result) {
			$str='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
		}else{
			$str='<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
		}
		return $result;




	}


    //xml转换成数组  
    private function xmlToArrays($xml) {  
  
  
        //禁止引用外部xml实体   
  
  
        libxml_disable_entity_loader(true);  
  
  
        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);  
  
  
        $val = json_decode(json_encode($xmlstring), true);  
  
  
        return $val;  
    }  


    //作用：生成签名  
    private function getSigns($Obj) {
    	$key = 'fqa5sJ6QsPWyVILdqUxCMjhn7xzm7WSd';
    	$this->key = $key;
        foreach ($Obj as $k => $v) {  
            $Parameters[$k] = $v;  
        }  
        //签名步骤一：按字典序排序参数  
        ksort($Parameters);  
        $String = $this->formatBizQueryParaMap($Parameters, false);  
        //签名步骤二：在string后加入KEY  
        $String = $String . "&key=" . $this->key;  
        //签名步骤三：MD5加密  
        $String = md5($String);  
        //签名步骤四：所有字符转为大写  
        $result_ = strtoupper($String);  
        return $result_;  
    } 


    ///作用：格式化参数，签名过程需要使用  
    private function formatBizQueryParaMap($paraMap, $urlencode) {  
        $buff = "";  
        ksort($paraMap); 
        // dump($paraMap);die();
        foreach ($paraMap as $k => $v) {  
            if ($urlencode) {  
                $v = urlencode($v);  
            }  
            $buff .= $k . "=" . $v . "&";  
        }  
        $reqPar = '';
        if (strlen($buff) > 0) {  
            $reqPar = substr($buff, 0, strlen($buff) - 1);  
        }  
        return $reqPar;  
    } 


}
?>