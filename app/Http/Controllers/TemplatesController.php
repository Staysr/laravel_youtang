<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

//模板方法
class TemplatesController extends Controller
{
    /*
     * 获取模板
     * */
    public function Templates()
    {
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token =  $this->api_token();
        $datainfo = [];
        $datainfo['language'] = 'zh';
        $datainfo['support_webp'] = 0;
        $datainfo['cli-os'] = 'web';
        $datainfo['api_token'] = $api_token;
        $info = $this->GuzzleHttp($datainfo, "theme/list", 'GET');
        try {
            $data1 = [];
            $data1['language'] = 'zh';
            $data1['support_webp'] = '0';
            $data1['cli-os'] = 'web';
            $data1['api_token'] = $api_token;
            $data1['page'] = '1';
            $data1['per_page'] = $info['data']['total'];
            $newdata = $this->GuzzleHttp($data1, "theme/list",'GET');
            if ($newdata['status'] == 1) {
                $install = $newdata['data']['list'];
                $isTemplate = DB::table('templatelist')->select('theme_id')->get()->map(function ($value) {
                    return (array)$value;
                })->toArray();
                if ($isTemplate == []) {
                    foreach ($install as $key => $value) {
//                        if()
                        $value['add_time'] = time();
                        $value['description'] = $value['description'] == '' ? '' : $value['description'];
                        $value['support_resolution'] = json_encode($value['support_resolution']);
                        $value['tag_name'] = json_encode($value['tag_name']);
                        $value['scene'] = $value['scene'] == [] ? '' : json_encode($value['scene']);
                        $value['scene_detail'] = $value['scene_detail'] == [] ? '' : json_encode($value['scene_detail']);
                        $value['theme_price'] = json_encode($value['theme_price']);
                        $value['statistics'] = json_encode($value['statistics']);
                        $value['best_resolution'] = json_encode($value['best_resolution']);
                        $value['preview_resolution'] = json_encode($value['preview_resolution']);
                        DB::table('templatelist')->insertGetId($value);
                        print_r($value);
                    }
                } else {
                    foreach ($install as $value) {
                        if ($this->isAllExists($value['theme_id'], $isTemplate, 'theme_id')) {
                            $value['add_time'] = time();
                            $value['description'] = $value['description'] == '' ? '' : $value['description'];
                            $value['support_resolution'] = json_encode($value['support_resolution']);
                            $value['tag_name'] = json_encode($value['tag_name']);
                            $value['scene'] = $value['scene'] == [] ? '' : json_encode($value['scene']);
                            $value['scene_detail'] = $value['scene_detail'] == [] ? '' : json_encode($value['scene_detail']);
                            $value['theme_price'] = json_encode($value['theme_price']);
                            $value['statistics'] = json_encode($value['statistics']);
                            $value['best_resolution'] = json_encode($value['best_resolution']);
                            $value['preview_resolution'] = json_encode($value['preview_resolution']);
                            DB::table('templatelist')->insertGetId($value);
                            print_r($value);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            if ($info['status'] == '-101') {
                $this->api_token();
            }
            echo $e;
        }
    }

    /*
     * 获取模板分类
     * */
    public function TemplatesClass()
    {
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token =  $this->api_token();
        $datainfo = [];
        $datainfo['language'] = 'zh';
        $datainfo['orderby'] = 'normal';
        $datainfo['cli-os'] = 'web';
        $info = $this->GuzzleHttp($datainfo, "theme/tags-new", 'GET');
        $install = $info['data'];
        try {
            if ($info['status'] == 1) {
                $isTemplate = DB::table('templateclass')->select('tag_id')->get()->map(function ($value) {
                    return (array)$value;
                })->toArray();
                if($isTemplate == []){
                    foreach ($install as $value){
                        $data = [];
                        $data['add_time'] = time();
                        $data['tag_id'] = $value['tag_id'];
                        $data['tag_name'] = $value['tag_name'];
                        $data['brief_name'] = $value['brief_name'];
                        $data['style'] = $value['style'];
                        $data['fid'] = '';
                        $data['class_id'] = 0;
                        $type = DB::table('templateclass')->insertGetId($data);
                        if($type){
                            $children_tag = $value['children_tag'];
                            foreach ($children_tag as $v){
                                $v['add_time'] = time();
                                $v['tag_id'] = $value['tag_id'];
                                $v['tag_name'] = $value['tag_name'];
                                $v['brief_name'] = $value['brief_name'];
                                $v['style'] = $value['style'];
                                $v['fid'] = $v['fid'] == '' ? '' : implode(",", $v['fid']);
                                $v['class_id'] = $type;
                                $type = DB::table('templateclass')->insertGetId($v);
                                print_r($type);
                            }
                        }
                    }
                }else{
                    foreach ($install as $value){
                        if ($this->isAllExists($value['tag_id'], $isTemplate, 'tag_id')) {
                            $data = [];
                            $data['add_time'] = time();
                            $data['tag_id'] = $value['tag_id'];
                            $data['tag_name'] = $value['tag_name'];
                            $data['brief_name'] = $value['brief_name'];
                            $data['style'] = $value['style'];
                            $data['fid'] = '';
                            $data['class_id'] = 0;
                            $type = DB::table('templateclass')->insertGetId($data);
                            if($type){
                                $children_tag = $value['children_tag'];
                                foreach ($children_tag as $v){
                                    $v['add_time'] = time();
                                    $v['tag_id'] = $value['tag_id'];
                                    $v['tag_name'] = $value['tag_name'];
                                    $v['brief_name'] = $value['brief_name'];
                                    $v['style'] = $value['style'];
                                    $v['fid'] = $v['fid'] == '' ? '' : implode(",", $v['fid']);
                                    $v['class_id'] = $type;
                                    $type = DB::table('templateclass')->insertGetId($v);
                                    print_r($type);
                                }
                            }
                        }
                    }
                }

            }
        } catch (Exception $e) {
            if ($info['status'] == '-101') {
                $this->api_token();
            }
            echo $e;
        }
    }

    /*
     * 获取换题模板接口
     * */
    public function Topic(){
        $api_token = Redis::get('api_token');
        if ($api_token == '') $api_token = $this->api_token();
        $datainfo = [];
        $datainfo['language'] = 'zh';
        $datainfo['cli-os'] = 'web';
        $info = $this->GuzzleHttp($datainfo, "theme/topic-info", 'GET');
        $install = $info['data'];
        try {
            if ($info['status'] == 1) {
                $isTemplate = DB::table('templatetopic')->select('zid as id')->get()->map(function ($value) {
                    return (array)$value;
                })->toArray();
                if($isTemplate == []){
                    foreach ($install as $value){
                        $value['add_time'] = time();
                        $value['zid'] = $value['id'];
                        DB::table('templatetopic')->insertGetId($value);
                        print_r($value);
                    }
                }else{
                    foreach ($install as $value){
                        if ($this->isAllExists($value['id'], $isTemplate, 'id')) {
                            $value['add_time'] = time();
                            $value['zid'] = $value['id'];
                            DB::table('templatetopic')->insertGetId($value);
                            print_r($value);
                        }
                    }
                }
            }
        }catch (Exception $e) {
            if ($info['status'] == '-101') {
                $this->api_token();
            }
            echo $e;
        }
    }

    /**
     * *
     * 判断一个数组是否存在于另一个数组中
     *
     * @param $arr
     * @param $allArr
     * @return boolean
     */
    function isAllExists($arr, $allArr, $key)
    {
        if (!empty($arr) && !empty($allArr)) {
            foreach ($allArr as $value) {
                if ($arr == $value[$key]) {
                    return false;
                }
            }
            return true;
        }
    }
}