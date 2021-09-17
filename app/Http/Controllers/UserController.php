<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Redis;
//用户方法
class UserController extends Controller
{
    /*用户登入
     * */
        /*用户登入接口*/
    public function userlogin()
    {
        //组装数据
        $data = [];
        $data['telephone'] = '13313116873';
        $data['password'] = 'xinying@2021';
        $data['brand_id'] = '29';
        $data['app_id'] = '137';
        $data['type'] = 2;
        $data['language'] = 'en';
        $info = $this->GuzzleHttp($data,'login','POST',false,false,false);
        if($info['status'] == 200){
//            获取用户api_token
//            存放在redis
            Redis::del('api_token');
            Redis::set('api_token',$info['data']['api_token']);
            return $info['data']['api_token'];
        }

    }
//
//    //获取用户信息
//    public function userinfo(){
//
//    }

}