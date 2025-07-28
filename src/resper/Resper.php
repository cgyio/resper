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
use Cgy\util\Str;
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
     * 定义 可以/不可以 作为响应方法的 方法名称列表
     */
    public static $methods = [
        //不可以做为响应方法的 方法名
        "except" => [
            "init", "path", "cls", "conf", 
            "addMiddlewares", "middlewareProcess",
            "response", "responsePaused", "responseLogin", "responseTokenError",
            "__construct"
        ],

        //可以作为响应方法的 方法名 这些都是一些通用的响应方法
        "common" => [
            "default", "empty", "uac", "db", "api", "notfound"
        ],
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

                            //module class dir
                            $psr_ds = [ 
                                $app_dir.DS."module"
                            ];
                            $alo->addPsr4($ns.'\\module\\'.$app.'\\', $psr_ds);
                            $alo->addPsr4($ns.'\\module\\'.$uap.'\\', $psr_ds);
                            
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
                 * patch web lib/model/module class
                 */
                $lib_ds = array_map(function($i) {
                    return ROOT_PATH.DS.str_replace("/",DS,trim($i));
                }, DIR_LIB);
                $model_ds = array_map(function($i) {
                    return ROOT_PATH.DS.str_replace("/",DS,trim($i));
                }, DIR_MODEL);
                $alo->addPsr4($ns.'\\', $lib_ds);
                $alo->addPsr4($ns.'\\model\\', $model_ds);
                $alo->addPsr4($ns.'\\module\\', [ ROOT_PATH.DS."module" ]);

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
         *  1  从预定义的 路由表中 查找，路由表定义在 Resper::$config->context["route"] 中
         */
        $route = Resper::$config->route;
        if (Is::nemarr($route)) {
            /**
             * 路由表定义形式：
             * [
             *      "/正则表达式/" => [
             *          "resper" => "响应者类全称 或 可通过 Cls::find() 读取的类名路径",
             *          "method" => "类中的 响应方法 fooBar",
             *          "uri" => [ 通过 正则匹配 得到的 响应方法参数 序列 ],
             *      ],
             * ]
             */
            $ustr = implode("/", $uri);
            foreach ($route as $pattern => $roi) {
                //先检查 路由定义
                $rrsp = $roi["resper"] ?? null;
                if (Is::nemstr($rrsp) && !class_exists($rrsp)) $rrsp = Cls::find($rrsp);
                //路由指向的类 不存在
                if (empty($rrsp) || !class_exists($rrsp)) continue;
                $rm = $roi["method"] ?? null;
                //路由指向的方法 不在 类中
                if (!Is::nemstr($rm) || !method_exists($rrsp, $rm)) continue;

                //使用 正则 匹配 $ustr 字符串
                try {
                    $mt = preg_match($pattern, $ustr, $matches);
                    //未匹配成功，继续下一个
                    if ($mt !== 1) continue;
                    //匹配成功，将 匹配结果 作为 响应方法参数 返回
                    return [
                        "resper" => $rrsp,
                        "method" => $rm,
                        "uri" => array_slice($matches, 1)
                    ];
                } catch (\Exception $e) {
                    //正则匹配出错
                    trigger_error("resper/fatal::URL无法解析 [ pattern=".$pattern."；URL=".$ustr." ]", E_USER_ERROR);
                }
            }
        }

        /**
         *  2  从右向左 依次检查 URI 是否对应了某个 app|module|resper 响应者类
         */
        for ($i=count($uri); $i>=1; $i--) {
            //构建要检查的 类名路径 长-->短
            $cls = implode("/", array_slice($uri, 0, $i));
            //依次检查 是否对应了某个 app|module|resper 响应者类
            if ($i==1) {
                //检查是否 app 响应者，只需要 uri[0] 即可，因此 类名路径超过 1 时 不检查 是否 app
                $app = App::has($cls);
                //var_dump($cls);var_dump($app);var_dump(111);
                if (false !== $app) {
                    $ma = Resper::seekMethod($app, array_slice($uri, $i));
                    if (!empty($ma)) {
                        //找到响应者，立即返回
                        return [
                            "resper"    => $app,
                            "method"    => $ma[0],
                            "uri"       => $ma[1]
                        ];
                    }
                }
            }

            //检查是否 module 响应者
            $mod = Module::has($cls);
            //var_dump($cls);var_dump($mod);var_dump(222);
            if (false !== $mod) {
                $ma = Resper::seekMethod($mod, array_slice($uri, $i));
                if (!empty($ma)) {
                    //找到响应者，立即返回
                    return [
                        "resper"    => $mod,
                        "method"    => $ma[0],
                        "uri"       => $ma[1]
                    ];
                }
            }

            //检查是否 resper 响应者
            $rsp = Resper::has($cls);
            //var_dump($cls);var_dump($rsp);var_dump(333);
            if (false !== $rsp) {
                $ma = Resper::seekMethod($rsp, array_slice($uri, $i));
                if (!empty($ma)) {
                    //找到响应者，立即返回
                    return [
                        "resper"    => $rsp,
                        "method"    => $ma[0],
                        "uri"       => $ma[1]
                    ];
                }
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
         *  4  全部失败，调用 Resper::notfound()
         */
        return [
            "resper"    => $resperCls,
            "method"    => "notfound",
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
        if (!is_subclass_of($cls, Resper::class)) return null;

        //空 uri
        if (!Is::nemarr($uri) || !Is::indexed($uri)) {
            return ["empty", []];
        }

        //查找 响应方法 方法名转为 驼峰，首字母小写 格式
        $m = Str::camel($uri[0], false);
        //当前响应者类的 路径信息
        $pi = $cls::pathinfo();
        //Resper::$config->context 参数 xpath
        $ppre = $pi["xpath"];
        //方法名转为 全小写，下划线_ 形式
        $mk = Str::snake($m, "_");
        //获取当前 响应者类的 config 参数
        $conf = Resper::$config->ctx($ppre);
        if (!Is::nemarr($conf)) $conf = [];

        /**
         * 首先检查 是否是 通用响应方法，通常这些方法会通过对应的 Proxyer 代理类来执行最终的响应方法
         */
        if (in_array($m, Resper::$methods["common"])) {
            if ($m=="api" && count($uri)>1) {
                //针对 api 特殊处理
                //取得可能的 api 方法名 小写下划线_ 格式
                $apin = Str::snake($uri[1], "_");
                //预先解析得到的 apis 方法列表
                $apis = $conf["apis"] ?? [];
                //存在 api 方法，返回
                if (isset($apis[$apin])) {
                    $mn = $apis[$apin]["method"] ?? null;
                    if (Is::nemstr($mn)) return [ $mn, array_slice($uri, 2) ];
                }
            }
            return [ $m, array_slice($uri, 1) ];
        }

        /**
         * 然后检查 是否是 需要排除的方法
         */
        if (in_array($m, Resper::$methods["except"])) {
            //如果请求的是 需要排除的方法，直接返回 default 方法
            return [ "default", $uri ];
        }

        /**
         * 最后检查 响应者参数中的 respers|apis 方法列表，这些方法在初始化阶段已经从对应的类文件中 解析得到
         */
        //预先解析得到的 respers 方法列表
        $respers = $conf["respers"] ?? [];
        if (isset($respers[$mk])) {
            //存在 resper 方法
            $mn = $respers[$mk]["method"] ?? null;
            if (Is::nemstr($mn)) return [ $mn, array_slice($uri, 1) ];
        }

        //未找到有效的 响应方法 则返回默认方法 default
        return [ "default", $uri ];
    }

    /**
     * 全局判断 是否存在 resper 响应类
     * 响应类保存在：
     *      [ROOT_PATH]/[DIR_LIB]/.. 
     *      [APP_PATH]/[DIR_LIB]/..
     *      [MODULE_PATH]/[module]/..
     * 必须是此类的 子类
     * @param String $cls 类名 或 带路径的类名 如：foo | app/foo/bar
     * @return Mixed 找到则返回 类全称，未找到则返回 false
     */
    public static function has($cls)
    {
        //首先查找 web root 下的 responder 响应类
        $wcls = Cls::find($cls);
        if (!empty($wcls) && is_subclass_of($wcls, Resper::class)) return $wcls;

        //然后在 app 中查找
        $acls = Cls::find("app/".$cls);
        if (!empty($acls) && is_subclass_of($acls, Resper::class)) return $acls;
        $acls = [];
        $adh = @opendir(APP_PATH);
        while (($app = @readdir($adh)) !== false) {
            if ($app=="." || $app=="..") continue;
            if (!is_dir(APP_PATH.DS.$app)) continue;
            $acls[] = "app/".$app."/".$cls;
        }
        @closedir($adh);
        $acls = Cls::find($acls);
        if (!empty($acls)) {
            if (is_subclass_of($acls, Resper::class)) return $acls;
        }

        //然后在 module 中查找
        $mcls = Cls::find("module/".$cls);
        if (!empty($mcls) && is_subclass_of($mcls, Resper::class)) return $mcls;
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

    /**
     * 根据响应者类全称，解析获取 响应者在 webroot 下的路径相关信息
     * !! 此方法仅解析 自定义 resper 响应者类，对于 app|module 类型响应者，必须在对应的子类中 覆盖此方法
     * 针对 自定义 resper 响应者类，类全称 与 路径 的对应关系 如下：
     *      NS\FooBar                   --> root/library/foo_bar
     *      NS\app\app_name\FooBar      --> app/app_name/library/foo_bar
     *      NS\module\md_name\FooBar    --> module/md_name/foo_bar
     * 对应的 config->context xpath 为：
     *      NS\FooBar                   --> foo_bar
     *      NS\app\app_name\FooBar      --> app/app_name        定义在 app 路径下的 resper 类，使用 app 的参数
     *      NS\module\md_name\FooBar    --> module/md_name      定义在 module 路径下的 resper 类，使用 module 的参数
     * 对应的 操作标识前缀 为：
     *      NS\FooBar                   --> foo_bar
     *      NS\app\app_name\FooBar      --> app/app_name/foo_bar
     *      NS\module\md_name\FooBar    --> module/md_name/foo_bar
     * 
     * @return Array|null 路径相关信息：
     *  [
     *      "class" => 类全称,
     *      "clsn" => 类名 FooBar 形式,
     *      "clsk" => 类名的路径格式 foo_bar 形式,
     *      "rtype" => "Resper",
     *      "path" => 类对应的 文件路径前缀，可以通过 Path::find() 读取
     *      "xpath" => 类参数 在 Resper::$config->context 中的 xpath 可通过 Resper::$config->ctx($xpath) 获取参数
     *      "oprn" => 操作标识 前缀
     *  ]
     */
    public static function pathinfo()
    {
        //获取当前类全称
        $cls = static::class;
        //仅 解析 自定义 resper 响应者类
        //!! app|module 类型的响应者类，应在各自的子类中 覆盖此方法
        $rtype = "Resper";
        //去除 NS 前缀
        $clsn = str_replace(NS,"",$cls);
        //类全称 xpath
        $clarr = explode("\\", $clsn);
        //类名
        $clsn = array_pop($clarr);
        //路径字符统一为 全小写，下划线_
        $clarr = array_map(function($pi) {
            return Str::snake($pi, "_");
        }, $clarr);
        //类名 转为 路径形式 全小写，下划线_
        $clsk = Str::snake($clsn, "_");
        //路径前缀 []
        $ppre = [];
        //参数 xpath []
        $xprr = [];
        //操作标识 前缀 []
        $oprr = [];

        if (empty($clarr)) {
            //定义在 webroot/library 路径下的 resper 响应者类
            $ppre[] = "root/library";
            //xpath 路径
            $xprr[] = $clsk;
            //操作标识
            //$oprr[] = $clsk;
        } else if ($clarr[0]=="app") {
            //定义在 app/app_name/library 路径下的 resper 响应者类
            if (count($clarr)<2) {
                //!! 路径错误，通常不可能  直接返回 null
                return null;
            }
            //生成 路径前缀
            $ppre = array_slice($clarr, 0,2);
            $ppre[] = "library";
            $ppre = array_merge($ppre, array_slice($clarr, 2));
            //xpath
            $xprr = array_slice($clarr, 0,2);   //array_merge([], $clarr);
            //oprr
            $oprr = array_merge([], $clarr);
        } else if ($clarr[0]=="module") {
            //定义在 module/md_name 路径下的 resper 响应者类
            if (count($clarr)<2) {
                //!! 路径错误，通常不可能  直接返回 null
                return null;
            }
            //生成 路径前缀
            $ppre = array_merge($ppre, $clarr);
            //xpath
            $xprr = array_slice($clarr, 0,2);   //array_merge([], $clarr);
            //oprr
            $oprr = array_merge([], $clarr);
        } else {
            //!! 类名错误，通常不可能  直接返回 null
            return null;
        }
        //将 $clsk 写回 路径数组
        $ppre[] = $clsk;
        $oprr[] = $clsk;

        //返回解析结果
        $rtn = [
            //响应者类全称
            "class" => $cls,
            //响应者 类名 驼峰，首字母大写
            "clsn" => $clsn,
            //响应者 类名的 路径字符串格式 全小写，下划线_
            "clsk" => $clsk,
            //响应者 类型
            "rtype" => $rtype,
            //响应者类 对应的 文件路径前缀
            "path" => implode("/", $ppre),
            //响应者的预设参数 在 Resper::$config->context 数组中的 xpath
            "xpath" => implode("/", $xprr),
            //此响应者类中定义的 响应方法的 操作标识 前缀
            "oprn" => implode("/", $oprr),
        ];
        return $rtn;
    }



    /**
     * 响应者劫持
     * 在某次响应会话内部，调用其他响应者 执行指定的响应方法，获取响应结果
     * !! 以劫持的方式调用响应者，目的通常是调用这个响应者的某个实例响应方法，因此：
     * !! 此响应者的 中间件/UAC权限控制/Log日志功能 将都不会启动，
     * !! 同时此响应者也不会创建 response 响应实例，仅执行被劫持的响应方法，取得执行结果
     * 
     * 调用方式：     Resper::hijack("foo/bar/jaz", [ 前端 post 到后端的数据... ])
     *              相当于 url访问：https://host/foo/bar/jaz  
     * 
     * !! 被劫持的响应者类，必须定义在当前 WEB_ROOT 目录下
     * 通常是在某个 app 响应者类的响应方法中，访问另一个 app 响应者的某个方法
     * 
     * @param String|Array $uri 模拟访问的 URI 可以是 foo/bar/jaz 或者 ["foo", "bar", "jaz"]
     * @param Array $post 模拟前端 post 来的数据
     * @return Mixed 返回响应方法的执行结果 如果发生错误 返回 false
     */
    public static function hijack($uri, $post=[])
    {
        //根据输入的 URI 查找对应的 响应者/响应方法/方法参数
        $ps = Resper::seek($uri);
        //响应者类
        $rcls = $ps["resper"] ?? null;
        //响应方法
        $rmethod = $ps["method"] ?? null;
        //响应方法参数
        $rargs = $ps["uri"] ?? [];
        //排错
        if (
            !Is::nemstr($rcls) || !class_exists($rcls) ||
            !Is::nemstr($rmethod)
        ) {
            //未找到有效的 响应者类/响应方法，直接返回 false
            return false;
        }

        //以劫持方式 实例化响应者类
        $resper = new $rcls([
            //标记为 以劫持方式实例化
            "hijack" => true,
            //将响应参数写入 被劫持的响应者实例的 ownParams 属性中
            "ownParams" => $ps
        ]);
        //临时添加 post 数据到 $request->inputs->context
        if (!empty($post)) Request::current()->inputs->append($post);
        //执行被劫持的响应方法
        $result = $resper->response();
        //恢复 post 数据为原数据
        if (!empty($post)) Request::current()->inputs->reset();
        //释放被劫持的响应者实例
        $resper = null;

        //返回被劫持响应方法的 执行结果
        return $result;
    }

}