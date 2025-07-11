<?php
/**
 * cgyio/resper 核心类
 * Resper 类 请求响应者类 (相当于 route 类)
 * 
 * Resper == Responder 响应者
 * cgyio/resper 框架本质上是一个路由器，一个完整的 Request / Response 请求/响应 流程包括：
 *  1   用户输入 URI，框架根据此输入创建 Request 请求实例，
 *  2   解析并查找对应的 Resper 响应者类，实例化响应者，
 *  3   由 Resper 响应者实例，创建 Response 响应实例，
 *  4   Response 响应实例调用 export 方法，最终输出响应结果
 * 
 * 任何由 Resper 响应者类 派生的子类，都可以作为 响应者，响应 Request 请求，这些类包括：
 *  App 应用类 / Module 模块类 
 */

namespace Cgy;

use Cgy\ResperBase;
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

class Resper extends ResperBase
{
    /**
     * 本次会话的核心类实例
     */
    //Request 请求实例
    public static $request = null;
    //Resper 响应者实例
    public static $resper = null;
    //Response 响应实例
    public static $response = null;

    //设置类实例
    public static $config = null;

    //本次会话调用的 响应者参数
    public static $params = [
        /*
        "resper"    => 响应者 类全称
        "method"    => 响应方法 实例 public 方法
        "uri"       => 本次响应的 URI 参数数组 即 URI 路径
        */
    ];

    /**
     * 框架入口方法
     * 在 webroot/index.php 中执行 Resper::start([ ... ])
     * @param Array $opt 框架启动参数
     * @return Exit
     */
    public static function start($opt = [])
    {
        //var_dump(0);
        //只能调用一次
        if (Resper::isStarted() === true) exit;
        //var_dump(111);

        /**
         * 框架启动
         */
        @ob_start();
        @session_start();

        //应用启动参数，定义常量
        Resper::$config = new Config($opt);
        //var_dump(222);

        //patch composer autoload
        Resper::patchAutoload();

        //初始化框架参数
        Resper::$config->initConf();
        //var_dump(333);

        //应用 errorHandler 自定义错误处理
        Error::regist();

        /**
         * 开始 request / response 请求与响应 流程
         */
        
        //创建 Request 请求实例
        Resper::$request = Request::current();
        //var_dump(444);
        
        //查找并创建响应者 Resper 实例
        Resper::current();
        //var_dump(555);
        //var_dump(Resper::$params);
        //var_dump(Resper::$resper);

        //通过 Resper::$resper 当前响应者创建 Response 响应实例
        Resper::$response = Resper::$resper->response();
        //var_dump(666);

        //Response 调用输出类 输出结果
        Resper::$response->export();

        exit;
    }

    /**
     * 检查 Resper 框架是否已经启动
     * Resper::start() 方法只能执行一次，因此在执行前要判断 框架是否已经启动
     * @return Bool
     */
    private static function isStarted()
    {
        return !is_null(Resper::$request) && !is_null(Resper::$resper) && !is_null(Resper::$response);
    }

    /**
     * patch composer autoload
     * 必须在 系统路径/web 路径 常量定义之后 执行
     * @return void
     */
    protected static function patchAutoload()
    {
        $ns = trim(NS, "\\");
        $af = VENDOR_PATH . DS . "autoload.php";
        if (file_exists($af)) {
            $al = file_get_contents($af);
            $alc = "ComposerAutoloader" . explode("::getLoader", explode("return ComposerAutoloader", $al)[1])[0];
            $alo = $alc::getLoader();
            if (!empty($alo)) {

                /**
                 * patch app classes autoload
                 */
                if (is_dir(APP_PATH)) {
                    $apps_dh = opendir(APP_PATH);
                    $psr_app = [APP_PATH];
                    while (($app = readdir($apps_dh)) !== false) {
                        if ($app == "." || $app == "..") continue;
                        $app_dir = APP_PATH . DS . $app;
                        $uap = ucfirst(strtolower($app));
                        if (is_dir($app_dir)) {

                            // app class dir
                            $psr_app[] = $app_dir;

                            //lib class dir
                            $psr_ds = array_map(function($i) use ($app_dir) {
                                return $app_dir.DS.str_replace("/",DS,trim($i));
                            }, DIR_LIB);
                            $psr_ds = array_merge($psr_ds, [$app_dir]);
                            $alo->addPsr4($ns.'\\App\\'.$app.'\\', $psr_ds);
                            $alo->addPsr4($ns.'\\App\\'.$uap.'\\', $psr_ds);
                            $alo->addPsr4($ns.'\\app\\'.$app.'\\', $psr_ds);
                            $alo->addPsr4($ns.'\\app\\'.$uap.'\\', $psr_ds);

                            //model class dir
                            $psr_ds = array_map(function($i) use ($app_dir) {
                                return $app_dir.DS.str_replace("/",DS,trim($i));
                            }, DIR_MODEL);
                            $psr_ds = array_merge($psr_ds, [$app_dir]);
                            $alo->addPsr4($ns.'\\App\\'.$app.'\\model\\', $psr_ds);
                            $alo->addPsr4($ns.'\\App\\'.$uap.'\\model\\', $psr_ds);
                            $alo->addPsr4($ns.'\\app\\'.$app.'\\model\\', $psr_ds);
                            $alo->addPsr4($ns.'\\app\\'.$uap.'\\model\\', $psr_ds);
                            
                            //error class
                            $psr_ds = [
                                $app_dir.DS.'error'
                            ];
                            $alo->addPsr4($ns.'\\error\\'.$app.'\\', $psr_ds);
                            $alo->addPsr4($ns.'\\error\\'.$uap.'\\', $psr_ds);
                    
                        }
                    }
                    $alo->addPsr4($ns.'\\App\\', $psr_app);
                    $alo->addPsr4($ns.'\\app\\', $psr_app);
                    closedir($apps_dh);
                }

                /**
                 * patch module classes autoload
                 */
                $mdp = MODULE_PATH;
                if (is_dir($mdp)) {
                    $dh = opendir($mdp);
                    while (($md = readdir($dh)) !== false) {
                        if ($md == "." || $md == "..") continue;
                        $md_dir = $mdp . DS . $md;
                        if (is_dir($md_dir)) {
                            //route class
                            //$alo->addPsr4($ns.'\\route\\', $md_dir.DS."route");
                            //error class
                            $alo->addPsr4($ns.'\\error\\', $md_dir.DS."error");
                        }
                    }
                    closedir($dh);
                }

                /**
                 * patch web class autoload
                 */
                //$alo->addPsr4($ns.'\\Web\\route\\', ROOT_PATH.DS."route");
                $alo->addPsr4($ns.'\\error\\', ROOT_PATH.DS."error");

                /**
                 * patch web lib/model class
                 */
                $lib_ds = array_map(function($i) {
                    return ROOT_PATH.DS.str_replace("/",DS,trim($i));
                }, DIR_LIB);
                $model_ds = array_map(function($i) {
                    return ROOT_PATH.DS.str_replace("/",DS,trim($i));
                }, DIR_MODEL);
                $alo->addPsr4($ns.'\\', $lib_ds);
                $alo->addPsr4($ns.'\\model\\', $model_ds);

            }
        }
    }



    /**
     * 查找响应者类 并创建 Resper 响应者实例
     */

    /**
     * 查找当前会话的 响应者 并创建 Resper 响应者实例
     * @return Resper 实例
     */
    public static function current()
    {
        $scls = Resper::class;
        if (Resper::$resper instanceof $scls) return Resper::$resper;

        $params = Resper::seek();
        //var_dump($params);
        Resper::$params = $params;
        $resperCls = $params["resper"];
        $resper = new $resperCls();

        //缓存 resper
        Resper::$resper = $resper;

        /**
         * 触发 resper-created 事件
         */
        $rtype = $resper->type;
        Event::trigger("resper-created", $resper, $rtype);

        return $resper;
    }

    /**
     * 根据 Request::$current->url->path 查询响应此 request 的 类/方法
     * 可以响应的类：App类 / module类 / Responder类(route类)
     * @param Array $uri URI 路径，不指定则使用 url->path
     * @return Array 得到的目标 类，方法，uri参数，未找到 返回 null
     * 结构：[
     *          "resper"    => 类全称 or 类实例
     *          "method"    => 响应方法，类实例的 public 方法
     *          "uri"       => 处理后，用作方法参数的 剩余 URI 路径数组
     *      ]
     */
    public static function seek($uri = [])
    {
        if (Is::nemstr($uri)) {
            //$uri = foo/bar/jaz 形式
            $uri = Arr::mk($uri);
        }
        if (!Is::nemarr($uri)) {
            $uri = Url::current()->path;
        }

        /**
         * 默认的 响应类 / 方法
         * 如果存在 app/index 则调用 \App\Index::empty() 
         * 否则调用 Resper::empty() 
         */
        $resperCls = Cls::find("resper");
        $resper = $resperCls;
        $method = "empty";

        /**
         *  0  URI 为空，返回默认响应
         */
        if (empty($uri)) {
            if (false !== ($appcls = App::has("index"))) {
                //如果存在 app/index 则调用 \App\Index::empty()
                $resper = $appcls;
            }
            return [
                "resper"    => $resper,
                "method"    => $method,
                "uri"       => []
            ];
        }

        /**
         *  1  判断是否存在 app / module 类
         */
        $cls = App::has($uri[0]);
        $ma = [];
        if ($cls !== false) {
            $resper = $cls;
            $ma = Resper::seekMethod($cls, array_slice($uri, 1));
        } else {
            $cls = Module::has($uri[0]);
            if ($cls !== false) {
                $resper = $cls;
                $ma = Resper::seekMethod($cls, array_slice($uri, 1));
            }
        }
        if (!empty($ma)) {
            return [
                "resper"    => $resper,
                "method"    => $ma[0],
                "uri"       => $ma[1]
            ];
        }

        /**
         *  2  判断是否 Resper 类 (相当于 route 类)
         */
        $rpd = Resper::has($uri[0]);
        if (false !== $rpd) {
            $resper = $rpd;
            $ma = Resper::seekMethod($rpd, array_slice($uri, 1));
            if (!empty($ma)) {
                return [
                    "resper"    => $resper,
                    "method"    => $ma[0],
                    "uri"       => $ma[1]
                ];
            }
        }

        /**
         *  3  判断是否 app/index 类中某个 public 方法
         */
        $app = App::has("index");
        if (false !== $app) {
            $resper = $app;
            $ma = Resper::seekMethod($app, $uri);
            if (!empty($ma)) {
                return [
                    "resper"    => $resper,
                    "method"    => $ma[0],
                    "uri"       => $ma[1]
                ];
            }
        }

        /**
         *  4  判断是否 Resper 基类中的 某个 public 方法
         */
        $ma = Resper::seekMethod($rpd, $uri);
        if (!empty($ma)) {
            return [
                "resper"    => $resperCls,
                "method"    => $ma[0],
                "uri"       => $ma[1]
            ];
        }

        /**
         *  5  全部失败，调用 Resper::error()
         */
        return [
            "resper"    => $resperCls,
            "method"    => "error",
            "uri"       => $uri
        ];
        
    }

    /**
     * 根据传入的 $uri 数组，在 $cls 类中 查找目标方法
     * @param String $cls 类全称
     * @param Array $uri 参数数组
     * @return Mixed 找到目标方法，返回 [ method name, [参数数组] ]，未找到则返回 null
     */
    public static function seekMethod($cls, $uri = [])
    {
        //如果 $cls 不是 Resper 子类，返回 null
        if (!is_subclass_of($cls, Cls::find("resper"))) return null;

        //空 uri
        if (!Is::nemarr($uri) || !Is::indexed($uri)) {
            return ["empty", []];
        }

        //查找 响应方法
        $m = $uri[0];
        /**
         * !! 排除一些固定的 resper 实例方法，这些方法不能作为响应方法
         */
        $except = [
            "init", "path", "cls", 
            "addMiddlewares", "middlewareProcess",
            "response", "responsePaused", "responseLogin", "responseTokenError",
            "__construct"
        ];
        if (!in_array($m, $except)) {
            //响应方法必须是 实例方法/public方法
            $has = Cls::hasMethod($cls, $m, "public", function($mi) {
                return $mi->isStatic() === false;
            });
            if ($has) return [ $m, array_slice($uri, 1) ];
        }

        //未找到有效的 响应方法 则返回默认方法 default
        return [ "default", $uri ];
    }

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
        //首先查找 web root 下的 responder 响应类
        $wcls = Cls::find($cls);
        if (!empty($wcls)) {
            if (is_subclass_of($wcls, Resper::class)) return $wcls;
        }

        //然后在 module 中查找
        $mcls = [];
        $mdh = @opendir(MODULE_PATH);
        while (($mdn = @readdir($mdh)) !== false) {
            if ($mdn=="." || $mdn=="..") continue;
            if (!is_dir(MODULE_PATH.DS.$mdn)) continue;
            $mcls[] = "module/".$mdn."/".$cls;
        }
        @closedir($mdh);
        $mcls = Cls::find($mcls);
        if (!empty($mcls)) {
            if (is_subclass_of($mcls, Resper::class)) return $mcls;
        }
        
        return false;
    }

}