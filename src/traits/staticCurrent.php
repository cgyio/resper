<?php
/**
 * cgyio/resper traits 可复用的类特征
 * staticCurrent
 * 
 *  1   引用的类必须包含一个默认的实例，保存在 self::$current
 *  2   通过 self::current() 静态方法生成此实例，并返回
 * 
 */

namespace Cgy\traits;

trait staticCurrent
{
    //默认实例
    //public static $current = null;

    //生成默认实例
    public static function current(...$args)
    {
        if (is_null(self::$current)) self::$current = new self(...$args);
        return self::$current;
    }
}