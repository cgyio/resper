<?php
/**
 * resper 框架 中间件
 * 入站
 * 用户权限过滤
 */

namespace Cgy\middleware\in;

use Cgy\Middleware;
use Cgy\Resper;
use Cgy\Request;
use Cgy\Response;
use Cgy\Log;

class AuthCheck extends Middleware 
{
    /**
     * 异常参数
     */
    //未登录
    protected $notLogin = false;
    //权限拒绝信息
    protected $deniedMsg = "";


    /**
     * !! 必须实现
     * 中间件处理核心方法
     * 根据 入站/出站 类型，中间件自行处理，并将处理结果自行保存到对应的 Request/Response 实例中
     * !! 此方法不需要参数，返回布尔值，当返回 false 时，立即终止响应
     * @return Bool
     */
    public function handle() 
    {
        $uac = $this->resper->uac;          //调用响应者的 权限控制实例
        $method = $this->resper->method;    //请求的响应方法

        if ($uac->enable!==true) {
            //未启用权限控制，直接通过验证
            return true;
        }

        if ($uac->isLogin()!==true) {
            //用户还未登录
            $this->notLogin = true;
            return false;
        }

        //执行权限检查，返回 [ "granted"=>true|false, "opr"=>"操作标识" ]
        $ac = $uac->authCheck($method);
        if ($ac["granted"]!==true) {
            //请求的方法 拒绝访问
            $this->deniedMsg = "用户无访问权限 [OPR=".$ac["opr"]."]";
            return false;
        }

        return true;
    }

    /**
     * 中间件终止响应方法
     * !! 覆盖父类，实现自有的终止响应方法
     * @return void
     */
    public function exit()
    {
        //入站中间件内部 不存在响应实例，创建
        if (empty($this->response)) $this->response = Response::current();

        if ($this->notLogin===true) {
            //用户未登录导致终止，跳转登陆页面
            Response::page("page/login");
            exit;
        }

        //记录日志，自动记录用户信息
        Log::error($this->deniedMsg);

        //输出权限验证错误
        Response::error("custom::".$this->deniedMsg);
        exit;
    }
}