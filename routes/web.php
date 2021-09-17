<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for yTask_engineeringour application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['middleware' => ['web']], function () {
    Route::post('videoadd/installTask', 'VideoController@installTask'); //创建视频任务
    Route::get('videoinfo/TaskInfo', 'VideoController@TaskInfo'); //获取任务详情
    Route::post('videoup/TaskUpdate', 'VideoController@TaskUpdate'); //任务信息修改
    Route::post('videoengineering/Task_engineering', 'VideoController@Task_engineering'); //任务工程文件修改
    Route::post('videoengineering/Task_engineeringTwo', 'VideoController@Task_engineeringTwo'); //任务工程文件修改副本
    Route::delete('videodelete/TaskDelete', 'VideoController@TaskDelete'); //任务工程删除
    Route::get('videonfolist/Taskinfolist', 'VideoController@Taskinfolist'); //我的任务列表
    Route::post('videoncopy/Taskcopy', 'VideoController@Taskcopy'); //复制任务
    Route::post('videoncancel/Taskcancel', 'VideoController@Taskcancel'); //取消物理删除
    Route::get('videoncancelrendering/Taskcancelrendering', 'VideoController@Taskcancelrendering'); //取消渲染
    Route::get('videonshare/Taskshare', 'VideoController@Taskshare'); //任务预览 & 分享
    Route::get('videonrendering/TaskRendering', 'VideoController@TaskRendering'); //获取渲染进度
    Route::get('videontemplatelist/Tasktemplatelist', 'VideoController@Tasktemplatelist'); //获取模板列表
    Route::get('videonTopic/TaskTopic', 'VideoController@TaskTopic'); //获取专题列表
    Route::get('videontemplateclass/Tasktemplateclass', 'VideoController@Tasktemplateclass'); //获取模板列表
    Route::post('videonaddimgoss/TaskAddImgOss', 'VideoController@TaskAddImgOss'); //上传图片
    Route::post('videomaterialinfo/Taskmaterialinfo', 'VideoController@Taskmaterialinfo'); //获取素材库信息
    Route::get('videoresourcedetails/TaskResourceDetails', 'VideoController@TaskResourceDetails'); //获取获取资源详情地址（单个 & 批量）

    Route::get('videoresourcelist/TaskResourceList', 'VideoController@TaskResourceList'); //获取资源列表


});