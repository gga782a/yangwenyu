<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/8
 * Time: 下午3:57
 */

namespace app\index\controller;

use think\Cookie;
use think\Request;

class Index extends Common
{
//    public $member_id;
//    public function __construct(Request $request = null)
//    {
//        parent::__construct($request);
//        $this->member_id = Session::get('member_id');
//    }

    public function index()
    {
        dd(2223333);
        dd(Cookie::get('member_id'));
        var_dump(22111);
        return view('index');
    }

    public function more()
    {
        return view('more');
    }
    public function address()
    {
        return view('address');
    }

    public function business()
    {
        return view('business');
    }
    public function cardDetails()
    {
        return view('cardDetails');
    }

    public function detail()
    {
        return view('detail');
    }
    public function discountTabs()
    {
        return view('discountTabs');
    }

    public function exchange()
    {
        return view('exchange');
    }
    public function exchangeShop()
    {
        return view('exchangeShop');
    }

    public function joinln()
    {
        return view('joinln');
    }
    public function my()
    {
        return view('my');
    }

    public function orderPay()
    {
        return view('orderPay');
    }
    public function payment()
    {
        return view('payment');
    }

    public function paythebill()
    {
        return view('paythebill');
    }
    public function receiving()
    {
        return view('receiving');
    }

    public function sharelt()
    {
        return view('sharelt');
    }

    public function shopDetailList()
    {
        return view('shopDetailList');
    }
    public function shopIndex()
    {
        return view('shopIndex');
    }

    public function shopList()
    {
        return view('shopList');
    }
    public function tabs()
    {
        return view('tabs');
    }

    public function zipCode()
    {
        return view('zipCode');
    }
}




