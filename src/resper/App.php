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
     * app 类保存在： 
     *      [APP_PATH]/..
     * 必须是此类的 子类
     * @param String $app app 名称
     * @return Mixed 找到则返回 app类全称，未找到则返回 false
     */
    public static function has($app)
    {
        $acls = Cls::find("app/".$app);
        if (!empty($acls) && is_subclass_of($acls, App::class)) return $acls;
        return false;
    }
    
    /**
     * 根据响应者类全称，解析获取 响应者在 webroot 下的路径相关信息
     * !! 此方法仅解析 app 类型的响应者类，覆盖父类方法
     * 针对 app 类型响应者类，类全称 与 路径 的对应关系 如下：
     *      NS\app\FooBar       --> app/foo_bar
     * 对应的 config->context xpath 为：
     *      NS\app\FooBar       --> app/foo_bar
     * 对应的 操作标识前缀 为：
     *      NS\app\FooBar       --> app/foo_bar
     * 
     * @return Array|null 路径相关信息：
     *  [
     *      "class" => 类全称,
     *      "clsn" => 类名 FooBar 形式,
     *      "clsk" => 类名的路径格式 foo_bar 形式,
     *      "rtype" => "App",
     *      "path" => 类对应的 文件路径前缀，可以通过 Path::find() 读取
     *      "xpath" => 类参数 在 Resper::$config->context 中的 xpath 可通过 Resper::$config->ctx($xpath) 获取参数
     *      "oprn" => 操作标识 前缀
     *  ]
     */
    public static function pathinfo()
    {
        //获取当前类全称
        $cls = static::class;
        //!! 仅 解析 module 类型的响应者类
        $rtype = "App";
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

        if (count($clarr)!=1) {
            //!! 类名错误，通常不可能，直接返回 null
            return null;
        }
        //将 $clsk 写回 路径数组
        $clarr[] = $clsk;

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
            "path" => implode("/", $clarr),
            //响应者的预设参数 在 Resper::$config->context 数组中的 xpath
            "xpath" => implode("/", $clarr),
            //此响应者类中定义的 响应方法的 操作标识 前缀
            "oprn" => implode("/", $clarr),
        ];
        return $rtn;
    }
    
}