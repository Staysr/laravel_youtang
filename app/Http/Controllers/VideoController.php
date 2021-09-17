<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\OssupdateImg;
use Illuminate\Support\Facades\Storage;
use OSS\Core\OssException;
use OSS\OssClient;
//视频方法
class VideoController extends Controller
{
    /*
     * 创建任务准备中
     * @$theme_id 模板id
     * @$resolution 任务比例
     * */
    public function installTask(Request $request)
    {
        //开始创建任务准备
        $theme_id = $request->input('theme_id');
        $resolution = $request->input('resolution');
        $uid = $request->input('uid');
        if ($theme_id == '' || $resolution == '' || $uid == '') {
            return $this->jsonErrorData(105, '参数值为空,请检查参数', '');
        }
        $is_user_status = DB::table('system_site')->where('id', '=', $uid)->value('status');
        if ($is_user_status == 0) {
            return $this->jsonErrorData(105, '该用户账号异常', '');
        } else {
            //查询该用户是否有权利发布任务
            $is_user_send = DB::table('system_site')->where('id', '=', $uid)->value('youtang_count');
            //后期会维护用户发布定值目前先写死
            if ($is_user_send < 20) {
                $status = $this->sendTask($theme_id, $resolution, $uid); //去创建任务
                try {
                    return $status['type'] == 1 ? $this->jsonSuccessData($status['data']) : $this->jsonErrorData(105, $status['data'], '');
                } catch (Exception $e) {
                    echo $e;
                }

            } else {
                return $this->jsonErrorData(105, '该用户暂无创建任务数量', '');
            }
        }

    }

    //创建任务
    public function sendTask($theme_id, $resolution, $uid)
    {
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $data = [];
        $data['api_token'] = $api_token; //用户凭证api_token（右糖登录接口获取)
        $data['language'] = 'zh'; //语言（en,fr,de,it,es,pt,nl,ja,zh,tw,cz,da,fi,gr,hu,no,pl,se,tr,sl,ru）
        $data['cli-os'] = 'web'; //终端（web, android, iphoneos, mac, windows）
        $data['theme_id'] = $theme_id; //模板ID
        $data['resolution'] = $resolution; //任务比例 可选 16x9 9x16 1x1
        $data['version'] = '3'; //请求版本（默认均用3，可选4）
        $info = $this->GuzzleHttp($data, "lm-task/create", 'POST', true);
        //处理返回参数错误
        if (!array_key_exists("data", $info)) {
            return ['type' => 2, 'data' => $info['error']];
        }
        try {
            $install = $info['data'];
            if ($info['status'] == 1) {
                $isTemplate = DB::table('templatetask')->where('task_id', '=', $install['task_id'])->first();
                if ($isTemplate) {
                    return $this->jsonErrorData(105, '任务重复,请重新创建', '');
                } else {
                    $install['task_project'] = json_encode($install['task_project']);
                    $install['theme_info'] = json_encode($install['theme_info']);
                    $install['pay_info'] = json_encode($install['pay_info']);
                    $install['add_time'] = time();
                    $install['uid'] = $uid;
                    $install['status'] = $info['status'] == 1 ? 0 : 2;
                    $install['statusinfo'] = $info['status'] == 1 ? '' : $info['error'];
                    $type = DB::table('templatetask')->insertGetId($install);
                    if ($type) {
                        $install['id'] = $type;
                        return ['type' => 1, 'data' => $install];
                    }
                }
            } else {
                print_r($info['status'] . $info['error']);
                return ['type' => 2, 'data' => $info['error']];
            }
        } catch (Exception $e) {
            if ($info['status'] == '-101') {
                $this->api_token();
            }
            echo $e;
        }

    }

    //更新用户发布次数
    public function add_user_send_num($id)
    {
        return DB::table('system_site')->where('id', '=', $id)->increment('youtang_count');
    }

    /*
     * 获取任务详情
     * */
    public function TaskInfo(Request $request)
    {
        $task_id = $request->input('task_id'); //任务id
        if ($task_id == '') {
            return $this->jsonErrorData(105, '参数为空', '');
        }
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $data = [];
        $data['api_token'] = $api_token; //用户凭证api_token（右糖登录接口获取)
        $data['language'] = 'zh'; //语言（en,fr,de,it,es,pt,nl,ja,zh,tw,cz,da,fi,gr,hu,no,pl,se,tr,sl,ru）
        $data['cli-os'] = 'web'; //终端（web, android, iphoneos, mac, windows）
        $data['version'] = 3; //请求版本（默认均用3，可选4）
        $data['task_id'] = $task_id; //请求版本（默认均用3，可选4）
        $info = $this->GuzzleHttp($data, "lm-task/detail", 'GET', true);
        $installdata = $info['data'];
        if ($info['status'] == 1) {
            if ($installdata['task_project'] == []) {
                $installdata['task_project'] = $this->array_to_object($installdata['task_project']);
            } else {
                $installdata['task_project'] = $installdata['task_project'];
            }
            if ($installdata['pay_info'] == []) {
                $installdata['pay_info'] = $this->array_to_object($installdata['pay_info']);
            } else {
                $installdata['pay_info'] = $installdata['pay_info'];
            }
            if ($installdata['theme_info'] == []) {
                $installdata['theme_info'] = $this->array_to_object($installdata['theme_info']);
            } else {
                $installdata['theme_info'] = $installdata['theme_info'];
            }
            return $this->jsonSuccessData($installdata);
        } else {
            return $this->jsonErrorData(105, '请求超时', '');
        }
    }

    /*
     * 任务信息修改
     * */
    public function TaskUpdate(Request $request)
    {
        $task = $request->all(); //任务id
        $data = [];
        foreach ($task as $key => $value) {
            if ($task['task_id'] == '') {
                return $this->jsonErrorData(105, '任务id不能为空', '');
            }
            if ($value == '') {
                unset($task[$key]);
            }
        }
        if (array_key_exists("resolution", $task)) {
            $data['resolution'] = $task['resolution'];
        }
        if (array_key_exists("title", $task)) {
            $data['title'] = $task['title'];
        }
        if (array_key_exists("task_complete_to_telephone", $task)) {
            $data['task_complete_to_telephone'] = $task['task_complete_to_telephone'];
        }
        if (array_key_exists("task_complete_to_telephone_country_num", $task)) {
            $data['task_complete_to_telephone_country_num'] = $task['task_complete_to_telephone_country_num'];
        }
        if (array_key_exists("task_complete_to_email", $task)) {
            $data['task_complete_to_email'] = $task['task_complete_to_email'];
        }
        if (array_key_exists("unsubscribe", $task)) {
            $data['unsubscribe'] = $task['unsubscribe'];
        }
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $data['api_token'] = $api_token; //用户凭证api_token（右糖登录接口获取)
        $data['language'] = 'zh'; //语言（en,fr,de,it,es,pt,nl,ja,zh,tw,cz,da,fi,gr,hu,no,pl,se,tr,sl,ru）
        $data['cli-os'] = 'web'; //终端（web, android, iphoneos, mac, windows）
        $data['version'] = 3; //请求版本（默认均用3，可选4）
        $data['task_id'] = $task['task_id']; //请求版本（默认均用3，可选4）
        $info = $this->GuzzleHttp(json_encode($data), "lm-task/save-attribute", 'PUT', true, true);
        try {
            if ($info['status'] == 1) {
                foreach ($data as $key => $value) {
                    if ($key == 'api_token' || $key == 'language' || $key == 'cli-os' || $key == 'version' || $key == 'task_id') {
                        unset($data[$key]);
                    }
                }
                $data['up_time'] = time();
                $type = DB::table('templatetask')->where('task_id', '=', $task['task_id'])->update($data);
                if ($type) {
                    return $this->jsonSuccessData(DB::table('templatetask')->where('task_id', '=', $task['task_id'])->first());
                } else {
                    return $this->jsonErrorData(105, '更新失败', '');
                }
            }
        } catch (Exception $e) {
            if ($info['status'] == '-101') {
                $this->api_token();
            }
            echo $e;
        }
    }

    //任务工程文件修改
    public function Task_engineering(Request $request)
    {
        $data = $request->all();
        if ($data['page_version'] == '' || $data['task_id'] == '' || $data['project_file'] == '' || $data['type'] == '' || $data['uid'] == '') {
            return $this->jsonErrorData(105, '参数错误!参数为空', '');
        }
        $newdata = [];
        $newdata['page_version'] = $data['page_version']; //页面版本号,7200S有效期（避免多端操作错误）
        $newdata['task_id'] = $data['task_id']; //任务ID
        $newdata['videodata'] = $data['project_file']; //任务工程文件Json字符串
        $newdata['type'] = $data['type']; //视频类型
        $newdata['username'] = $this->getUserName($data['uid']); //用户id
        $newdata['createtime'] = time(); //创建时间
        if ($this->isJson($data['project_file'])) {
            //查看当前任务是否有权利发布
            $status = DB::table('templatetask')->where('task_id', '=', $data['task_id'])->where('delete_type', '=', 0)->first();
            if ($status) {
                $type = DB::table('dsp_aivideo')->insertGetId($newdata);
//                DB::table('dsp_video')->insertGetId($dsp_video);
                $this->add_user_send_num($data['uid']);
                return $this->jsonSuccessData($type);
            } else {
                return $this->jsonErrorData(105, '任务异常,任务被锁定,或没有当前任务', '');
            }
        } else {
            return $this->jsonErrorData(105, 'JSON格式错误!请检查参数', '');
        }

    }

    /*
     * 任务工程文件修改副本
     * */
    public function Task_engineeringTwo(Request $request)
    {
        $data = $request->all();
        if ($data['vid'] == '' || $data['task_id'] == '' || $data['uid'] == '') {
            return $this->jsonErrorData(105, '参数错误!参数为空', '');
        }
        $dsp_video = [];
        $dsp_video['title'] = $data['title'];
        $dsp_video['tags'] = $data['tags'];
        $dsp_video['abstract'] = $data['abstract'] == '' ? '' : $data['abstract'];
        $dsp_video['videotype'] = $data['videotype'];
        $dsp_video['videofrom'] = $data['videofrom'];
        $dsp_video['thumb'] = $data['thumb'] == '' ? '' : $data['thumb'];
        $dsp_video['username'] = $this->getUserName($data['uid']); //用户id
        $dsp_video['atnickname'] = $data['atnickname'];
        $dsp_video['atuser'] = $data['atuser'];
        $dsp_video['type'] = $data['type']; //平台
        $dsp_video['account'] = $data['account'];
        $dsp_video['accuser'] = $data['accuser'];
        $dsp_video['accname'] = $data['accname'];
        $dsp_video['task_id'] = $data['task_id'];
        $dsp_video['sendtime'] = '211111111';
        //查询当前数据库有没有这条记录
        $is_videodata = DB::table('dsp_video')->where('task_id', '=', $data['task_id'])->first();
        if ($is_videodata) {
            return $this->jsonErrorData(105, '数据错误,任务重复-不可添加', '');
        } else {
            $add_dsp_video = DB::table('dsp_video')->insertGetId($dsp_video);
            if ($add_dsp_video) {
                $up = DB::table('dsp_aivideo')->where('task_id', '=', $data['task_id'])->update(['vid' => $data['vid'], 'is_rendering' => 2]);
                if ($up) {
                    return $this->jsonSuccessData($add_dsp_video);
                } else {
                    return $this->jsonErrorData(105, '失败,请检查参数', '');
                }
            }
        }
    }

    /*
     * 获取我的任务列表
     * @type
     * @per_page
     * @page
     * */
    public function Taskinfolist(Request $request)
    {
        $data = $request->all();
        if ($data['page'] == '' || $data['per_page'] == '') {
            return $this->jsonErrorData(105, '参数错误!参数为空', '');
        }
        if ($data['page'] < 1 || $data['per_page'] < 1) {
            return $this->jsonErrorData(105, '页码和条数必须大于1', '');
        }
        $newdata = [];
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $newdata['api_token'] = $api_token; //用户凭证api_token（右糖登录接口获取)
        $newdata['language'] = 'zh'; //语言（en,fr,de,it,es,pt,nl,ja,zh,tw,cz,da,fi,gr,hu,no,pl,se,tr,sl,ru）
        $newdata['cli-os'] = 'web'; //终端（web, android, iphoneos, mac, windows）
        $newdata['version'] = 3; //请求版本（默认均用3，可选4）
        $newdata['page'] = $data['page']; //页码，起始页码1
        $newdata['per_page'] = $data['per_page']; //每页数据条数，最小1
        if (array_key_exists("type", $data)) {
            $newdata['type'] = $data['type'];
        } else {
            $newdata['type'] = 'all';
        }
        if (array_key_exists("order_by", $data)) {
            $newdata['order_by'] = $data['order_by'];
        } else {
            $newdata['order_by'] = 'created_at';
        }
        if (array_key_exists("title", $data)) {
            $newdata['title'] = $data['title'];
        }
        $info = $this->GuzzleHttp($newdata, "lm-task/list", 'GET', true, true);
        if ($info['status'] == 1) {
            return $this->jsonSuccessData($info['data']);
        } else {
            return $this->jsonErrorData(105, $info['error'], '');
        }
    }

    /*
     * 复制任务
     * @task_id 任务id
     * */
    public function Taskcopy(Request $request)
    {
        $task_id = $request->input('task_id'); //任务id
        $uid = $request->input('uid'); //用户id
        if ($task_id == '' || $uid == '') {
            return $this->jsonErrorData(105, '参数错误!参数为空', '');
        }
        $newdata = [];
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $newdata['api_token'] = $api_token; //用户凭证api_token（右糖登录接口获取)
        $newdata['language'] = 'zh'; //语言（en,fr,de,it,es,pt,nl,ja,zh,tw,cz,da,fi,gr,hu,no,pl,se,tr,sl,ru）
        $newdata['cli-os'] = 'web'; //终端（web, android, iphoneos, mac, windows）
        $newdata['version'] = 3; //请求版本（默认均用3，可选4）
        $newdata['task_id'] = $task_id; //请求版本（默认均用3，可选4）
        $info = $this->GuzzleHttp(json_encode($newdata), "lm-task/copy", 'POST', true, true);
        if ($info['status'] == 1) {
            $install = $info['data'];
            $isTemplate = DB::table('templatetask')->where('task_id', '=', $install['task_id'])->first();
            if ($isTemplate) {
                return $this->jsonErrorData(105, '任务重复,请重新创建', '');
            } else {
                $install['task_project'] = json_encode($install['task_project']);
                $install['theme_info'] = json_encode($install['theme_info']);
                $install['pay_info'] = json_encode($install['pay_info']);
                $install['add_time'] = time();
                $install['uid'] = $uid;
                $install['status'] = $info['status'] == 1 ? 0 : 2;
                $install['statusinfo'] = $info['status'] == 1 ? '' : $info['error'];
                $type = DB::table('templatetask')->insertGetId($install);
                if ($type) {
                    $install['id'] = $type;
                    $this->add_user_send_num($uid);
                    return $this->jsonSuccessData($install);
                }
            }
        } else {
            return $this->jsonErrorData(105, $info['error'], '');
        }

    }


    /*
     * 删除任务
     * @task_id 任务id
     * */
    public function TaskDelete(Request $request)
    {

        $task_id = $request->input('task_id'); //任务id
        $type = $request->input('type'); //删除模式
        if ($task_id == '' || $type == '') {
            return $this->jsonErrorData(105, '参数错误!参数不能为空', '');
        }
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $data = [];
        $data['api_token'] = $api_token; //用户凭证api_token（右糖登录接口获取)
        $data['language'] = 'zh'; //语言（en,fr,de,it,es,pt,nl,ja,zh,tw,cz,da,fi,gr,hu,no,pl,se,tr,sl,ru）
        $data['cli-os'] = 'web'; //终端（web, android, iphoneos, mac, windows）
        $data['version'] = 3; //请求版本（默认均用3，可选4）
        $data['task_id'] = $task_id; //任务id
        if ($type == 1) { //丢弃废纸篓
            $status = DB::table('templatelist')->where('task_id', '=', $task_id)->update(['delete_type' => 1]);
            if ($status) {
                return $this->jsonSuccessData($status);
            } else {
                return $this->jsonErrorData(105, '删除失败', '');
            }
        } else {

            $info = $this->GuzzleHttp(json_encode($data), "lm-task/delete", 'DELETE', true, true);
            print_r($info);
//            $installdata = $info['data'];
            if ($info['status'] == 1) {
                $delete = DB::table('templatetask')->where('task_id', '=', $task_id)->delete();
                if ($delete) {
                    return $this->jsonSuccessData($delete);
                } else {
                    return $this->jsonErrorData(105, '删除失败', '');
                }
            } else {
                return $this->jsonErrorData(105, $info['error'], '');
            }
        }
    }

    /*
     * 取消物理删除
     * @task_id 任务id
     * */
    public function Taskcancel(Request $request)
    {
        $task_id = $request->input('task_id'); //任务id
        if ($task_id == '') return $this->jsonErrorData(105, '任务id不能为空', '');
        return $this->jsonSuccessData(DB::table('templatetask')->where('task_id', '=', $task_id)->update(['delete_type' => 0]));
    }

    /*
     * 取消渲染
     * @task_id 任务id
     * */
    public function Taskcancelrendering(Request $request)
    {
        $task_id = $request->input('task_id'); //任务id
        if ($task_id == '') return $this->jsonErrorData(105, '任务id不能为空', '');
        //判断当然任务是否支持取消渲染
        $celren = DB::table('templatetask')->where('task_id', '=', $task_id)->value('rendering');
        if ($celren != 1) {
            return $this->jsonErrorData(105, '当前任务不支持取消渲染', '');
        } else {
            $api_token = Redis::get('api_token');
            if ($api_token == '') $api_token = $this->api_token();
            $data = [];
            $data['api_token'] = $api_token; //用户凭证api_token（右糖登录接口获取)
            $data['language'] = 'zh'; //语言（en,fr,de,it,es,pt,nl,ja,zh,tw,cz,da,fi,gr,hu,no,pl,se,tr,sl,ru）
            $data['cli-os'] = 'web'; //终端（web, android, iphoneos, mac, windows）
            $data['version'] = 3; //请求版本（默认均用3，可选4）
            $data['task_id'] = $task_id; //任务id
            $info = $this->GuzzleHttp(json_encode($data), "lm-render/cancle", 'POST', true, true);
            if ($info['status'] == 1) {
                $offvideo = DB::table('dsp_aivideo')->where('task_id', '=', $task_id)->update(['is_off' => 2]);
                if ($offvideo) {
                    return $this->jsonSuccessData($offvideo);
                } else {
                    return $this->jsonErrorData(105, '数据错误,取消渲染错误', '');
                }
            } else {
                return $this->jsonErrorData(105, $info['error'], '');
            }

        }
    }

    /*
     * 任务预览 & 分享
     * @task_id
     * */
    public function Taskshare(Request $request)
    {
        $task_id = $request->input('task_id'); //任务id
        if ($task_id == '') return $this->jsonErrorData(105, '任务id不能为空', '');
        //验证是否有当前任务
        $type = DB::table('templatetask')->where('task_id', '=', $task_id)->where('delete_type', '=', 0)->first();
        if ($type) {
            $data = [];
            $data['language'] = 'zh'; //语言（en,fr,de,it,es,pt,nl,ja,zh,tw,cz,da,fi,gr,hu,no,pl,se,tr,sl,ru）
            $data['cli-os'] = 'web'; //终端（web, android, iphoneos, mac, windows）
            $data['version'] = 3; //请求版本（默认均用3，可选4）
            $data['task_id'] = $task_id; //任务id
            $info = $this->GuzzleHttp($data, "lm-task/preview", 'GET', true);
            if ($info['status'] == 1) {
                return $this->jsonSuccessData($info['data']);
            } else {
                return $this->jsonErrorData(105, $info['error'], '');
            }
        } else {
            return $this->jsonErrorData(105, '任务不存在,或被锁定', '');
        }
    }

    /*
     *获取渲染进度
     * @task_id
     * */
    public function TaskRendering(Request $request)
    {
        $task_id = $request->input('process_list'); //渲染进程id
        if ($task_id == '') return $this->jsonErrorData(105, '渲染进程id不能为空', '');
        $data = [];
        $data['process_list'] = $task_id;
        $info = $this->GuzzleHttp($data, "lm-render/progress", 'GET', true, true);
        if ($info['status'] == 1) {
            return $this->jsonSuccessData($info['data']);
        } else {
            return $this->jsonErrorData(105, $info['error'], '');
        }
    }

    /*
     *获取模板列表
     * @type
     * @page
     * @limt
     * */
    public function Tasktemplatelist(Request $request)
    {
        $task_id = $request->input('type');
        $pagenNum = $request->input('page');//页数
        $limit = $request->input('limit');
        $uid = $request->input('uid');
        $offset = $limit * ($pagenNum - 1);
        if ($task_id == '' || $pagenNum == '' || $limit == '' || $uid == '') {
            return $this->jsonErrorData(105, '参数错误!参数为空');
        }
        //查询当前用户是否有权利去获取模板列表
        $is_user = DB::table('system_site')->where('id', '=', $uid)->value('status');
        if ($is_user == 1) {
            if ($task_id == 'all') {
                $list = DB::table('templatelist')
                    ->orderBy('id', 'desc')
                    ->offset($offset)
                    ->limit($limit)
                    ->get()->toArray();
            } else {
                $list = DB::table('templatelist')
                    ->where('theme_type', '=', $task_id)
                    ->orderBy('id', 'desc')
                    ->offset($offset)
                    ->limit($limit)
                    ->get()->toArray();
            }
            if ($list) {
                return $this->jsonSuccessData($list);
            } else {
                return $this->jsonErrorData(105, '数据异常', '');
            }
        } else {
            return $this->jsonErrorData(105, '用户状态异常', '');
        }
    }

    /*
     * 获取专题列表
     * @page
     * @limt
     * */
    public function TaskTopic(Request $request)
    {
        $pagenNum = $request->input('page');//页数
        $limit = $request->input('limit');
        $uid = $request->input('uid');
        $offset = $limit * ($pagenNum - 1);
        if ($pagenNum == '' || $limit == '' || $uid == '') {
            return $this->jsonErrorData(105, '参数错误!参数为空');
        }
        $is_user = DB::table('system_site')->where('id', '=', $uid)->value('status'); //查询当前用户是否正常
        if ($is_user == 1) {
            $list = DB::table('templatetopic')
                ->orderBy('id', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get()->toArray();
            if ($list) {
                return $this->jsonSuccessData($list);
            } else {
                return $this->jsonErrorData(105, '数据异常', '');
            }
        } else {
            return $this->jsonErrorData(105, '用户状态异常', '');
        }
    }

    /*
     * 获取模板分类
     * @uid
     * @class_id
     * */
    public function Tasktemplateclass(Request $request)
    {
        $uid = $request->input('uid');
        $class_id = $request->input('class_id');
        if ($uid == '') {
            return $this->jsonErrorData(105, '参数错误,参数不能为空', '');
        }
        $is_user = DB::table('system_site')->where('id', '=', $uid)->value('status'); //查询当前用户是否正常
        if ($is_user == 1) {
            if ($class_id == '') {
                $class = DB::table('templateclass')->where('class_id', '=', 0)->get()->toArray();
                return $this->jsonSuccessData($class);
            } else {
                $ficlass = DB::table('templateclass')->where('id', '=', $class_id)->get()->toArray();
                return $this->jsonSuccessData($ficlass);
            }
        } else {
            return $this->jsonErrorData(105, '用户状态异常', '');
        }
    }

    /*
     * 上传图片 获取获取STS临时上传授权
     * */
    public function TaskAddImgOss(Request $request)
    {
        $type = $request->input('type');
        $tmp = $request->file('file');
        $uid = $request->input('uid');
        $task_id = $request->input('task_id');
        if (is_null($tmp) || is_null($uid) || is_null($type) || is_null($task_id)) {
            return $this->jsonErrorData(105, '参数错误,参数不能为空', '');
        }
        //查询当前用户是否可以上传图片
        $is_user = DB::table('system_site')->where('id', '=', $uid)->value('status');
        if ($is_user == 1) {
            $path = '/article/';
            if ($tmp->isValid()) { //判断文件上传是否有效
                $FileType = $tmp->getClientOriginalExtension(); //获取文件后缀

                $FilePath = $tmp->getRealPath(); //获取文件临时存放位置

                $FileName = date('Y-m-d') . uniqid() . '.' . $FileType; //定义文件名

                Storage::disk('article')->put($FileName, file_get_contents($FilePath)); //存储文件

                $data = [
                    'code' => 200,
                    'imginfo' => $tmp,
                    'path' => base_path() . '/public' . $path. $FileName, //文件路径
                    'FileName' => $FileName,
                    'uid' => $uid,
                    'task_id' => $task_id,
                    'type' => $type,
                    'tmp' => $tmp,
                    'FileType'=>$FileType
                ];
                $info = $this->upload_auth($data);
                return $this->jsonSuccessData($info);
            }

        } else {
            return $this->jsonErrorData(105, '用户状态异常', '');
        }
    }

    /*
     * 获取上传图片的临时上传授权
     * $newdata
     * */
    public function upload_auth($newdata)
    {
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $data = [];
        $data['api_token'] = $api_token;
        $data['cli-os'] = 'web';
        $data['version'] = '3';
        $data['language'] = 'zh';
        $info = $this->GuzzleHttp($data, "upload-auth", 'GET', true, true);
        $IMstall = $info['data'];
        $addimg = new OssupdateImg();
        $imgdata = [];
        $imgdata['path'] = $newdata['path'];
        $imgdata['accessKeyId'] = $IMstall['access_id'];
        $imgdata['accessKeySecret'] = $IMstall['access_secret'];
        $imgdata['endpoint'] = $IMstall['endpoint'];
        $imgdata['bucket'] = $IMstall['bucket'];
        $imgdata['FileName'] = $newdata['FileName'];
        $imgdata['SecurityToken'] = $IMstall['security_token'];
        $imgdata['callback'] = $IMstall['callback'];
        $imgdata['uid'] = $newdata['uid'];
        $imgdata['task_id'] = $newdata['task_id'];
        $imgdata['type'] = $newdata['type'];
        $imgdata['oss'] = $IMstall['path'];
        $imgdata['tmp'] = $newdata['tmp'];
        $imgdata['FileType'] = $newdata['FileType'];
        return $addimg->uploadOne($imgdata);
    }

    /*
     * 获取素材库信息
     * */
    public function Taskmaterialinfo(Request $request)
    {
        $page = $request->input('page');
        $per_page = $request->input('per_page');
        if ($page == '' || $per_page == '') {
            return $this->jsonErrorData(105, '参数错误,参数不能为空', '');
        }
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $data = [];
        $data['api_token'] = $api_token;
        $data['cli-os'] = 'web';
        $data['version'] = '3';
        $data['language'] = 'zh';
        $data['page'] = '1';
        $data['per_page'] = '10';
        $info = $this->GuzzleHttp($data, "resources/directory-info", 'GET', true, true);
        if ($info['status'] == 1) {
            return $this->jsonSuccessData();
        } else {
            return $this->jsonErrorData(105, $info['error'], '');
        }
    }

    /*
     * 获取获取资源详情地址（单个 & 批量）
     * @resource_ids
     * */
    public function TaskResourceDetails(Request $request)
    {
        $resource_ids = $request->input('resource_ids');
        if (is_null($resource_ids)) return $this->jsonErrorData(105, '参数错误', '');
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $data = [];
        $data['api_token'] = $api_token;
        $data['cli-os'] = 'web';
        $data['version'] = '3';
        $data['resource_ids'] = $resource_ids;
        $info = $this->GuzzleHttp(json_encode($data), "resources/info", 'POST', true, true);
        if ($info['status'] == 1) {
            return $this->jsonSuccessData($info['data']);
        } else {
            return $this->jsonErrorData(105, $info['error'], '');
        }
    }

    /*
     * 获取资源列表
     * @uid
     * @task_id
     * */
    public function TaskResourceList(Request $request)
    {
        $uid = $request->input('uid');
        $task_id = $request->input('task_id');
        if (is_null($uid) || is_null($task_id)) return $this->jsonErrorData(105,'参数错误,参数不能为空','');
        $info = DB::table('resourceall')->where('task_id','=',$task_id)->where('uid','=',$uid)->select('id','uid','type','task_id','resource_id','filename','size','path_url')->get()->toArray();
        if ($info){
            return $this->jsonSuccessData($info);
        }else{
            return $this->jsonErrorData(105,'数据为空','');
        }
    }
}