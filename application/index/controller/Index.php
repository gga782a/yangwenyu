<?php
/**
 * Created by PhpStorm.
 * User: ztwl
 * Date: 2018/11/8
 * Time: 下午3:57
 */

namespace app\index\controller;

class Index extends Common
{
    public function index()
    {
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




