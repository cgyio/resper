<?php
/**
 * cgyio/resper framework core
 * Resper 核心类
 */

namespace Cgy;

//使用 composer autoload
require_once(__DIR__."/../../../autoload.php");

//加载 全局方法
//require_once("resper/functions.php");

use Cgy\resper\Config;
use Cgy\resper\Responder;
use Cgy\Error;
use Cgy\Request;
use Cgy\request\Url;
//use Cgy\Response;
//use Cgy\response\Respond;
use Cgy\util\Path;
use Cgy\util\Arr;
use Cgy\util\Str;
use Cgy\util\Is;

use Cgy\traits\staticCurrent;

class Resper 
{
    //引入trait
    use staticCurrent;

    /**
     * current
     */
    public static $current = null;

    /**
     * config 设置类
     */
    public $config = null;

    /**
     * 核心类实例缓存
     * 按创建的先后顺序
     */
    //请求实例
    public $request = null;
    //响应者实例
    public $responder = null;
    //响应实例
    public $response = null;

    /**
     * 构造
     * @param Array $opt Resper 框架启动参数，来自引用页面用户输入
     *      参数格式 like 设置类 \resper\Config::$init
     * @return void
     */
    public function __construct($opt = [])
    {
        //应用 用户设置 并初始化
        $this->config = new Config($opt);
    }

    

    /**
     * !! 框架启动 入口方法
     * @param Array $opt 用户自定义参数
     * @return void;
     */
    public static function start($opt=[])
    {
        @ob_start();
        @session_start();

        //创建框架实例 单例 Resper::$current = $Resper
        $Resper = self::current($opt);

        //patch composer autoload
        self::patchAutoload();

        //应用 errorHandler 自定义错误处理
        Error::setHandler();

        /**
         * 开始 request / response 请求与响应 流程
         */
        
        //创建 Request 请求实例
        $Resper->request = Request::current();
        
        //查找并创建响应者 Responder 实例
        $Resper->responder = Responder::current();

        //创建 Response 响应实例
        $Resper->response = Response::current();

        //响应者执行响应方法
        $Resper->responder->response();

        //Response 调用输出类 输出结果
        $Resper->response->export();

        exit;
    }



    /**
     * config 与 初始化
     */

    

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
     * static tools
     */

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
}