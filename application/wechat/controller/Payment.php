<?php
/*
*       微信支付
*/

    namespace app\wechat\controller;


class Payment extends Index
{
    public function __construct()
    {
        parent::__construct();
        $this->time=time();

    }


    public function jspaymoney()
    {
        require_once ROOT_PATH.'weixin/Weixinpay.php';

        $app_id       =	 $this->parme('app_id');
        //var_dump($app_id);
        $data=db('app_pay_setting')->where('app_id',$app_id)->find();
//var_dump($data);
        $mch_id       =  $data['mch_id'];
        $key		  =  $data['mch_key'];
        $appid        =  $data['appid'];

        $openid       =  $this->parme('openid');
        $out_trade_no =  $this->parme('out_trade_no').$this->createNoncestr(6);
        $total_fee    =  $this->parme('total_fee');
        $attach       =  $this->parme('attach');
        $body    	  =  $this->parme('body');
        $res = db('payment')->where(['app_id'=>$this->parme('app_id'),'pay_order'=>$this->parme('out_trade_no')])->update(['out_trade_no'=>$out_trade_no]);
        if($res!==false) {
            if (empty($total_fee)) //押金
            {
                $total_fee = floatval(99 * 100);
            } else {
                $total_fee = floatval($total_fee * 100);
            }
            $weixinpay = new \WeixinPay($appid, $openid, $mch_id, $key, $out_trade_no, $body, $total_fee, $attach);
            $return = $weixinpay->pay();

            $this->datas = $return;
        }else{
            $this->abnormal($this->DbError,'支付失败，请重试');
        }

    }



//微信支付回调验证
    public function paynotice()
    {
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        if (empty($xml)) {
            $xml = file_get_contents("php://input");
        }
        $data=$this->xmlToArrays($xml);

        $data_sign = $data['sign'];
        unset($data['sign']);

        $type_json  =      explode(',',$data['attach']);
        $list       =      db('payment')->where('id',decode($type_json[1]))->where(['status'=>0])->find();//
        $key        =      db('app_pay_setting')->where('app_id',$list['app_id'])->find();
        $sign=$this->getSigns($data,$key['mch_key']);
        // db('ceshi')->insert([ 'text'=>$data_sign,'text1'=>$sign ]);
        // 判断签名是否正确  判断支付状态

        if ( ($sign===$data_sign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS') ){

            //处理数据更新
            // pay_type 支付类型 1 购买商品 2购买秒杀商品 3购买拼团商品 4开通会员 5购买商品退款 6购买秒杀商品退款 7购买拼团商品退款 8分销返利 9商家入驻 10置顶
//            $pay_type=explode(',',decode($data['attach']));
//            $data=["money"=>1,"log_id"=>1,"member_id"=>111,"order_number"=>1111,"attach"=>"{\"attach\": 111,\"pay_type\": 1}"];
//            $type_json=$this->object_array(json_decode($data['attach']) );

/*
/
    {"appid":"wx19823ab8ea64a7b5","attach":"3,ZWs%3D","bank_type":"CFT","cash_fee":"1","fee_type":"CNY","is_subscribe":"N","mch_id":"1498851792","nonce_str":"q5tx 97jl2x7bmll586e1mwsm74048fz2","openid":"opLcb5K9mzh-k_WoKOdB81hsWxCc","out_trade_no":"2018072056100985","result_code":"SUCCESS","return_code":"SUCCESS","time_end":"20180720132317","total_fee":"1","trade_type":"JSAPI","transaction_id":"4200000118201807205103474469"}
/
/
*/
            if($type_json){
                //db('ceshi')->insert(['text'=>$type_json[0],'text1'=>'hhhhhhhh']);
                switch ($type_json[0]){
                    case 1:
                        $where['id'] = decode($type_json[1]);
                        $list    =  db('payment')->where($where)->find();
                        //db('ceshi')->insert(['text'=>json_encode($list),'text1'=>'hhh']);
                        if ($list) {
                            if ($list['coupon_id'] > 0) {
                                $db_data = array('status'=> 1 ,'update_at'=>$this->time);
                                db('user_coupon')->where('id',$list['coupon_id'])->setField($db_data);
                            }
                            $back1 = db('payment')->where($where)->setField(['status' => 1,'paytime' => time()]);
                            $plus = json_decode($list['pay_goods_id'],true);
                            //db('ceshi')->insert(['text'=>json_encode($plus),'text1'=>'h']);
                            //dump($plus);
                            foreach ($plus as $key => $value) {

                                //db('ceshi')->insert(['text'=>json_encode($value),'text1'=>decode($type_json[1]).'ren']);
                                $wh['goods_id'] = decode($value['goods_id']);//商品总库存删减
                                $stock = db('shop_goods')->where($wh)->find();
                                $skus = json_decode($value['sku'],true);
                                $str = '';
                                if (is_array($skus['sku_type'])) {
                                    foreach ($skus['sku_type'] as $v) {
                                        $str .= $v . ",";
                                    }
                                    $str = substr($str, 0, strlen($str) - 1);
                                } else {
                                    $str = $skus['sku_type'];
                                }
                                //db('ceshi')->insert(['text'=>json_encode($stock),'text1'=>'stock']);
                                if ($stock['goods_stock'] > 0) {
                                    db('shop_goods')->where($wh)->setDec('goods_stock',$value['goods_stock']);
                                }
                                //db('ceshi')->insert(['text'=>$value['goods_stock'],'text1'=>'ssss']);
                                // $sku_where['sku_type'] = json_decode($value['sku'],true)['sku'];//规格库存删减


                                $sku_where['sku_type'] = $str;
                                //db('ceshi')->insert(['text'=>json_decode($value['sku'],true)['sku'],'text1'=>'sssddsdsds']);
                                //dd($value['sku_stock'].'-1');
                                $sku_where['shop_goods_id'] = decode($value['goods_id']);
                                db('ceshi')->insert(['text'=>$type_json[0],'text1'=>'asdasdasdasdasdas']);
                                $a = db('goods_sku')->where($sku_where)->setDec('sku_stock',$value['goods_stock']);
                                db('ceshi')->insert(['text'=>'22222222','text1'=>'asdas']);
                                //查询新的规格
                                $skuarrwhere = array(
                                    'app_id' => $list['app_id'],
                                    'shop_goods_id' => decode($value['goods_id']),
                                );
                                db('ceshi')->insert(['text'=>json_encode($skuarrwhere),'text1'=>'skuarrwhere']);
                                $skuarr = db('goods_sku')->where($skuarrwhere)->select();
                                db('ceshi')->insert(['text'=>json_encode($skuarr),'text1'=>'skuarr']);
                                //便利组装新数据
                                $attr_arr = [];
                                foreach ($skuarr as $ke => $v){
                                    $v['sku_type'] = explode(',',$v['sku_type']);
                                    $attr_arr[] = $v;
                                }
                                db('ceshi')->insert(['text'=>json_encode($attr_arr),'text1'=>'attr_arr']);
                                db('shop_goods')->where($wh)->update(['attr_arr'=>json_encode($attr_arr)]);
                            }
                        }
                        $pay_type['money']=$list['need_pay'];
                        $pay_type['log_id']=$list['id'];
                        $pay_type['log_type']=$type_json[0];
                        $pay_type['member_id']=$list['user_id'];
                        $pay_type['order_number']=$list['order_number'];
                        $result = true;
                        break;
                    case 2:
                    // ---
                        // $sendjson = json_encode($pay_type);
                        // $callback = curl_request('http://api.ztwlxx.club/wechat/Payment/json', $sendjson);
                        // $this->datas=json_decode($callback);
                        break;
                    case 3:
                    // ---
                    // db('ceshi')->insert([ 'text'=>decode($type_json[1]),'text1'=>2222222 ]);
                        $where['id'] = decode($type_json[1]);
                        $list    =  db('payment')->where($where)->find();
                        if ($list) {
                            if ($list['coupon_id'] > 0) {
                                $db_data = array('status'=> 1 ,'update_at'=>$this->time);
                                db('user_coupon')->where('id',$list['coupon_id'])->setField($db_data);
                            }
                            $back1 = db('payment')->where($where)->setField(['status' => 1,'paytime' => time()]);
                            $plus = json_decode($list['pay_goods_id'],true);
                            foreach ($plus as $key => $value) {
                                $wh['goods_id'] = decode($value['goods_id']);//商品总库存删减
                                $stock = db('shop_goods')->where($wh)->find();
                                if ($stock['goods_stock'] > 0) {
                                    db('shop_goods')->where($wh)->setDec('goods_stock',$value['goods_stock']);
                                }
                                //$sku_where['sku_type'] = json_decode($value['sku'],true)['sku'];//规格库存删减
                                $skus = json_decode($value['sku'],true);
                                $str = '';
                                if (is_array($skus['sku_type'])) {
                                    foreach ($skus['sku_type'] as $v) {
                                        $str .= $v . ",";
                                    }
                                    $str = substr($str, 0, strlen($str) - 1);
                                } else {
                                    $str = $skus['sku_type'];
                                }

                                $sku_where['sku_type'] = $str;
                                //dd($value['sku_stock'].'-3');
                                $sku_where['shop_goods_id'] = decode($value['goods_id']);
                                db('goods_sku')->where($sku_where)->setDec('sku_stock',$value['goods_stock']);
                                db('ceshi')->insert(['text'=>'22222222','text1'=>'asdas']);
                                //查询新的规格
                                $skuarrwhere = array(
                                    'app_id' => $list['app_id'],
                                    'shop_goods_id' => decode($value['goods_id']),
                                );
                                db('ceshi')->insert(['text'=>json_encode($skuarrwhere),'text1'=>'skuarrwhere']);
                                $skuarr = db('goods_sku')->where($skuarrwhere)->select();
                                db('ceshi')->insert(['text'=>json_encode($skuarr),'text1'=>'skuarr']);
                                //便利组装新数据
                                $attr_arr = [];
                                foreach ($skuarr as $ke => $v){
                                    $v['sku_type'] = explode(',',$v['sku_type']);
                                    $attr_arr[] = $v;
                                }
                                db('ceshi')->insert(['text'=>json_encode($attr_arr),'text1'=>'attr_arr']);
                                db('shop_goods')->where($wh)->update(['attr_arr'=>json_encode($attr_arr)]);
                            }
                        }
                        $pay_type['money']=$list['need_pay'];
                        $pay_type['log_id']=$list['id'];
                        $pay_type['log_type']=$type_json[0];
                        $pay_type['member_id']=$list['user_id'];
                        $pay_type['order_number']=$list['order_number'];
                        $result = true;
                    
                        break;
                    case 4:
                    // ---
                        break;
                    case 5:
                    // ---
                        break;
                    case 6:
                    // ---
                        break;
                    case 7:
                    // ---
                        break;
                    case 8:
                    // ---
                        $where['id'] = decode($type_json[1]);

                        $list    =  db('payment')->where($where)->find();
                        db('ceshi')->insert(['text'=>json_encode($list),'text1'=>'list']);
                        if ($list) {
                            if ($list['coupon_id'] > 0) {
                                $db_data = array('status'=> 1 ,'update_at'=>$this->time);
                                db('user_coupon')->where('id',$list['coupon_id'])->setField($db_data);
                            }
                            $back1 = db('payment')->where($where)->setField(['status' => 1,'paytime' => time(),'is_distribut' => 1]);
                            $plus = json_decode($list['pay_goods_id'],true);
                            db('ceshi')->insert(['text'=>json_encode($plus),'text1'=>'p']);
                            //db('ceshi')->insert(['text'=>json($plus),'text1'=>'plus']);
                            foreach ($plus as $key => $value) {
                                db('ceshi')->insert(['text'=>decode($type_json[1]),'text1'=>'a']);
                                $wh['goods_id'] = decode($value['goods_id']);//商品总库存删减
                                //db('ceshi')->insert(['text'=>decode($value['goods_id']),'text1'=>'goods_id']);
                                $stock = db('shop_goods')->where($wh)->find();
                                db('ceshi')->insert(['text'=>$value['goods_stock'],'text1'=>'b']);
                                //db('ceshi')->insert(['text'=>json_encode($stock),'text1'=>'stock']);
                                if ($stock['goods_stock'] > 0) {
                                    db('shop_goods')->where($wh)->setDec('goods_stock',$value['goods_stock']);
                                }
                                //db('ceshi')->insert(['text'=>decode($value['goods_id']),'text1'=>'goods_id22222222']);
                                //db('ceshi')->insert(['text'=>json_encode($value),'text1'=>'stock22213333']);
                                //db('ceshi')->insert(['text'=>$value['sku'],'text1'=>'stock22ddd2q3']);
                                //db('ceshi')->insert(['text'=>json_encode($value['sku']),'text1'=>'st2q3']);
                                //$sku_where['sku_type'] = json_decode($value['sku'],true)['sku'];//规格库存删减
                                $skus = json_decode($value['sku'],true);
                                $str = '';
                                if (is_array($skus['sku_type'])) {
                                    foreach ($skus['sku_type'] as $v) {
                                        $str .= $v . ",";
                                    }
                                    $str = substr($str, 0, strlen($str) - 1);
                                } else {
                                    $str = $skus['sku_type'];
                                }
                                db('ceshi')->insert(['text'=>$str,'text1'=>'str']);
                                $sku_where['sku_type'] = $str;
                                //db('ceshi')->insert(['text'=>json_decode($value['sku'],true)['sku'],'text1'=>'stock343']);
                                $sku_where['shop_goods_id'] = decode($value['goods_id']);
                                //db('ceshi')->insert(['text'=>decode($value['goods_id']),'text1'=>'stock111111111']);
                                //db('ceshi')->insert(['text'=>json_decode($value['sku'],true)['sku_stock'],'text1'=>'stock11']);
                                $a   =db('goods_sku')->where($sku_where)->setDec('sku_stock',$value['goods_stock']);
                                db('ceshi')->insert(['text'=>'22222222','text1'=>'asdas']);
                                //查询新的规格
                                $skuarrwhere = array(
                                    'app_id' => $list['app_id'],
                                    'shop_goods_id' => decode($value['goods_id']),
                                );
                                db('ceshi')->insert(['text'=>json_encode($skuarrwhere),'text1'=>'skuarrwhere']);
                                $skuarr = db('goods_sku')->where($skuarrwhere)->select();
                                db('ceshi')->insert(['text'=>json_encode($skuarr),'text1'=>'skuarr']);
                                //便利组装新数据
                                $attr_arr = [];
                                foreach ($skuarr as $ke => $v){
                                    $v['sku_type'] = explode(',',$v['sku_type']);
                                    $attr_arr[] = $v;
                                }
                                db('ceshi')->insert(['text'=>json_encode($attr_arr),'text1'=>'attr_arr']);
                                db('shop_goods')->where($wh)->update(['attr_arr'=>json_encode($attr_arr)]);
                                //continue;
                                //db('ceshi')->insert(['text'=>$a,'text1'=>'stock222222222']);
                            }
                            //db('ceshi')->insert(['text'=>$list['user_id'],'text1'=>'userid11111']);
                            $u_where['member_id'] = $list['user_id'];
                            //db('ceshi')->insert(['text'=>$list['user_id'],'text1'=>'userid']);
                            $user = db('app_member')->where($u_where)->find();
                            //db('ceshi')->insert(['text'=>json_encode($user),'text1'=>'user']);
                            if ($user['distribut_id']) {
                                $app_info = db('app')->where('app_id',$list['app_id'])->find();
                                if ($app_info['distribut_type'] == 1) {
                                    $money = (int)$app_info['distribut_num'];
                                }elseif ($app_info['distribut_type'] == 2) {
                                    $ticheng = $app_info['distribut_num']/100 * $list['need_pay'];
                                    $money = round($ticheng,2);
                                }
                                //db('ceshi')->insert(['text'=>$money,'text1'=>'money']);
                                db('app_member')->where('member_id',$user['distribut_id'])->setInc('cashmoney', $money);
                                db('app_member')->where('member_id',$user['distribut_id'])->setInc('allmoney', $money);
                                
                            }
                        }
                        $pay_type['money']=$list['need_pay'];
                        $pay_type['log_id']=$list['id'];
                        $pay_type['log_type']=$type_json[0];
                        $pay_type['member_id']=$list['user_id'];
                        $pay_type['order_number']=$list['order_number'];
                        $result = true;
                        break;
                    case 9:
                    // ---
                        break;
                    case 10:
                    // ---
                        break;
                default:
                    $this->datas="缺少log_type";
                }
            }else{
                $this->datas="缺少log_type";
            }
            if(!$pay_type){
                $pay_type['log_id']=0;
            }
            $res=db("money_log")->insert($pay_type);
//            $this->datas=$res;
            $result = true;
            /*$where['id'] = decode($data['attach']);
            $list    =  db('payment')->where($where)->find();
            if ($list) {
                $back1 = db('payment')->where($where)->update(['status' => '1','paytime' => time()]);
                $plus = json_decode($list['pay_goods_id'],true);
                foreach ($plus as $key => $value) {
                    $wh['goods_id'] = decode($value['goods_id']);
                    db('shop_goods')->where($where)->setDec('goods_stock',$value['goods_stock']);
                }
                $result = 1;
            }else{
                $result = false;
            }*/
        }else{
            // $this->abnormal($this->DbError,'回调失败');
            $result = false;
        }
        // 返回状态给微信服务器
        if ($result) {

            $str='<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
            // db('ceshi')->insert(['text'=>$str,'text1'=>'88ceshi']);
        }else{
            $str='<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
            // db('ceshi')->insert(['text'=>$str,'text1'=>'88ceshi']);
        }
        return $str;
        exit();
    }


    //xml转换成数组
    private function xmlToArrays($xml) {


        //禁止引用外部xml实体


        libxml_disable_entity_loader(true);


        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);


        $val = json_decode(json_encode($xmlstring), true);


        return $val;
    }

    private function createNoncestr($length = 32) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    //作用：生成签名
    private function getSigns($Obj,$key) {
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

    function object_array($array) {
        if(is_object($array)) {
            $array = (array)$array;
        }
        if(is_array($array)) {
            foreach($array as $key=>$value) {
                $array[$key]=$this-> object_array($value);
            }
        }
        return $array;
    }


//退款接口
    public function refund()
    {
        //var_dump(111);
        require_once ROOT_PATH.'weixin/WinXinRefund.php';

        $app_id       =   $this->parme('app_id');
        $data         =  db('app_pay_setting')->where('app_id',$app_id)->find();
        $mch_id         =   $data['mch_id']; //1498851792
        $key            =   $data['mch_key'];  //RljWrQcDhz0J8DTbS7pujUxP2gooy0Ih
        $appid          =   $data['appid'];
        $SSLCERT_PATH   =   ROOT_PATH."public/uploads/".$data['certificate'];
        $SSLKEY_PATH    =   ROOT_PATH."public/uploads/".$data['certificate_key'];
//        dd($SSLKEY_PATH);
//        dump(ROOT_PATH);
//        dump($mch_id);
//        dump($key);
//        dump($appid);
//        dump($SSLCERT_PATH);
//        dump($SSLKEY_PATH);
        $out_refund_no  =  $this->createNoncestr(64);

        $out_trade_no   =  $this->parme('out_trade_no');
        $total_fee      =  $this->parme('total_fee')*100;
        $refund_fee     =  $this->parme('refund_fee')*100;
//        dump($total_fee);
//        dump($refund_fee);
        $weixinrefund   =  new \WinXinRefund($appid,$mch_id,$key,$out_trade_no,$total_fee,$out_refund_no,$refund_fee,$SSLCERT_PATH,$SSLKEY_PATH);
        $return=$weixinrefund->refund();
        db('ceshi')->insert(['text'=>json_encode($return),'text1'=>time()]);
        $this->datas =   $return;
    }



//拼团订单退款
    public function pintuan_refund()
    {

        require_once ROOT_PATH.'weixin/WinXinRefund.php';
        if($this->parme('_token')){

            $exp[0]=$this->parme('app_id');
            $exp[1]=$this->parme('pid');
        }else{
            $res = explode(':',$this->parme('out_time'));
            $exp = explode('-',$res[1]);
            if(!$res) {
                return 'error';
            }
        }

        db('ceshi')->insert([ 'text'=>$this->parme('out_time'), 'text1'=>date("Y-m-d",time() )]);


        $info = db('pintuan_people')->where('pid',$exp[1])->field('member_id,order_number')->find();

        $c_where['master_id'] = $info['member_id'];
        $child_info = db('pintuan_people')->where($c_where)->field('order_number')->select();

        if(isset($child_info) && $child_info){

            foreach ($child_info as $key => $value) {

                $arr[] = $value['order_number'];
            }

            array_push($arr,$info['order_number']);

            foreach ($arr as $k => $v) {

                $this->tongyi_refund($exp[0],$v);
            }
        }else{
           $this->tongyi_refund($exp[0],$info['order_number']);

        }


    }

    private function tongyi_refund($app_id,$order_num){
        db('ceshi')->insert([ 'text'=>json_encode($app_id.$order_num), 'text1'=>date("Y-m-d",time() )]);

        $data           =   db('app_pay_setting')->where('app_id',$app_id)->find();
        $mch_id         =   $data['mch_id']; //1498851792
        $key            =   $data['mch_key'];  //RljWrQcDhz0J8DTbS7pujUxP2gooy0Ih
        $appid          =   $data['appid'];
        $SSLCERT_PATH   =   ROOT_PATH."public/uploads/".$data['certificate'];
        $SSLKEY_PATH    =   ROOT_PATH."public/uploads/".$data['certificate_key'];


        $order = db('payment')->where('pay_order',$order_num)->find();


        $out_refund_no  =  $this->createNoncestr(64);

        $out_trade_no   =  $order_num;
        $total_fee      =  $order['need_pay']*100;
        $refund_fee     =  $order['need_pay']*100;

        $weixinrefund   =  new \WinXinRefund($appid,$mch_id,$key,$out_trade_no,$total_fee,$out_refund_no,$refund_fee,$SSLCERT_PATH,$SSLKEY_PATH);
        $return=$weixinrefund->refund();

        db('ceshi')->insert([ 'text'=>json_encode($return), 'text1'=>date("Y-m-d",time() )]);

        $back = $return;

        if ($back['return_code'] && $back['return_msg']) {
            db('pintuan_people')->where('order_number',$out_trade_no)->setField('status','2');
            db('payment')->where('pay_order',$out_trade_no)->setField('status','7');
        }else{
            db('pintuan_people')->where('order_number',$out_trade_no)->setField('status','3');
            db('payment')->where('pay_order',$out_trade_no)->setField('status','6');
        }


    }


}
?>