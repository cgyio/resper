<?php
/**
 * cgyio/resper 核心类 
 * App 类
 */

namespace Cgy;

use Cgy\Resper;
//use Cgy\resper\Responder;
use Cgy\util\Str;
use Cgy\util\Cls;

class App extends Resper
{
    /**
     * App info
     * 覆盖 responder 基类中定义的属性
     */
    public $intr = "";  //app说明，子类覆盖
    public $name = "";  //app名称，子类覆盖
    public $key = "";   //app调用路径
    

    /**
     * 此 响应者类 是否需要 UAC 权限控制，
     * 如仅部分方法需要控制权限，设为 false，在需要控制权限的方法内部 if (Uac::grant("$app->key/method")===true) { 方法逻辑 }
     * 如所有方法都需要控制权限，设为 true
     * !! 子类覆盖
     */
    public $uac = false;

    /**
     * responder 初始化，在构造方法中执行
     * !! 子类覆盖
     * @return Responder $this
     */
    protected function init()
    {
        //初始化动作，在构造后执行，子类覆盖

        return $this;   //要返回自身
    }

    /**
     * App 内部 文件/路径 查找
     * @param String $path 文件/路径
     * @return String 完整的 文件/路径 !! 可能不存在
     */
    /*public function path($path = "")
    {
        $path = str_replace(["/", "\\"], DS, $path);
        return APP_PATH.DS.strtolower($this->name).DS.$path;
    }*/


    /**
     * static tools
     */

    /**
     * 外部通过 App::create(...) 方法创建
     */

    /**
     * 判断是否存在 app
     * @param String $app app 名称
     * @return Mixed 找到则返回 app类全称，未找到则返回 false
     */
    public static function has($app)
    {
        $appcln = Str::beginUp($app) ? $app : ucfirst($app);
        $appcls = Cls::find("App/$appcln");
        if (empty($appcls)) return false;
        return $appcls;
    }
}