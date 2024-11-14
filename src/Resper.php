<?php
/**
 * cgyio/resper framework core
 * Resper 核心类
 */

namespace Cgy;

//使用 composer autoload
require_once(__DIR__."/../../../autoload.php");

use Cgy\util\Path;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Is;
use Cgy\config\Resper as ResperConfiger;
use Cgy\Error;
use Cgy\Request;
use Cgy\request\Url;
use Cgy\Response;
use Cgy\response\Respond;

class Resper 
{
    /**
     * 固定的 常量
     */
    protected static $defines = [
        "version" => "0.0.1",       //版本升级时修改
        "ds" => DIRECTORY_SEPARATOR,
        "ns" => "\\Cgy\\",
        "ext" => ".php",
    ];

    /**
     * config
     * 处理后的 运行时 config
     */
    public static $config = null;
    //定义的 constant
    public static $cnsts = [];

    /**
     * 核心类实例缓存
     * 按创建的先后顺序
     */
    //请求实例
    public static $request = null;
    //响应者实例
    public static $resper = null;
    //响应实例
    public static $response = null;
    //解析得到的 respond 响应类
    //public static $respond = null;
    //如果响应类是 app 则缓存 app 实例
    //public static $app = null;

    

    /**
     * 框架启动
     * @param Array $opt 用户自定义参数
     * @return void;
     */
    public static function start($opt=[])
    {
        @ob_start();
        @session_start();

        //定义固定常量 以及 系统路径常量
        self::defStatic();

        //应用 用户设置
        self::$config = new ResperConfiger($opt);

        //patch composer autoload
        self::patchAutoload();

        /*var_export(self::$config->ctx());
        var_export(self::$cnsts);
        var_export(self::$config->app->index);
        var_export(WEB_ALI_SSLCHECK);
        var_export(DIR_MODEL);
        exit;*/

        //应用 errorHandler
        Error::setHandler();

        //调用 框架启动方法，开始 request/response 处理
        self::run();
    }

    /**
     * 执行 request/response 流程
     * @return exit
     */
    private static function run()
    {
        //请求/响应 流程开始
        //创建 Request 请求实例
        self::$request = Request::current();
        //解析响应类
        //self::$respond = new Seeker();

        var_export(self::$respond);
        exit;
    }



    /**
     * config 与 初始化
     */

    /**
     * 定义固定常量
     * @return Array
     */
    protected static function defStatic()
    {
        //定义 固定常量
        self::def(self::$defines);
        //定义 系统路径常量
        $pre = Path::fix(__DIR__.DS."..".DS."..".DS."..".DS."..");
        $vdp = $pre.DS."vendor";
        $cgp = $vdp.DS."cgyio";
        $rep = $cgp.DS."resper".DS."src";
        $mdp = $rep.DS."modules";
        $path = [
            "pre_path" => $pre,
            "vendor_path" => $vdp,
            "cgy_path" => $cgp,
            "resper_path" => $rep,
            "module_path" => $mdp
        ];
        self::def($path);
        //路径常量合并到 $defines
        self::$defines = Arr::extend(self::$defines, $path);
        return self::$defines;
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
                            $alo->addPsr4($ns.'\\route\\', $md_dir.DS."route");
                            //error class
                            $alo->addPsr4($ns.'\\error\\', $md_dir.DS."error");
                        }
                    }
                    closedir($dh);
                }

                /**
                 * patch web class autoload
                 */
                $alo->addPsr4($ns.'\\route\\', ROOT_PATH.DS."route");
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
     * 生成 runtime 缓存
     * @return Array
     */
    protected static function cacheConf()
    {
        $conf = self::$_CONF;
        $cache = Path::mk("cache/runtime".EXT);
        //输出 conf
        var_export($conf);
        //从缓冲区读取 conf
        $conf_str = ob_get_contents();
        //清空缓冲区
        ob_clean();
        $conf_str = str_replace("array (","[", $conf_str);
        $conf_str = str_replace(")","]", $conf_str);
        $conf_str = str_replace("'","\"", $conf_str);
        $conf_str = "return ".$conf_str.";";
        $ch = @fopen($cache, "w");
        fwrite($ch, $conf_str);
        fclose($ch);
        return $conf;
    }



    /**
     * !!!! Resper 核心方法 !!!!
     * 
     * Resper 名称由此而来：Resper == Responder 响应者
     * Resper 框架实质上是一个 路由器，根据输入的 URI 查找对应的响应 类/方法
     * 并围绕此功能，建立了其他核心功能类：Request 请求类 / Response 响应类
     */

    /**
     * 根据 URI 查询响应 本次会话 的 类/方法
     * 可以响应的类：App类 / module类 / Respond类(route类)
     * @param Array $uri URI 路径，不指定则使用 url->path
     * @return Array 得到的目标 类，方法，uri参数，未找到 返回 null
     * 结构：[
     *          "response"  => 类全称 or 类实例
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
         * 否则调用 \response\Respond::empty() 
         * 
         */
        $response = Resper::cls("response/Respond");
        $method = "empty";

        /**
         * URI 为空，返回默认响应
         */
        if (empty($uri)) {
            if (false !== ($appcls = App::has("index"))) {
                //如果存在 app/index 则调用 \App\Index::empty()
                $response = $appcls;
            }
            return [
                "response"  => $response,
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
            $response = $cls;
            $ma = self::seekMethod($cls, array_slice($uri, 1));
        } else {
            $cls = Module::has($uri[0]);
            if ($cls !== false) {
                $response = $cls;
                $ma = self::seekMethod($cls, array_slice($uri, 1));
            }
        }
        if (!empty($ma)) {
            return [
                "response"  => $response,
                "method"    => $ma[0],
                "uri"       => $ma[1]
            ];
        }

        /**
         *  2  判断是否 response\Respond 类 (相当于 route 类)
         */
        $rpd = Respond::has($uri[0]);
        if (false !== $rpd) {
            $response = $rpd;
            $ma = self::seekMethod($rpd, array_slice($uri, 1));
            if (!empty($ma)) {
                return [
                    "response"  => $response,
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
            $response = $app;
            $ma = self::seekMethod($app, $uri);
            if (!empty($ma)) {
                return [
                    "response"  => $response,
                    "method"    => $ma[0],
                    "uri"       => $ma[1]
                ];
            }
        }

        /**
         *  4  判断是否 response/Respond 基类中的 某个 public 方法
         */
        $rpd = Resper::cls("response/Respond");
        $ma = self::seekMethod($rpd, $uri);
        if (!empty($ma)) {
            return [
                "response"  => $rpd,
                "method"    => $ma[0],
                "uri"       => $ma[1]
            ];
        }

        /**
         *  5  全部失败，调用 response/Respond::error()
         */
        return [
            "response"  => Resper::cls("response/Respond"),
            "method"    => "error",
            "uri"       => $uri
        ];
        
    }

    /**
     * !! 核心方法
     * 根据传入的 $uri 数组，在 $cls 类中 查找目标方法
     * @param String $cls 类全称
     * @param Array $uri 参数数组
     * @return Mixed 找到目标方法，返回 [ method name, [参数数组] ]，未找到则返回 null
     */
    public static function seekMethod($cls, $uri = [])
    {
        //如果 $cls 不是 Respond 子类，返回 null
        if (!is_subclass_of($cls, Resper::cls("response/Respond"))) return null;
        //空 uri
        if (!Is::nemarr($uri) || !Is::indexed($uri)) {
            return ["empty", []];
        }
        //查找 响应方法
        $m = $uri[0];
        //响应方法必须是 实例方法/public方法
        $has = Cls::hasMethod($cls, $m, "public", function($mi) {
            return $mi->isStatic() === false;
        });
        if ($has) {
            return [ $m, array_slice($uri, 1) ];
        } else {
            return [ "default", $uri ];
        }
    }



    /**
     * 调用过滤器
     * 对 Request / Response 结果执行 预定义的过滤方法
     * 输入 Request/Response 实例，返回经过处理的 实例  or  抛出错误
     * @param Mixed $obj Request/Response 实例
     * @return Mixed 经过处理的 实例  or  抛出错误
     */
    protected static function filter()
    {

    }




    /**
     * tools
     */

    /**
     * 定义常量
     * @param Array $defs
     * @param String $pre 常量前缀
     * @return Array
     */
    public static function def($defs = [], $pre="")
    {
        $pre = ($pre=="" || !Is::nemstr($pre)) ? "" : strtoupper($pre)."_";
        foreach ($defs as $k => $v) {
            $k = $pre.strtoupper($k);
            $ln = count(explode("_",$k));
            if (Is::associate($v) && !empty($v)) {
                self::def($v, $k);
            } else {
                if (!defined($k)) {
                    self::$cnsts[] = $k;
                    define($k, $v);
                }
            }
        }
        return $defs;
    }

    /**
     * 获取 类全称
     * foo/bar  -->  NS\foo\Bar
     * @param String $path      full class name
     * @param String $pathes...
     * @return Class            not found return null
     */
    public static function cls($path = "")
    {
        $ps = func_get_args();
        if (empty($ps)) return null;
        $cl = null;
        for ($i=0; $i<count($ps); $i++) {
            //先判断一下
            if (class_exists($ps[$i])) {
                $cl = $ps[$i];
                break;
            }

            $pi = trim($ps[$i], "/");
            $pia = explode("/", $pi);
            $pin = $pia[count($pia)-1];
            if (!Str::beginUp($pin)) {
                $pia[count($pia)-1] = ucfirst($pin);
            }
            $cls = NS . implode("\\", $pia);
            //$cls = NS . str_replace("/","\\", trim($ps[$i], "/"));
            //var_dump($cls);
            if (class_exists($cls)) {
                $cl = $cls;
                break;
            }
        }
        return $cl;
    }

    /**
     * 生成 类全称前缀
     * foo/bar  -->  NS\foo\bar\
     * @param String $path
     * @return String
     */
    public static function clspre($path = "")
    {
        $path = trim($path, "/");
        return NS . str_replace("/","\\", $path) . "\\";
    }

    /**
     * 获取不包含 namespace 前缀的 类名称
     * NS\foo\bar  -->  bar
     * @param Object $obj 类实例
     * @return String
     */
    public static function clsname($obj)
    {
        try {
            $cls = get_class($obj);
            $carr = explode("\\", $cls);
            return array_pop($carr);
        } catch(Exception $e) {
            return null;
        }
    }
}