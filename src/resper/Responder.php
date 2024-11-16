<?php
/**
 * cgyio/resper 核心类
 * 
 * Resper == Responder 响应者
 * Resper 框架实质上是一个 路由器，根据输入的 URI 查找对应的响应 类/方法
 * 由 Responder 类派生的子类 都可以作为 会话的响应者
 * 这些子类包括：App 类 / Module 类 
 */

namespace Cgy\resper;

use Cgy\Resper;
use Cgy\resper\Seeker;
use Cgy\Response;

class Responder extends Seeker
{
    /**
     * 响应者实例参数
     */

    //响应类(路由类)信息
    public $name = "";
    public $key = "";
    public $desc = "";

    //此 响应者 是否不受 WEB_PAUSE 设置影响
    public $unpause = false;

    /**
     * 构造
     * @return void
     */
    public function __construct()
    {
        
    }

    /**
     * 默认的 预定义的 响应方法
     */

    /**
     * 未指定响应方法时 使用此方法
     * !! 子类可覆盖
     * @param Array $args 传入的 URI 数组
     * @return Mixed
     */
    public function default(...$args)
    {
        //trigger_error("auth::错误错误", E_USER_ERROR);
        //Response::dump(Response::$current);
        //Response::page("app/index/foo.php");
        
    }


    /**
     * 响应 空 URI
     * !! 子类可覆盖
     * @return Mixed
     */
    public function empty()
    {
        var_export("Empty Respond Class");
        exit;
    }

    /**
     * 解析 URI 最终返回错误
     * !! 子类 不要 覆盖 !!
     * @return Mixed
     */
    public function error()
    {
        var_export("Respond Error");
        exit;
    }

    

    /**
     * __get
     * @param String $key
     */
    public function __get($key)
    {
        /**
         * $responder->foo --> Responder::$params["foo"]
         */
        if (!empty(self::$params)) {
            $ps = self::$params;
            if ($key=="ctx") return $ps;
            if (isset($ps[$key])) return $ps[$key];
        }

        /**
         * $responder->cls --> 返回 Responder::$params["responder"] 的 类名 不是全名
         */
        if ($key == "cls") {
            $cls = Resper::clsname($this);
            return $cls;
        }

        /**
         * $responder->type 获取 当前响应者是 App / Module / Responder
         */
        if (!empty(self::$params) && $key=="type") {
            $responderCls = self::$params["responder"];
            $appcls = Resper::cls("App");
            $modcls = Resper::cls("Module");
            if (is_subclass_of($responderCls, $appcls)) return "App";
            if (is_subclass_of($responderCls, $modcls)) return "Module";
            return "Responder";
        }
        
        return null;
    }

    /**
     * !! Resper 核心方法
     * 响应者创建 执行响应方法
     * @return Response 实例
     */
    public function response()
    {
        //读取 响应者 响应方法参数
        //响应者 == $this
        $responder = $this->responder;  //self::$params["responder"]
        $method = $this->method;        //self::$params["method"]
        $uri = $this->uri;              //self::$params["uri"]

        //检查响应方法
        $result = null;
        if (method_exists($this, $method)) {
            $result = $this->$method(...$uri);
        } else {
            //响应方法不存在，通常不可能
            //抛出错误
            //trigger_error( ... );
        }

        //将 响应结果 写入 response 实例
        $response = Response::current();
        $response->setData($result);

        //返回 response 实例
        return $response;
        
    }



    



    /**
     * static tools
     */

    /**
     * 全局判断 是否存在 resper 响应类
     * 响应类保存在：
     *      [ROOT_PATH]/[DIR_LIB]/.. 
     *      [MODULE_PATH]/[module]/..
     * 必须是此类的 子类
     * @param String $cls 类名
     * @return Mixed 找到则返回 类全称，未找到则返回 false
     */
    public static function has($cls)
    {
        //首先查找 web root 下的 resper 响应类
        $wcls = Resper::cls($cls);
        if (!empty($wcls)) {
            if (is_subclass_of($wcls, self::class)) return $wcls;
        }

        //然后在 module 中查找
        $mcls = [];
        $mdh = @opendir(MODULE_PATH);
        while (($mdn = @readdir($mdh)) !== false) {
            if ($mdn=="." || $mdn=="..") continue;
            if (!is_dir(MODULE_PATH.DS.$mdn)) continue;
            $mcls[] = $mdn."/".$cls;
        }
        @closedir($mdh);
        $mcls = Resper::cls(...$mcls);
        if (!empty($mcls)) {
            if (is_subclass_of($mcls, self::class)) return $mcls;
        }
        
        return false;
    }
}