<?php
namespace app\wxcheck\controller;
use app\lib\errorCodes;
use app\wxcheck\Controller;
/**
 * 对微信小程序用户加密数据的解密示例代码.
 *
 * @copyright Copyright (c) 1998-2014 Tencent Inc.
 */


class Wxcheck extends Controller
{
    private $appid;
	private $sessionKey;

    public static $IllegalAesKey = -41001;
    public static $IllegalIv = -41002;
    public static $IllegalBuffer = -41003;
    public static $DecodeBase64Error = -41004;
	/**
	 * 构造函数
	 * @param $sessionKey string 用户在小程序登录后获取的会话密钥
	 * @param $appid string 小程序的appid
	 */
//	public function __construct()
//	{
//
//        // $this->IllegalAesKey = $IllegalAesKey;
//        // $this->IllegalIv = $IllegalIv;
//        // $this->IllegalBuffer = $IllegalBuffer;
//        // $this->DecodeBase64Error = $DecodeBase64Error;
//
//	}


	/**
	 * 检验数据的真实性，并且获取解密后的明文.
	 * @param $encryptedData string 加密的用户数据
	 * @param $iv string 与用户数据一同返回的初始向量
	 * @param $data string 解密后的原文
     *
	 * @return int 成功0，失败返回对应的错误码
	 */
	/**
	 * error code 说明.
	 *    <li>-41001: encodingAesKey 非法</li>
	 *    <li>-41003: aes 解密失败</li>
	 *    <li>-41004: 解密后得到的buffer非法</li>
	 *    <li>-41005: base64加密失败</li>
	 *    <li>-41016: base64解密失败</li>
	 */

	public function decryptData()
	{
		$sessionKey = $this->parme('sessionKey');
		$appid = $this->parme('appid');

		$encryptedData = $this->parme('encryptedData');
		$iv = $this->parme('iv');

		// dump($sessionKey);dump('||||||');dump($appid);dump('||||||');dump($encryptedData);dump('||||||');dump($iv);die();
		if (strlen($sessionKey) != 24) {
			return self::$IllegalAesKey;
		}
		$aesKey=base64_decode($sessionKey);

		if (strlen($iv) != 24) {
			return self::$IllegalIv;
		}
		$aesIV=base64_decode($iv);

		$aesCipher=base64_decode($encryptedData);
		$result=openssl_decrypt( $aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);

		$dataObj=json_decode( $result );
		if( $dataObj  == NULL )
		{
			return self::$IllegalBuffer;
		}
		if( $dataObj->watermark->appid != $appid )
		{
			return self::$IllegalBuffer;
		}
		$data = $result;
		return $data;
	}

	public function savephone()
	{
		$app_id = $this->parme('app_id');
		$openid = $this->parme('openid');
		$phone = $this->parme('phone');
		if (empty($openid) || empty($phone)) {
			return 'openid,手机号不能为空';
		}else{
			$where['app_id']  = $app_id;
			$where['open_id'] = $openid;
			$data['phone']    = $phone;
			$back = db('app_member')->where($where)->update($data);
			if ($back) {
				return 'OK!';
			}else{
				return 'fail!';
			}
			
		}


	}

}

