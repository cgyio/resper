<?php
/**
 * cgyio/resper 核心类
 * Resper 响应者 默认响应方法
 * Resper 类继承自此类
 */

namespace Cgy;

use Cgy\Resper;
use Cgy\Log;
use Cgy\Orm;
use Cgy\Uac;
use Cgy\Config;
use Cgy\Error;
use Cgy\Request;
use Cgy\Response;
use Cgy\request\Url;
use Cgy\App;
use Cgy\Module;
use Cgy\Event;
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Cls;
use Cgy\util\Path;

class ResperBase
{
    /**
     * 响应者实例参数
     */
    
    /**
     * 响应类(路由类)信息
     * !! 子类必须覆盖
     */
    public $intr = "";  //resper 说明，子类覆盖
    public $name = "";  //resper 名称，子类覆盖
    public $key = "";   //resper 调用路径

    /**
     * 日志处理
     */
    public $log = null;

    /**
     * ORM 数据库操作挂载实例
     * 所有数据库相关的操作 都通过 $resper->orm 实例来执行
     * 对应的预设参数在 对应的 resper 类型(app/module)的预设参数的 orm 项目中指定
     * 例如 app 类型的 resper 响应者，其 orm 参数应设置在：
     *      $app::$config->context["app"][appname]["orm"]
     */
    public $orm = null;

    /**
     * UAC 权限控制操作挂载实例
     * 所有权限控制相关操作 都通过 $resper->uac 实例来执行
     * 对应的预设参数在 对应的 resper 类型(app/module)的预设参数的 uac 项目中指定
     * 例如 app 类型的 resper 响应者，其 uac 参数应设置在：
     *      $app::$config->context["app"][appname]["uac"]
     */
    public $uac = null;

    /**
     * 注册的中间件 入站/出站
     */
    protected $middlewares = [
        "in" => [],
        "out" => [],
    ];

    /**
     * 此 响应者 是否不受 WEB_PAUSE 设置影响
     * !! 子类覆盖
     * == false 默认，WEB_PAUSE==true 时 阻止响应
     * == true 则 WEB_PAUSE==true 时，此响应者依然可以响应 request 并输出结果
     * == [ "method", "method", ... ] 这些指定的响应方法，不受 WEB_PAUSE 控制
     * == [ "__except__", "method", "method", ... ] 除了这些指定的方法外，其他方法不受 WEB_PAUSE 控制
     */
    public $unpause = false;


    /**
     * Resper 响应者实例方法
     */

    /**
     * 构造
     * @return void
     */
    public function __construct()
    {
        /**
         * 根据 resper 响应者预设参数 初始化：
         */
        //日志启动
        $this->log = Log::current($this);
        //实例化 Orm 类
        $this->orm = Orm::current($this);
        //实例化 Uac 类
        $this->uac = Uac::current($this);

        //注册中间件，从 config 中获取需要添加的中间件
        $this->addMiddlewares();

        //自定义初始化动作
        return $this->init();
    }

    /**
     * resper 初始化，在构造方法中执行
     * !! 子类覆盖
     * @return resper $this
     */
    protected function init()
    {
        //初始化动作，在构造后执行，子类覆盖
        //...

        //要返回自身
        return $this;
    }

    /**
     * !! Resper 框架执行响应的 入口方法
     * !! 子类如果要修改，必须返回 Response 实例
     * 在根据 request 请求实例 查询到对应的 响应者后，统一通过此入口，执行响应方法，处理并生成最终 response 响应结果
     * @return Response 实例
     */
    public function response()
    {
        //读取 响应者 响应方法参数
        //响应者 == $this
        $resper = $this->resper;    //Resper::$params["resper"]
        $method = $this->method;    //Resper::$params["method"]
        $uri = $this->uri;          //Resper::$params["uri"]

        /**
         * 执行 Uac 权限验证
         * 验证不通过，将在 Uac 实例内部执行相应的 终止响应操作
         */
        if (Uac::on()===true) {
            $this->uac->verify();
            
            if (Uac::isLogin()===true) {
                //如果 用户已登录，则 验证 当前响应方法的 权限
                
            } else {
                //用户未登录，或 Uac 权限验证 verify 方法未能正确执行，终止响应

            }
        }

        //入站中间件处理
        $this->middlewareProcess("in");

        //创建 Response 实例
        $response = Response::current();

        /**
         * 暂停响应
         */
        if ($response->paused) {
            $response->setFormat("pause");
            //直接输出
            $response->export();
            exit;
        }

        //检查 Resper::$config 是否包含了 response 额外参数
        $conf = $this->conf;
        $resps = $conf["response"] ?? [];
        if (!empty($resps)) {
            $response->setParams($resps);
        }

        //执行响应方法
        $result = null;
        if (method_exists($this, $method)) {
            $result = $this->$method(...$uri);
        } else {
            //响应方法不存在，通常不可能
            //抛出错误
            //trigger_error( ... );

        }

        //将 响应结果 写入 response 实例
        $response->setData($result);

        //出站中间件处理
        $this->middlewareProcess("out");

        //返回 response 实例
        return $response;
        
    }



    /**
     * 响应者实例 工具方法
     */

    /**
     * resper 类 内部 文件/路径 查找
     * !! 子类可覆盖
     * @param String $path 文件/路径
     * @param Mixed $params 
     *      Array   Path::find() 方法的 第二参数
     *      Bool    如果传入 false，则不查找真实文件
     * @return String 完整的 文件/路径 !! 可能不存在
     */
    public function path($path = "", $params = null)
    {
        /**
         * 处理传入的 params 参数
         * 传入 Path::find() 第二参数
         */
        $dfp = ["inDir" => DIR_ASSET];
        if (Is::nemarr($params) && Is::associate($params)) {
            $params = Arr::extend($dfp, $params);
        } else if ($params!==false) {
            $params = $dfp;
        } else {
            $params = false;
        }

        /**
         * 如果 $path 已存在
         */
        if (file_exists($path)) return $path;
        if (is_dir($path)) {
            if (!Is::nemarr($params)) {
                return $path;
            } else {
                if (isset($params["checkDir"]) && $params["checkDir"]==true) {
                    return $path;
                }
            }
            return null;
        }

        //resper 类型：App / Module / Resper
        $rtp = $this->type;
        //resper 类全称
        $cls = $this->resper;
        //resper 类名，不是全名，foo\bar\Tom --> Tom
        $name = $this->cls;
        //路径前缀
        $pre = $this->path;

        
        if ($params===false) {
            //直接输出 真实的 resper 所在路径
            if ($rtp == "App") {
                $pre = APP_PATH.DS.strtolower($name);
            } else {
                $pr = Path::find($pre, ["checkDir"=>true]);
                $pre = empty($pr) ? ROOT_PATH.DS.strtolower($name) : $pr;
            }
            if ($path=="") return $pre;
            return $pre.DS.str_replace("/", DS, trim($path,"/"));
        } else {
            //查找真是 文件/路径
            $full = $pre.($path=="" ? "" : "/".trim($path, "/"));
            return Path::find($full, $params);
        }
    }

    /**
     * 获取 resper 内部 类全称
     * @param String $cls 类名 或 部分类名
     * @return String 类全称 或 null
     */
    final public function cls($cls)
    {
        $clp = $this->path;
        $cln = $clp."/".trim($cls, "/");
        return Cls::find($cln);
    }

    /**
     * 注册 resper 响应者类的中间件
     * @param String $inout 入站/出站
     * @param String $middlware 中间件类全称
     * @return Bool
     */
    final public function addMiddlewares($inout=null, ...$middleware)
    {
        if (!Is::nemstr($inout) && !Is::nemarr($middleware)) {
            //未指定要注册的中间件，默认从 config 中获取预设的 中间类名
            $mids = $this->conf["middleware"] ?? [];
            $rtn = true;
            foreach ($mids as $io => $midcls) {
                if (!in_array($io, ["in","out"])) continue;
                if (!Is::nemarr($midcls) || !Is::indexed($midcls)) continue;
                $rtn = $rtn && $this->addMiddlewares($io, ...$midcls);
            }
            return $rtn;
        }

        if (!in_array($inout, ["in","out"]) || empty($middleware)) {
            //参数错误
            return false;
        }

        //中间件写入 $this->middlewares
        $mids = $this->middlewares[$inout] ?? [];
        foreach ($middleware as $i => $midi) {
            if (in_array($midi, $mids)) continue;
            $this->middlewares[$inout][] = $midi;
        }
        return true;
    }

    /**
     * 执行中间件处理
     * @param String $inout 入站/出站
     * @return $this
     */
    final public function middlewareProcess($inout)
    {
        if (!in_array($inout, ["in","out"])) return $this;
        $mids = $this->middlewares[$inout] ?? [];
        if (!Is::nemarr($mids)) return $this;
        
        //按顺序调用中间件
        for ($i=0;$i<count($mids);$i++) {
            $midi = $mids[$i];
            if (!class_exists($midi)) continue;
            //实例化，注入依赖的 resper 实例
            //其他依赖项（Request/Response）在中间件内部通过 Request::$current/Response::$current 自行获取
            $midIns = new $midi($this);
            /**
             * 调用 handle 方法，处理结果由中间件自行保存到 Request/Response 实例中
             * !! 返回布尔值，当返回 false 时，立即终止响应
             */
            $hdlResult = $midIns->handle();
            if ($hdlResult===false) {
                //立即终止响应，通过调用中间件的 exit 方法
                return $midIns->exit();
            }
            //释放资源
            $midIns = null;
        }

        return $this;
    }

    /**
     * __get
     * @param String $key
     */
    public function __get($key)
    {
        if (!empty(Resper::$params) && Is::associate(Resper::$params)) {
            $ps = Resper::$params;
            $cls = $ps["resper"];

            switch ($key) {
                //$this->ctx 返回 resper::$params 数组
                case "ctx": return $ps; break;
                //$this->cls 返回当前 resper 的 类名 不是全名
                case "cls": return Cls::name($this); break;
                
                /**
                 * $this->path 获取 响应者类 所在路径前缀
                 * 通常用于查找 响应者路径下文件
                 */
                case "path":
                    $clp = str_replace(NS, "", $cls);
                    $clp = str_replace("\\", "/", $clp);
                    $clp = strtolower($clp);
                    if ($this->type == "Resper") {
                        //响应者是 Resper 类
                        $cla = explode("/", $clp);
                        if (count($cla)>2 && in_array(strtolower($cla[0]), ["app", "module"])) {
                            //定义在 app / module 路径下的 Resper 类
                            $clp = implode("/", array_slice($cla, 0,2));
                        }
                    }
                    return $clp;
                    break;
                
                /**
                 * $this->type 获取 当前响应者类型，可能是：
                 * App / Module / Resper
                 */
                case "type":
                    $appcls = Cls::find("App");
                    $modcls = Cls::find("Module");
                    if (is_subclass_of($cls, $appcls)) return "App";
                    if (is_subclass_of($cls, $modcls)) return "Module";
                    return "Resper";
                    break;

                /**
                 * $this->conf 获取 当前响应者的 预设参数
                 * 通过 Resper::start([...]) 修改的参数
                 */
                case "conf":
                    //$rtp = $this->type;
                    $xpt = $this->path;
                    $conf = Resper::$config;
                    return $conf->ctx(strtolower($xpt));
                    break;
                /**
                 * $this->configer 获取 Resper::$config 实例
                 */
                case "configer":
                    return Resper::$config;
                    break;

                /**
                 * $this->foo 读取 Resper::$params["foo"]
                 */
                default:
                    if (isset($ps[$key])) return $ps[$key];
                    break;
            }
        }
    
        return null;
    }

    /**
     * 根据 WEB_PAUSE 检查 当前的目标响应方法 是否被终止
     * @return Bool
     */
    public function responsePaused()
    {
        $webPause = $this::$request->pause;
        if (!$webPause) return false;
        $unpause = $this->unpause;
        if (is_bool($unpause)) return !$unpause;
        if (is_array($unpause)) {
            if (empty($unpause)) return true;
            //第一项为 __except__ 则只有指定的方法被 pause
            $except = $unpause[0] === "__except__";
            $ps = $this->ctx;
            $m = $ps["method"] ?? null;
            $inups = in_array($m, $unpause);
            return $except ? $inups : !$inups;
        }
        return true;
    }



    /**
     * Uac 权限控制相关方法
     * !! 在此处定义这些方法，而不是在 Uac 类中定义，是为了支持 不同的 resper 响应者类 可以自定义这些方法
     * 这些方法，大部分需要创建特殊相应实例，然后终止当前响应，因此
     * !! 这些方法必须在 Response 响应实例创建之前 执行
     */
    
    /**
     * 当启用 UAC 但是用户还未登录 或 登录已过期 时，执行此方法，跳转到 登陆界面
     * !! 子类可覆盖此方法，在不同情况下，需要使用不同的登陆跳转方法
     * 
     * @param Array $vali 执行 token 验证后，得到的返回数据，通常包含验证状态 以及 错误信息
     * @return Mixed
     */
    public function responseLogin($vali=[])
    {
        //如果未启用 Uac 直接返回
        if (Uac::on()!==true) return true;

        $request = Request::$current;
        //创建 Response 实例
        $response = empty(Response::$current) ? Response::current() : Response::$current;
        //收集当前请求的数据
        $origin = [
            "url" => Url::current()->full,      //当前请求的 url 完整地址，包含 $_GET 数据
            "post" => $request->inputs->json,   //post 到当前请求的 json 数据
        ];

        /**
         * 此处定义 默认的 跳转登陆 方法
         */
        // 1    根据 Response 实例来确定输出格式 html/json/str...
        $format = $response->format;

        if ($format=="json") {
            /**
             * 2    如果输出格式为 json，大部分 BS 应用都是这种情况
             *      返回规定格式数据给前端，由 前端框架 进行跳转登陆界面操作
             */
            Response::json([
                //要跳转登陆界面的标记
                "needLogin" => true,
                //将 当前请求的数据 作为原始请求数据，在登陆成功后，将跳转回这个请求地址
                "origin" => $origin,
            ]);
            exit;
        } else {
            /**
             * 3    如果输出格式为 html/str/...
             *      跳转到框架规定的 登陆页面，可在 $this->conf["uac"]["login"] 参数中自定义位置
             */
            $lp = $this->conf["uac"]["login"] ?? "root/page/login.php";
            $lp = Path::find($lp);
            if (!file_exists($lp)) {
                //自定义的登陆页面不存在，则使用框架默认的页面，此页面肯定存在
                $lp = Path::find("resper/uac/login.php");
            }
            Response::page($lp, [
                //将 当前请求的数据 作为原始请求数据，在登陆成功后，将跳转回这个请求地址
                "origin" => $origin,
            ]);
            exit;
        }
    }

    /**
     * 当启用 UAC 但是前端传回的 token 不正确时，说明 token 可能被篡改
     * 记录 日志
     * 直接报错
     * !! 子类可覆盖此方法，执行自定义的 token 被篡改时的操作
     * 
     * @param Array $vali 执行 token 验证后，得到的返回数据，通常包含验证状态 以及 错误信息
     * @return Mixed
     */
    public function responseTokenError($vali=[])
    {
        //如果未启用 Uac 直接返回
        if (Uac::on()!==true) return true;

        //记录日志，在 Log 类中，自动记录 请求来源等数据
        $msg = $vali["msg"] ?? "";
        $msg = $msg=="" ? "" : $msg."，";
        Log::emergency($msg."Token 可能被篡改", $vali);

        //创建 Response 实例
        $response = empty(Response::$current) ? Response::current() : Response::$current;
        Response::error("权限验证失败");
        exit;
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
        trigger_error("custom::error test", E_USER_ERROR);
        exit;
        //默认方法 返回未找到 Resper 实例的预设页面
        $pg = RESPER_PATH.DS."page".DS."not-found.php";
        if (file_exists($pg)) {
            Response::page($pg, [
                "resper" => $this
            ]);
        }
        Response::code(404);
    }

    /**
     * 响应 空 URI
     * !! 子类可覆盖
     * @return Mixed
     */
    public function empty()
    {
        //URI 为空时执行
        if ($this->type == "App") {
            //如果响应者是 App 则查找路径下 page/index.php
            $pg = $this->path("page/index.php");
            if (file_exists($pg)) {
                Response::page($pg, [
                    "resper" => $this
                ]);
            }
        }

        Response::code(404);
    }

    /**
     * 响应 Uac 权限验证相关操作 请求
     * !! 子类 不要 覆盖
     * @return Mixed
     */
    final public function uac(...$args)
    {
        //如果未启用 UAC 直接返回
        if (Uac::on()!==true) return true;

        /**
         * 调用 Uac 实例的 response 方法，代理处理这些请求
         */
        return $this->uac->response(...$args);
    }

    /**
     * 响应 Orm 数据库操作 请求
     * !! 子类 不要 覆盖 !!
     * @return Mixed
     */
    final public function db(...$args)
    {
        if (empty($this->orm)) {
            trigger_error("orm::ORM 对象未初始化", E_USER_ERROR);
            return null;
        }

        //调用 Orm 实例的 response 方法，响应此请求
        return $this->orm->response(...$args);
    }

    /**
     * 解析 URI 最终返回错误
     * !! 子类 不要 覆盖 !!
     * @return Mixed
     */
    final public function error()
    {
        //TODO: 根据 $response->format 输出格式，来决定：显示错误页面，或 返回带错误信息的 json
        //...

        Response::error("Resper Error!");
    }


    
}