<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    private $data  = [];//不验证参数名
    protected function redirectTo($request)
    {
        $newdata = $request->all();
        foreach ($newdata as $v){
            if ($v == ''){
                return '参数错误,参数不能为空';
            }
        }
    }
}
