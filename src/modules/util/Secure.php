<?php
/**
 * cgyio/resper 对输入数据安全处理 工具类
 */

namespace Cgy\util;

use Cgy\Util;
use Cgy\util\Is;

class Secure extends Util 
{
    //输入的数据
    protected $origin = null;

    //处理后数据
    protected $context = null;

    /**
     * 外部使用
     * Secure::fix($data, 过滤方法1, 过滤方法2, function($d) { 自定义过滤方法 }, ... )
     * @param Mixed $data 输入的数据
     * @param Mixed $fs 指定的过滤方法，Secure 类方法 或 自定义函数
     * @return Secure 实例
     */
    public static function fix($data, ...$fs)
    {
        $sec = new Secure();
        $sec->origin = $data;
        //按顺序执行 过滤方法
        for ($i=0;$i<count($fs);$i++) {
            $fi = $fs[$i];
            if (Is::nemstr($fi)) {
                $m = "fix".$fi;     //.ucfirst($fi);
                if (method_exists($sec, $m)) {
                    $data = $sec->$m($data);
                }
            } else if (is_callable($fi)) {
                $data = $fi($data);
            }
        }
        $sec->context = $data;
        return $sec;
    }

    /**
     * 针对 String 类型数据
     * Secure::str($str, function(){}, ...) 相当于 Secure::fix($str, IllegalChars, function(){})
     * @param String $str
     * @param Closure $fs 自定义函数
     * @return Secure 实例
     */
    public static function str($str, ...$fs)
    {
        //针对 String 预定义的 Secure 过滤方法
        $fs = array_merge([
            "IllegalChars",     //去除非法字符
        ], $fs);
        return self::fix($str, ...$fs);
    }



    /**
     * 预定义的过滤方法
     */

    /**
     * String
     * 去除非法字符
     * @param String $str
     * @return String
     */
    protected function fixIllegalChars($str)
    {
        //TODO：去除非法字符
        //...

        return $str;
    }
}