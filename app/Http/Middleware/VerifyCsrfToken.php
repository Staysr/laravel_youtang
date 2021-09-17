<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        //
        'videoadd/installTask',
        'videoinfo/TaskInfo',
        'videoup/TaskUpdate',
        'videoengineering/Task_engineering',
        'videodelete/TaskDelete',
        'videonfolist/Taskinfolist',
        'videoncopy/Taskcopy',
        'videoncancel/Taskcancel',
        'videoncancelrendering/Taskcancelrendering',
        'videonshare/Taskshare',
        'videonrendering/TaskRendering',
        'videontemplatelist/Tasktemplatelist',
        'videontemplateclass/Tasktemplateclass',
        'videoengineering/Task_engineeringTwo',
        'videonaddimgoss/TaskAddImgOss',
        'videomaterialinfo/Taskmaterialinfo',
        'videoresourcedetails/TaskResourceDetails',
        'videoresourcelist/TaskResourceList'
    ];
}
