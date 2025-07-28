<?php
/**
 * cgyio/resper 模块类
 * Module 模块基类
 */

namespace Cgy;

use Cgy\Resper;
use Cgy\App;
use Cgy\util\Is;
use Cgy\util\Str;
use Cgy\util\Cls;
use Cgy\util\Path;

class Module extends Resper
{


    /**
     * static tools
     */

    /**
     * 判断是否存在 模块
     * 模块类保存在：
     *      [MODULE_PATH]/..
     *      [ROOT_PATH]/module/.. 
     *      [APP_PATH]/module/..
     * 必须是此类的 子类
     * @param String $md 类名 或 带路径的类名 如：foo | foo/bar
     * @return Mixed 找到则返回 模块类全称，否则返回 false
     */
    public static function has($md)
    {
        //首先查找 MODULE_PATH 和 ROOT_PATH/module 
        //当 $md 是 foo/bar 形式时，亦可能定义在 app/foo/module/Bar.php
        $mcls = Cls::find("module/".$md);
        if (!empty($mcls) && is_subclass_of($mcls, Module::class)) return $mcls;

        //然后在 APP_PATH 下查找
        $acls = [];
        $adh = @opendir(APP_PATH);
        while (($app = @readdir($adh)) !== false) {
            if ($app=="." || $app=="..") continue;
            if (!is_dir(APP_PATH.DS.$app)) continue;
            $acls[] = "module/".$app."/".$md;
        }
        @closedir($adh);
        $acls = Cls::find($acls);
        if (!empty($acls)) {
            if (is_subclass_of($acls, Module::class)) return $acls;
        }

        return false;
    }

    /**
     * 根据响应者类全称，解析获取 响应者在 webroot 下的路径相关信息
     * !! 此方法仅解析 module 类型的响应者类，覆盖父类方法
     * 针对 module 类型响应者类，类全称 与 路径 的对应关系 如下：
     *      NS\module\FooBar            --> root/module/foo_bar
     *                                  --> module/foo_bar
     *      NS\module\app_name\FooBar   --> app/app_name/module/foo_bar
     * 对应的 config->context xpath 为：
     *      NS\module\FooBar            --> module/foo_bar
     *                                  --> module/foo_bar
     *      NS\module\app_name\FooBar   --> app/app_name    定义在 app 路径下的模块，使用 app 的参数
     * 对应的 操作标识前缀 为：
     *      NS\module\FooBar            --> module/foo_bar
     *                                  --> module/foo_bar
     *      NS\module\app_name\FooBar   --> app/app_name/foo_bar
     * 
     * @return Array|null 路径相关信息：
     *  [
     *      "class" => 类全称,
     *      "clsn" => 类名 FooBar 形式,
     *      "clsk" => 类名的路径格式 foo_bar 形式,
     *      "rtype" => "Module",
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
        $rtype = "Module";
        //去除 NS 前缀
        $clsn = str_replace(NS,"",$cls);
        //去除 module 前缀
        if (substr($clsn, 0,7)!=="module\\") {
            //!! 不是 module 类，通常不可能，直接返回 null
            return null;
        }
        $clsn = substr($clsn, 7);

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
            $mf = Path::find("root/module/".$clsn.EXT);
            $smf = Path::find("module/".$clsn.EXT);
            if (!empty($mf)) {
                //定义在 webroot/module 路径下的 module 类
                $ppre[] = "root/module";
            } else if (!empty($smf)) {
                //定义在 框架模块文件夹下的 module 类，框架内部的模块
                $ppre[] = "module";
            } else {
                //!! 未找到类文件，通常不可能，直接返回 null
                return null;
            }
            //xprr
            $xprr[] = "module";
            $xprr[] = $clsk;
            //oprr
            $oprr = $xprr;
        } else if (App::has($clarr[0])) {
            //定义在 app/app_name/module 路径下的 module 类
            $appn = $clarr[0];
            $ppre[] = "app";
            $ppre[] = $appn;
            $ppre[] = "module";
            $ppre = array_merge($ppre, array_slice($clarr, 1));
            //xprr
            $xprr[] = "app";
            $xprr[] = $appn;
            //oprr
            $oprr = array_merge($xprr, array_slice($clarr, 1));
            $oprr[] = $clsk;
        } else {
            //!! 路径错误，通常不可能  直接返回 null
            return null;
        }
        //将 $clsk 写回 路径数组
        $ppre[] = $clsk;

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
    
}