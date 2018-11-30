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
use think\Session;

class Index extends Common
{
    public $member_id;
    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->member_id = $this->check();
    }

    public function index()
    {
        if(!$this->member_id){
            return redirecturl('index');
        }
        return view('index');
    }

    public function more()
    {
        if(!$this->member_id){
            return redirecturl('more');
        }
        return view('more');
    }
    public function address()
    {
        if(!$this->member_id){
            return redirecturl('address');
        }
        return view('address');
    }

    public function business()
    {
        if(!$this->member_id){
            return redirecturl('business');
        }
        return view('business');
    }
    public function cardDetails()
    {
        if(!$this->member_id){
            return redirecturl('cardDetails');
        }
        return view('cardDetails');
    }

    public function detail()
    {
        if(!$this->member_id){
            return redirecturl('detail');
        }
        return view('detail');
    }
    public function discountTabs()
    {
        if(!$this->member_id){
            return redirecturl('discountTabs');
        }
        return view('discountTabs');
    }

    public function exchange()
    {
        if(!$this->member_id){
            return redirecturl('exchange');
        }
        return view('exchange');
    }
    public function exchangeShop()
    {
        if(!$this->member_id){
            return redirecturl('exchangeShop');
        }
        return view('exchangeShop');
    }

    public function joinln()
    {
        if(!$this->member_id){
            return redirecturl('joinln');
        }
        return view('joinln');
    }
    public function my()
    {
        if(!$this->member_id){
            return redirecturl('my');
        }
        return view('my');
    }

    public function orderPay()
    {
        if(!$this->member_id){
            return redirecturl('orderPay');
        }
        return view('orderPay');
    }
    public function payment()
    {
        if(!$this->member_id){
            return redirecturl('payment');
        }
        return view('payment');
    }

    public function paythebill()
    {
        if(!$this->member_id){
            return redirecturl('paythebill');
        }
        return view('paythebill');
    }
    public function receiving()
    {
        if(!$this->member_id){
            return redirecturl('receiving');
        }
        return view('receiving');
    }

    public function sharelt()
    {
        if(!$this->member_id){
            return redirecturl('sharelt');
        }
        return view('sharelt');
    }

    public function shopDetailList()
    {
        if(!$this->member_id){
            return redirecturl('shopDetailList');
        }
        return view('shopDetailList');
    }
    public function shopIndex()
    {
        if(!$this->member_id){
            return redirecturl('shopIndex');
        }
        return view('shopIndex');
    }

    public function shopList()
    {
        if(!$this->member_id){
            return redirecturl('shopList');
        }
        return view('shopList');
    }
    public function tabs()
    {
        if(!$this->member_id){
            return redirecturl('tabs');
        }
        return view('tabs');
    }

    public function zipCode()
    {
        if(!$this->member_id){
            return redirecturl('zipCode');
        }
        return view('zipCode');
    }
}




