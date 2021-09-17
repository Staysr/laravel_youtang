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
use OSS\Core\OssException;
use OSS\OssClient;
class OssupdateImg extends Controller
{
    /**
     * Notes:添加文件
     */
    public function uploadOne($data)
    {
        $accessKeyId = $data['accessKeyId'];
        $accessKeySecret = $data['accessKeySecret'];
        $securityToken = $data['SecurityToken'];
        $endpoint = $data['endpoint'];
        $bucket = $data['bucket'];
        $FileType = $data['FileType']; //文件后缀
        $content = $data['path']; //原路径
        $tmpFile_A = $data['tmp']; //文件对象
        if($data['type'] == 1){ // oss 上传路径
            $ossSnd = $data['oss']['images'];
        }else if($data['type'] == 2){
            $ossSnd =  $data['oss']['videos'];
        }else if($data['type'] == 3){
            $ossSnd =  $data['oss']['audios'];
        }else if($data['type'] == 4){
            $ossSnd =  $data['oss']['resources'];
        }
        $object_A =  $ossSnd . md5(time().uniqid()). '.' . $FileType; //上传路径 oss
//        设置回调
        $url =
            '{
        "callbackUrl":"http://dev-oss-aoscdn-com.aoscdn.com/api/callbacks/inputoss",
        "callbackBody":"bucket=${bucket}&object=${object}&etag=${etag}&size=${size}&mime_type=${mimeType}&image_height=${imageInfo.height}&image_width=${imageInfo.width}&image_format=${imageInfo.format}&x:app_id=dev4eb2b-e03a-3d48-b689-8882e697c0mv&x:user_id=27926210&x:action=user_upload",
        "callbackBodyType":"application/x-www-form-urlencoded"
    }';
        $var =
            '{
        "x:var1":"value1",
        "x:var2":"值2"
    }';
        $options = array(OssClient::OSS_CALLBACK => $url,
            OssClient::OSS_CALLBACK_VAR => $var
        );
        try {
            $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint, false, $securityToken);
            $info_A = $ossClient->uploadFile($bucket, $object_A, $content,$options);//为了获取上传后的文件信息
            $this->addresourceall($data, json_decode($info_A['body'], true), $info_A);
            return json_decode($info_A['body'], true);
            // 使用STS临时授权上传文件。
        } catch (OssException $e) {
            print $e->getMessage();
        }
    }
    /*
     * 添加资源库
     * @data
     * */
    public function addresourceall($data, $infodata, $info)
    {
        if ($data == []) return '参数不正常';
        if ($info['info']['http_code'] == 200) {
            $newdata = [];
            $newdata['type'] = $data['type'];
            $newdata['uid'] = $data['uid'];
            $newdata['task_id'] = $data['task_id'];
            $newdata['resource_id'] = $infodata['data']['resource']['resource_id'];
            $newdata['filename'] = $data['FileName'];
            $newdata['path_url'] = $data['path'];
            $newdata['add_time'] = time();
            $newdata['size'] = $infodata['data']['resource']['size'];
            $newdata['code_status'] = json_encode($info);
            DB::table('resourceall')->insertGetId($newdata);
        } else {
            return 'oss异常';
        }

    }
}