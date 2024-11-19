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
use Cgy\util\Is;
use Cgy\util\Arr;
use Cgy\util\Path;

class Responder extends Seeker
{
    /**
     * 响应者实例参数
     */

    //
    /**
     * 响应类(路由类)信息
     * !! 子类必须覆盖
     */
    public $intr = "";  //responder 说明，子类覆盖
    public $name = "";  //responder 名称，子类覆盖
    public $key = "";   //responder 调用路径

    /**
     * 此 响应者类 是否需要 UAC 权限控制，
     * 如仅部分方法需要控制权限，设为 false，在需要控制权限的方法内部 if (Uac::grant("$app->key/method")===true) { 方法逻辑 }
     * 如所有方法都需要控制权限，设为 true
     * !! 子类覆盖
     */
    public $uac = false;

    /**
     * 此 响应者 是否不受 WEB_PAUSE 设置影响
     * !! 子类覆盖
     * == true 则 WEB_PAUSE==true 时，此响应者依然可以响应 request 并输出结果
     */
    public $unpause = false;

    /**
     * 构造
     * @return void
     */
    public function __construct()
    {
        //自定义初始化动作
        return $this->init();
    }

    /**
     * responder 初始化，在构造方法中执行
     * !! 子类覆盖
     * @return Responder $this
     */
    protected function init()
    {
        //初始化动作，在构造后执行，子类覆盖
        //...

        //要返回自身
        return $this;
    }

    /**
     * responder 类 内部 文件/路径 查找
     * !! 子类覆盖
     * @param String $path 文件/路径
     * @param Mixed $params 
     *      Array   Path::find() 方法的 第二参数
     *      Bool    如果传入 false，则不查找真实文件
     * @return String 完整的 文件/路径 !! 可能不存在
     */
    public function path($path = "", $params = null)
    {
        //responder 类型：App / Module / Responder
        $rtp = $this->type;
        //responder 类全称
        $cls = $this->responder;
        //responder 类名，不是全名，foo\bar\Tom --> Tom
        $name = $this->cls;
        //路径前缀
        $pre = $this->path;

        //传入 Path::find() 第二参数
        $dfp = ["inDir" => DIR_ASSET];
        if (Is::nemarr($params) && Is::associate($params)) {
            $params = Arr::extend($dfp, $params);
        } else if ($params!==false) {
            $params = $dfp;
        } else {
            $params = false;
        }
        if ($params===false) {
            //直接输出 真实的 responder 所在路径
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
        Response::dump($this->path);
        //trigger_error("php::这是错误说明，可以很长很长，for English path/foo/bar，Responder->path == ".$this->path, E_USER_ERROR);
        //Resper::foo();
        //Response::code(500);

        //return [
        //    "foo" => "bar",
        //    "tom" => "jaz"
        //];
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
        if (!empty(self::$params) && Is::nemarr(self::$params)) {
            $ps = self::$params;
            $cls = $ps["responder"];

            switch ($key) {
                //$this->ctx 返回 responder::$params 数组
                case "ctx": return $ps; break;
                //$this->cls 返回当前 responder 的 类名 不是全名
                case "cls": return Resper::clsname($this); break;
                
                /**
                 * $this->path 获取 响应者类 所在路径前缀
                 * 通常用于查找 响应者路径下文件
                 */
                case "path":
                    $clp = str_replace(NS, "", $cls);
                    $clp = str_replace("\\", "/", $clp);
                    return strtolower($clp);
                    break;
                
                /**
                 * $this->type 获取 当前响应者类型，可能是：
                 * App / Module / Responder
                 */
                case "type":
                    $appcls = Resper::cls("App");
                    $modcls = Resper::cls("Module");
                    if (is_subclass_of($cls, $appcls)) return "App";
                    if (is_subclass_of($cls, $modcls)) return "Module";
                    return "Responder";
                    break;

                /**
                 * $this->conf 获取 当前响应者的 预设参数
                 * 通过 Resper::start([...]) 修改的参数
                 */
                case "conf":
                    $rtp = $this->type;
                    $xpt = $this->path;
                    $conf = Resper::$current->config;
                    if ($rtp=="App") return $conf->ctx(strtolower($xpt));
                    if ($rtp=="Module") {
                        $xpt = "module/".strtolower($xpt);
                    } else {
                        $xpa = explode("/", $xpt);
                        if (count($xpa)>1) {
                            $xpt = "module/".strtolower($xpa[0]);
                        } else {
                            //单独定义的 responder 类 不设置 预设参数 直接返回 null
                            return null;
                        }
                    }
                    return $conf->ctx($xpt);
                    break;

                /**
                 * $this->foo 读取 self::$params["foo"]
                 */
                default:
                    if (isset($ps[$key])) return $ps[$key];
                    break;
            }
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

        var_dump(self::$params);

        //对此响应者 进行权限控制
        if ($this->uac==true) {
            //!! 权限检查，无权限则 trigger_error
            //TODO:
            //...

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
        $response = Response::current();
        $response->setData($result);

        //返回 response 实例
        return $response;
        
    }

    /**
     * !! Resper 核心方法
     * UAC 权限控制
     * !! 子类可覆盖
     * @return Responder $this
     */
    protected function uacCtrl()
    {
        //如果此 app 所有方法都需要控制权限，首先检查权限
        if (Uac::required() && $this->uac===true/* && Uac::requiredAndReady()*/) {
            $opr = "app-".strtolower($this->name);
            if (!Uac::isLogin()) {
                return $this->loginPage();
            } else if (Uac::grant($opr)!==true) {
                trigger_error("auth::无操作权限 [ OPR= $opr ]", E_USER_ERROR);
                return false;
            }
        }
    }



    



    /**
     * static tools
     */

    /**
     * 全局判断 是否存在 responder 响应类
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