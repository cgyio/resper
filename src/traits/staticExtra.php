<?php
/**
 * cgyio/resper traits 可复用的类特征
 * staticExtra
 * 应与 staticCurrent 同时使用，用来在 使用单例模式的类中，单独创建额外的实例
 * 
 * 使单例模式的类，可以创建额外的实例，保存在 class::$extra [] 中
 * 通过此方式生成的实例，并不会影响 $current 单例
 * 
 *  1   引用的类必须包含一个用于缓存 额外生成的实例的 静态属性 $extra
 *  2   以 'EX_'.md5(foo::class) 为键名，键值为额外生成的 实例
 *  3   通过 self::extra() 静态方法生成此实例，并返回
 *  4   额外操作执行完成后，应通过 self::removeExtra($ins->exKey) 释放额外生成的实例
 * 
 */

namespace Cgy\traits;

use Cgy\Resper;
use Cgy\util\Is;
use Cgy\util\Str;

trait staticExtra
{
    //单例模式下，额外生成的实例，缓存在此属性下
    //public static $extra = [];

    /**
     * 单例模式的类，生成额外的实例
     * @param Array $args 类的构造函数参数
     * @return Object 此类的实例
     */
    public static function extra(...$args)
    {
        //获取 class::$extra 数组
        $exts = self::$extra;
        //检查第一个参数是否 resper 实例
        if (!empty($args) && $args[0] instanceof Resper) {
            //第一个参数是 resper 实例，则以此创建 此实例在 class::$extra 数组中的键名
            $key = "EX_".md5(get_class($args[0]));
        } else {
            //创建随机 不重复 键名
            $key = "EX_".Str::nonce(32, false);
            while(isset($exts[$key])) {
                $key = "EX_".Str::nonce(32, false);
            }
        }
        //如果已有缓存，则返回
        if (isset($exts[$key])) return $exts[$key];
        //创建额外实例
        $exo = new self(...$args);
        //写入 额外参数
        $exo->isExtra = true;
        $exo->exKey = $key;
        self::$extra[$key] = $exo;
        //返回创建的额外实例
        return $exo;
    }

    /**
     * 从 $extra 数组中释放指定的 类实例
     * 当额外的操作执行完毕后，释放生成的额外实例
     * @param String $exKey 额外实例在 $extra 数组中的 键名
     * @return void
     */
    public static function removeExtra($exKey=null)
    {
        if (Is::nemstr($exKey) && isset(self::$extra[$exKey])) {
            unset(self::$extra[$exKey]);
        }
    }
}