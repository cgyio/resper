<?php
/**
 * cgyio/resper response 响应工具类
 * Respond 响应类 基类
 * 
 * 直接响应 URI 参数，可根据 URI 参数，取得 类/方法
 * App/Module 都是其子类
 * 可为 App/Module 增加响应 URI 参数的功能
 * 
 * 相当于 route 类
 */

namespace Cgy\response;

use Cgy\Resper;
use Cgy\Response;

class Respond 
{
    /**
     * 响应类(路由类)信息
     */
    public $name = "";
    public $key = "";
    public $desc = "";

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
        var_export($args);
        exit;
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
     * static tools
     */

    /**
     * 全局判断 是否存在 respond 响应类
     * 响应类保存在：
     *      [ROOT_PATH]/[DIR_LIB]/.. 
     *      [MODULE_PATH]/[module]/..
     * 必须是此类的 子类
     * @param String $cls 类名
     * @return Mixed 找到则返回 类全称，未找到则返回 false
     */
    public static function has($cls)
    {
        //首先查找 web root 下的 respond 响应类
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