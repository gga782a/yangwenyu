<?php
namespace app;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use Qiniu\Processing\PersistentFop;
use think\Controller;


class Qiniu extends Controller
{

    public static function del($img)
    {
        if ($img != '') {
            require_once APP_PATH . '/../qiniu/autoload.php';
            $auth           =   new Auth(_config('qiniu_accessKey'), _config('qiniu_secretKey'));
            $bucketMgr  =   new BucketManager($auth);
            $bucketMgr->delete(_config('qiniu_bucket'), $img);
            return true;
        } else {
            return false;
        }
    }


    public static function upload($img)
    {
        //var_dump($img);
        //var_dump(2222);
        $file = request()->file($img);
        //var_dump($file);
        //dd($file);
        if($file!='')
        {
            //var_dump(3333);
            $size   =   $file->getSize();
            //var_dump($size);
            //var_dump(_config('file_size'));
            if($size<_config('file_size'))
            {
                $ext            =   strtolower(pathinfo($file->getInfo('name'), PATHINFO_EXTENSION));
                switch (true)
                {
                    case in_array($ext,explode(',',_config('img_type'))):
                        $type   =   '1';
                        break;
                    case in_array($ext,explode(',',_config('video_type'))):
                        $type   =   '2';
                        break;
                    case in_array($ext,explode(',',_config('music_type'))):
                        $type   =   '3';
                        break;
                    case $ext   == 'pem':
                        $type   =   '0';
                        break;
                    default:
                        $type   =   false;
                        break;
                }

                if($type != false)
                {
                    $filePath           =   $file->getRealPath();
                    $return             =   self::qiniu($filePath,$ext);
                    $return['file_type']=   $type;
                    $return['file_size']=   $size;
                    $return['file_name']=   $file->getInfo('name');
                    return $return;

                }else{
                    return ["code"=>false,'msg'=>'文件格式不正确'];
                }
            }else{
                return ["code"=>false,'msg'=>'文件大小超过限制'];
            }
        }else{
            //var_dump(444);
            if(input($img)!='')
            {
                //var_dump(input($img));
                $count  =   db('file')->where('file_path',input($img))->count();
                if($count)
                {
                    return ["code"=>true,'file_path'=>input($img)];
                }else{
                    return ["code"=>false,'msg'=>'文件不存在'];
                }
            }else{
                //var_dump(55555);
                return ["code"=>false,'msg'=>'未选择文件'];
            }

        }
    }


    public function utoken()
    {
        require_once APP_PATH . '/../qiniu/autoload.php';
        $auth           =   new Auth(_config('qiniu_accessKey'), _config('qiniu_secretKey'));
        $token          =   $auth->uploadToken(_config('qiniu_bucket'));
        return $token;
    }

    public static function qiniu($path,$ext)
    {
        require_once APP_PATH . '/../qiniu/autoload.php';
        $filePath       =   $path;
        $key            =   substr(md5(time().rand(1000,9999)), 0, 5) . date('YmdHis') . rand(0, 9999) . '.' . $ext;
        $auth           =   new Auth(_config('qiniu_accessKey'), _config('qiniu_secretKey'));
        $token          =   $auth->uploadToken(_config('qiniu_bucket'));
        $uploadMgr      =   new UploadManager();


        $return         =   $uploadMgr->putFile($token, $key, $filePath);
        if ($return[0]['key'] !== null) {
            unlink($path);
            return ["code"=>true,"file_path"=>$return[0]['key']];
        } else {
            return ["code"=>false,'msg'=>'上传失败'];
        }
    }

    public static function watermark($mp4,$water)
    {
        require_once APP_PATH . '/../qiniu/autoload.php';
        $auth        =   new Auth(_config('qiniu_accessKey'), _config('qiniu_secretKey'));
        $bucket      =   _config('qiniu_bucket');
        $wmImg       =   \Qiniu\base64_urlSafeEncode($water);

        $notifyUrl   =   'http://api.ztwlxx.club/wechat/authorize/notify';
        $pipeline    =   '10086';
        $name        =   md5(rand(11111111,99999999)."-".time()).".mp4";
        $saveas      =   self::urlsafe_b64encode($bucket .':'. $name);
        $pfop        =   new PersistentFop($auth, $bucket, $pipeline,$notifyUrl);
        $fops        =   [
            "avthumb","mp4","wmImage",$wmImg,
//            "wmText","d2Vsb3ZlcWluaXU=",
//            "wmFontColor","cmVk","wmFontSize","60",
            "wmGravityText","NorthEast",
            _config('qiniu_bucket'),
            $mp4,
            $wmImg.'|saveas/'.$saveas,'10086',
        ];
        list($id, $err) = $pfop->execute($mp4, $fops);
        if ($err != null) {
            var_dump($err);
           return 'error';
        } else {
           return $name;
        }

    }

    private static function urlsafe_b64encode($string) {
        $data = base64_encode($string);
        $data = str_replace(array('+','/'),array('-','_'),$data);
        return $data;
    }



}