<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

//视频发布方法
class VideoreleaseController extends Controller
{
    /*
     * 发布创建工程文件
     * @$theme_id 模板id
     * @$resolution 任务比例
     * */
    public function sendTask()
    {
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $videoData = DB::table('dsp_aivideo')->where('type', '=', 'free')->where('runtime', '=', 0)->where('is_off', '=', 1)->select('page_version', 'videodata', 'task_id')->limit(5)->get()->map(function ($value) {
            return (array)$value;
        })->toArray();
        if ($videoData != []) {
            foreach ($videoData as $value) {
                $data = [];
                $data['api_token'] = $api_token;
                $data['language'] = 'zh';
                $data['version'] = '3';
                $data['cli-os'] = 'web';
                $data['page_version'] = $value['page_version'];
                $data['task_id'] = $value['task_id'];
                $data['project_file'] = $value['videodata'];
                $info = $this->GuzzleHttp(json_encode($data), "lm-task/save-json", 'PUT', true, true);
                if ($info['status'] == 1) {
                    $upsuccess = DB::table('templatetask')->where('task_id', '=', $value['task_id'])->update(['rendering' => 2]);
                    $info = $this->Rendering($value['task_id']);
                    print_r($info);
                } else {
                    $aivideoError = DB::table('dsp_aivideo')->where('task_id', '=', $value['task_id'])->update(['videoalert' => $info['error'], 'videotime' => time()]);
                }
            }
        } else {
            echo '暂无发布json数据';
        }
    }

    /*
     * 开始渲染 未完成
     * @$task_id
     * */
    public function Rendering($task_id)
    {
        //查看当前任务是否正常
//        $task_id = '9tqvapx';
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $is_rendering = DB::table('dsp_aivideo')->where('task_id','=',$task_id)->value('is_rendering');
        if ($is_rendering == 2) {
            $is_Task = DB::table('templatetask')->where('task_id', '=', $task_id)->where('delete_type', '=', 0)->first();
            if ($is_Task) {
                $data = [];
                $output_type = '1080';
                $data['api_token'] = $api_token;
                $data['language'] = 'zh';
                $data['version'] = '3';
                $data['cli-os'] = 'web';
                $data['task_id'] = $task_id;
                $data['output_type'] = $output_type; //当前选择 720 后期改动
                $info = $this->GuzzleHttp(json_encode($data), "lm-render/start", 'POST', true, true);
                print_r($info);
                if($info['status'] == 1 ||  $info['status'] == -160){
                    //处理渲染异常操作
                    switch ($info['status']) {
                        case '1': //成功
                            $this->seussstart($info['data'],$task_id);
                            break;
                        case '-160':
                            $this->offUnpaid($task_id,$output_type); //未支付
                            break;
                        default:
                    }
                }else{
                    //渲染失败
                    $this->fail($task_id,$info['error']);
                }

            } else {
                return '任务异常,不能渲染';
            }
        }else{
            return "当前用户未点击最终确认,正在等待........";
        }
    }

    /*
     * 渲染成功操作
     * @process_list
     * @$task_id
     * */
    public function seussstart($process_list,$task_id){
        $type = DB::table('templatetask')->where('task_id','=',$task_id)->update(['process_list'=>$process_list,'rendering'=>2]);
        $dsp_aivideo = DB::table('dsp_aivideo')->where('task_id','=',$task_id)->update(['process_list'=>$process_list,'runtime'=>time()]);
    }
    /*
     * 渲染失败
     * @task_id
     * */
    public function fail($task_id,$infoerror){
        $templatetask = DB::table('templatetask')->where('task_id','=',$task_id)->update(['rendering'=>4]);
        $dsp_aivideo = DB::table('dsp_aivideo')->where('task_id','=',$task_id)->update(['runtime'=>time(),'videoalert'=>$infoerror]);
        $uid = DB::table('templatetask')->where('task_id','=',$task_id)->value('uid');
        $status = DB::table('system_site')->where('id','=',$uid)->value('youtang_count');
        if($status < 0){
            $system_site = DB::table('system_site')->where('id','=',$uid)->decrement('youtang_count');
        }
    }
    /*
     * 渲染未支付状态 未完成
     * @task_id
     * @output_type
     * */
    public function offUnpaid(){
        $task_id = 'hltsvei';
        $output_type = '1080';
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $data = [];
        $data['api_token'] = $api_token;
        $data['language'] = 'zh';
        $data['version'] = '3';
        $data['cli-os'] = 'web';
        $data['task_id'] = $task_id;
        $data['output_type'] = $output_type;
        $data['pay_type'] = 'vip_num';
        $info = $this->GuzzleHttp(json_encode($data), "lm-task/pay", 'POST', true, true);
        print_r($info);
    }

    /*
     * 获取渲染进度
     * */
    public function progress(){
        $videoData = DB::table('templatetask')->where('rendering', '=', 2)->where('delete_type', '=', 0)->select('process_list','task_id')->get()->map(function ($value) {
            return (array)$value;
        })->toArray();
        if($videoData != []){
            foreach ($videoData as $value){
                $data = [];
                $data['process_list'] = $value['process_list'];
                $info = $this->GuzzleHttp($data, "lm-render/progress", 'GET', true, true);
                print_r($info);
                if($info['status'] == 1){
                    if($info['data'][0]['progress'] == 100){ //当前渲染完毕
                        //渲染完毕获取下载视频
                        DB::table('templatetask')->where('task_id','=',$value['task_id'])->update(['rendering'=>3]);
                        DB::table('dsp_aivideo')->where('task_id','=',$value['task_id'])->update(['videotime'=>time()]);
                        $this->geturldata($value['task_id']);
                    }
                }else{
                    print_r($info['error']);
                }
            }
        }else{
          echo '当前无渲染模板进程';
        }
    }

    /*
     * 获取视频下载链接
     * task_id
     * */
    public function geturldata($task_id){
        print_r($task_id);
//        $task_id = '2s3dyfw';
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $data = [];
        $data['api_token'] = $api_token;
        $data['language'] = 'zh';
        $data['version'] = '3';
        $data['cli-os'] = 'web';
        $data['task_id'] = $task_id;
        $data['output_type'] = '1080';
        $info = $this->GuzzleHttp($data, "lm-task/download", 'GET', true, true);
        if($info['status'] == 1  && $info['data'] != []){
            $uid = DB::table('templatetask')->where('task_id','=',$task_id)->value('uid');
            $tt = $info['data']['video'];
            $pash = $this->videoGet($tt,$uid);
            if($pash){
                DB::table('dsp_aivideo')->where('task_id','=',$task_id)->update(['videotime'=>time()]);
                DB::table('dsp_video')->where('task_id','=',$task_id)->update(['video'=>$pash]);
            }else{
                echo '链接错误';
            }
        }else{
            return $info;
        }

    }

}