<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/25
 * Time: 15:17
 */
namespace app\wechat\controller;
use app\DataAnalysis;
use app\Qiniu;
use think\Validate;

//use app\catering\Controller;

class Manage extends Index
{
    //课程列表
    public function course_list()
    {

        $data = db("manage_course_list")
            ->where(['status' => 1, 'is_del' => 0])
            ->select();

        $tree =$this->getTree($data, 0);
//        dump($tree);

        $this->datas = $tree;
    }


    public function  video(){
        $file = Qiniu::upload('file');
        dump($file);
        $name ='https://o6wndwjxn.qnssl.com/'.$file['file_path'];//分析文件
        dump($name);

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

    //课程详情
    public function course()
    {
        $rule = [
            'course_id|分类id' => 'require'
        ];

        $validate = new Validate($rule);
        if (!$validate->check($this->parme)) {
            $this->abnormal($this->ValitorError, $validate->getError());
        } else {
            $course_id['course_id'] = $this->parme("course_id", 0);
            $is_hot     =   $this->parme("is_hot", 0);
            $is_recom   =   $this->parme("is_recom", 0);

            if($is_hot==1){
                $order="is_hot desc";
            }elseif($is_recom==1){
                $order="is_recom desc";
            }else{
                $order="update_at desc";
            }


            if ($course_id['course_id'] == 0) {
                $data = db("manage_course")
                    ->where(['is_del' => 0, 'status' => 1, 'course_type' => 0])
                    ->page(input('page', '1'), input('pageshow', 16))
                    ->order($order)
                    ->select();

                $count=count(db("manage_course")->where(['is_del' => 0, 'status' => 1, 'course_type' => 0])->order($order)->select());
                $data['count']=$count;
                $this->datas = $data;
            } else {
                $data = db('manage_course_list')->where('list_id', $course_id['course_id'])->select();

                if ($data) {
                    $list = '';
                    foreach ($data as $value) {
                        $list .= $value['course_id'] . ',';
                    }
                    $list = substr($list, 0, strlen($list) - 1);

                    $list_id = db('manage_course_list')->where($course_id)->value('list_id');
                    if ($list_id == 0) {
                        $data = db('manage_course_list')->where('list_id', 'in', $list)->select();
                        foreach ($data as $value) {
                            $list .= ',' . $value['course_id'];
                        }
                    }

                    $data = db('manage_course')
                        ->where('course_id', 'in', $list)
                        ->where(['is_del' => 0, 'status' => 1])
                        ->page(input('page', '1'), input('pageshow', 16))
                        ->order($order)
                        ->select();
                    $count=count( db('manage_course')->where('course_id', 'in', $list)->where(['is_del' => 0, 'status' => 1])->order($order)->select());

                } else {
                    $where['course_id'] = $this->parme("course_id", 0);
                    $data = db("manage_course")
                        ->where($where)
                        ->where(['is_del' => 0, 'status' => 1])
                        ->page(input('page', '1'), input('pageshow', 16))
                        ->order($order)
                        ->select();
                    $count=count( db("manage_course")->where($where)->where(['is_del' => 0, 'status' => 1])->order($order)->select());

                }
                $data['count']=$count;
                $this->datas = $data;
//                $data  = db('manage_course_list')
//                    ->alias("a")
//                    ->join('manage_course b','a.course_id = b.course_id')
//                    ->where('a.course_id','a.list_id')
//                    ->where('b.course_id','in',$list)
//                    ->where(['a.is_del'=>0 , 'a.status'=>1,'b.is_del'=>0 , 'b.status'=>1 ])
//                    ->field('a.course_id,b.outline_id,b.course_id,b.video_name,b.author,b.video_img,b.video_hits,b.is_new,b.is_recom,b.is_hot')
//                    ->page(input('page','1'),input('pageshow',16))
//                    ->select();
            }
        }
    }

    //课程大纲
    public function course_outline()
    {
        $rule = [
            'outline_id|课程id' => 'require'
        ];
        $validate = new Validate($rule);
        if (!$validate->check($this->parme)) {
            $this->abnormal($this->ValitorError, $validate->getError());
        } else {
            $outline_id=$this->parme("outline_id");
            if(empty($outline_id) ){
                $this->abnormal($this->ValitorError,'参数错误');
            }
            $whereOr['outline_id']   =  $this->parme("outline_id");
            $whereOr['course_type']  =  $this->parme("outline_id");
            $data = db("manage_course")
                ->whereOr($whereOr)
                ->where(['is_del' => 0, 'status' => 1])
                ->select();

            $this->datas = $data;
        }

    }

    //课程搜索
    public function course_search()
    {
        $rule = [
            'video_name|课程id' => 'require'
        ];
        $validate = new Validate($rule);
        if (!$validate->check($this->parme)) {
            $this->abnormal($this->ValitorError, $validate->getError());
        }else{
            $video_name=$this->parme('video_name');
            $data=db("manage_course")
                ->where('video_name','like','%'.$video_name.'%')
                ->where(['is_del' => 0, 'status' => 1, 'course_type' => 0])
                ->page(input('page', '1'), input('pageshow', 16))
                ->select();
            $count=count(db("manage_course")->where('video_name','like','%'.$video_name.'%')->where(['is_del' => 0, 'status' => 1, 'course_type' => 0])->select());
            $data['count']=$count;
            $this->datas=$data;
        }
    }

    function getTree($data, $pId)
    {
        $tree = '';
        foreach($data as $k => $v)
        {
            if($v['list_id'] == $pId)
            {
                $v['data'] = $this->getTree($data, $v['course_id']);
                $tree[] = $v;
            }
        }
        return $tree;
    }

}