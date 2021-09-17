<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class Controller extends Command
{

    private $totalPageCount;
    private $counter = 1;
    private $concurrency = 1;  // 同时请求数量

    private $users = ['login'];

    /*用户登入
     * */
    private $api = 'https://gwdev.aoscdn.com/base/passport/v1/api/';//测试环境

    private $dataurl = 'https://gwdev.aoscdn.com/app/lightmv/';

    protected $signature = 'test:multithreading-request';
    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
    }

    /*
     * 验证数据参数是否为空
     * @data  == 验证数据
     * */
    public function isdata($data = [])
    {
        if ($data == []) return '验证数据为空请检查数据';
        foreach ($data as $key => $v) {
            if ($data[$key] === '') {
                return false;
            }
        }
    }

    /*
     * 监测是否是完整的json字符串
     * @string
     * */
    public function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /*
     * 更新token
     * */
    public function api_token()
    {
        $info = new UserController();
        $data = $info->userlogin();
        return $data;
    }

    /*
     * 通过用户id获取用户名
     * @uid 用户id
     * */

    public function getUserName($uid)
    {
        return DB::table('system_site')->where('id', '=', $uid)->value('username');
    }

    /**
     * 返回一个json
     * @param $code 状态码
     * @param $message 返回说明
     * @param $data 返回数据集合
     * @return false | string
     */
    private function jsonResponse($code, $message, $data)
    {
        $content = [
            'code' => $code,
            'msg' => $message,
            'data' => $data
        ];
        return json_encode($content, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 成功的时候返回结果
     * @param $data 返回数据集合
     * @return false | string
     */
    public function jsonSuccessData($data = [])
    {
        return $this->jsonResponse(200, 'success', $data);
    }

    /**
     * 失败的时候返回
     * @param $code 状态码
     * @param $message 返回说明
     * @param $data 返回数据集合
     * @return false | string
     */
    public function jsonErrorData($code, $message, $data = [])
    {
        return $this->jsonResponse($code, $message, $data);
    }

    /*
     * 视频下载
     * */
    public function videoGet($MethodName, $uid)
    {
        $url = $MethodName;
        $uid = $this->realid($uid);
        $path = "/mnt/cosfs-aivideo/upload/admin/" . $uid . '/';
//        $path = "/Users/admin/Desktop/image/" . $uid . '/';
        $datatime = time();
        $fp = fopen($datatime . ".mp4", "wb");
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        if (!is_dir($path)) {
            mkdir($path, 0777, true);  // 创建文件夹test,并给777的权限（所有权限）
            $file = base_path() . '/' . $datatime . ".mp4";
            rename($file, $path . $datatime . ".mp4"); //拷贝到新目录
            return $path . $datatime . ".mp4";
        } else {
            $file = base_path() . '/' . $datatime . ".mp4";
            rename($file, $path . $datatime . ".mp4");
            return $path . $datatime . ".mp4";
        }
    }
    public function move_file($fileFolder, $newPath, $reNameflag = false)
    {
        //1、首先先读取文件夹
        $temp = @scandir($fileFolder);
        //遍历文件夹
        foreach ($temp as $v) {
            $a = $fileFolder . '/' . $v;
            if (is_dir($a)) {//如果是文件夹则执行
                //判断是否为系统隐藏的文件.和..  如果是则跳过否则就继续往下走，防止无限循环再这里。
                if ($v == '.' || $v == '..') {
                    continue;
                }
                echo "<font color='red'>$a</font>", "<br/>"; //把文件夹红名输出
                //因为是文件夹所以再次调用自己这个函数，把这个文件夹下的文件遍历出来
                $this->move_file($a, $newPath, $reNameflag);
            } else {
                //echo $v,"<br/>";
                $newName = $v;
                if ($reNameflag) {
                    $newName = uniqid() . '.' . explode('.', $v)[1];
                }
                echo "已完成--", $newPath . '/' . $newName, "<br/>";
                copy($a, $newPath . '/' . $newName);
            }
        }
    }

    /*
     * 加密用户id
     * @uid
     * */
    public function realid($id, $type = 0)
    {
        if ($type) {
            //解密
            $realid = base_convert(substr($id, 3, -2), 36, 10);
            $realid = $realid / 6;
            if ($id == realid($realid, 0)) {
                return $realid;
            } else {
                return 0;
            }
        } else {
            //加密
            $id = $id * 6;
            $hash = md5(md5($id) . '-我是密钥@2020');
            if (substr($hash, 0, 1) == '0') {
                return '1' . substr($hash, 1, 2) . base_convert($id, 10, 36) . substr($hash, -2);
            } else {
                return substr($hash, 0, 3) . base_convert($id, 10, 36) . substr($hash, -2);
            }
        }
    }

    /*
     * 请求数据方法
     * */
    public function GuzzleHttp($data = [], $MethodName = '', $ask = 'POST', $isheaders = false, $isJson = false, $isuser = true)
    {
        $client = new Client([
            //跟域名
            'base_uri' => $isuser ? $this->dataurl : $this->api,
            // 超时
//            'timeout' => 2.0,
        ]);
        if ($isheaders) {
            if ($ask == 'GET') {
                $response = $client->request($ask, $MethodName, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . Redis::get('api_token')
                    ],
                    'query' => $data,
                ]);
            } else {
                if ($isJson) {
                    $response = $client->request($ask, $MethodName, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . Redis::get('api_token')
                        ],
                        'body' => $data,
                    ]);
                } else {
                    $response = $client->request($ask, $MethodName, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . Redis::get('api_token')
                        ],
                        'form_params' => $data,
                    ]);
                }
            }
        } else {
            if ($ask == 'GET') {
                $response = $client->request($ask, $MethodName, [
                    'query' => $data,
                ]);
            } else {
                if ($isJson) {
                } else {
                    $response = $client->request($ask, $MethodName, [
                        'form_params' => $data,
                    ]);
                }
            }
        }
        $body = $response->getStatusCode(); // 200
        if ($body == 200) {
            $response->getHeaderLine('content-type'); // 'application/json; charset=utf8'
            $newdata = json_decode($response->getBody(),true);
            if ($newdata['status'] == '-101') {
                //token错误重新获取请求
                $this->api_token();
                $this->GuzzleHttp($data, $MethodName, $ask, $isheaders, $isJson, $isuser);
            }
            return $newdata;
        } else {
            print_r($body);
        }
    }

   public function array_to_object($arr) {
        if (gettype($arr) != 'array') {
            return;
        }
        foreach ($arr as $k => $v) {
            if (gettype($v) == 'array' || getType($v) == 'object') {
                $arr[$k] = (object)array_to_object($v);
            }
        }

        return (object)$arr;
    }
}
