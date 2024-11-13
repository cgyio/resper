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
use Cgy\util\Is;
use Cgy\config\Resper as ResperConfiger;
use Cgy\Error;
use Cgy\Request;

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
     */
    protected static $request = null;
    protected static $response = null;

    

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

        var_export(self::$request);
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
     * foo/bar  -->  NS\foo\bar
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
            $cls = NS . str_replace("/","\\", $ps[$i]);
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