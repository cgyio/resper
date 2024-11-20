<?php
/**
 * cgyio/resper 工具类
 * Num 数字处理
 * 精确处理浮点数
 */

namespace Cgy\util;

use Cgy\Util;

class Num extends Util 
{

    //默认保留小数位数
    public static $dig = 2;

    
    /**
     * 四舍五入 到指定位数
     * @param Numeric $num 
     * @param Int $dig 保留小数位数
     * @return Numeric
     */
    public static function round($num, $dig = null)
    {
        $dig = !is_int($dig) ? self::$dig : $dig;
        $d = 10 ** $dig;
        return round($num*$d)/$d;
    }

    /**
     * 整数数字转为 <1 浮点数，max 为数字最大值，超过此值则返回 1
     * 相当于 $n/$max
     * @param Int $n 
     * @param Int $max 最大值
     * @param Int $dig 保留小数位数
     */
    public static function fl($n, $max=255, $dig = null) {
        $dig = !is_int($dig) ? self::$dig : $dig;
        if ($n>$max) return 1;
        if ($n<0) return 0;
        $d = 10 ** $dig;    //10 的 dig 次方
        return round($n*$d/$max)/$d;
    }
    
    /**
     * <1 浮点数转为 整数
     * 相当于 $n*$max
     * @param Int $n 
     * @param Int $max 最大值
     * @param Int $dig 保留小数位数
     */
    public static function it($n, $max=255) {
        if ($n>1) return $max;
        if ($n<0) return 0;
        return round($n*$max);
    }
    //保留 dig 位小数
    public static function rd($n, $dig = null) {
        $d = 10 ** $dig;
        return round($n*$d)/$d;
    }

    //g 转 Kg
    public static function kg($num, $dig = null)
    {
        //$dig = !is_int($dig) ? self::$dig : $dig;
        $kg = $num>=1000 ? self::round($num/1000, $dig)."Kg" : $num."g";
        $kg = str_replace(".00","",$kg);
        return $kg;
    }

    //一直显示 kg 
    public static function kg_always($num, $dig = null)
    {
        $kg = self::round($num/1000, $dig)."Kg";
        return $kg;
    }

    //bety <--> KB <--> MB <--> GB
    public static function file_size($fsz)
    {
        if (!is_numeric($fsz)) return $fsz;
        if ($fsz<1000) {
            return $fsz." Bety";
        } else if ($fsz<1000*1000) {
            return (round(($fsz/1000)*100)/100)." KB";
        } else if ($fsz<1000*1000*1000) {
            return (round(($fsz/(1000*1000))*100)/100)." MB";
        } else {
            return (round(($fsz/(1000*1000*1000))*100)/100)." GB";
        }
    }

}